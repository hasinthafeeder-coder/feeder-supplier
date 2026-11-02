<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderStatusHistory;
use Feeder\Core\Models\SupplierSettlementInvoice;
use Feeder\Core\Models\SupplierSettlementInvoiceLine;
use Feeder\Core\Models\SupplierSettlementReturnedReference;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CompletedOrderArchiveWindow;
use Feeder\Core\Services\Order\SupplierDispatchNoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Live rows for supplier Completed Orders, Archived Orders, Dispatch Summary,
 * and Order Payments.
 *
 * Ownership is the authenticated supplier user. An order is included only when
 * orders.supplier_id matches that user and at least one item product belongs
 * to them, or the order has no product that belongs to a different supplier.
 * Query-string ids are ignored. Another supplier's product, unit cost, and
 * settlement line are never serialized.
 *
 * Completed and Archived use CompletedOrderArchiveWindow (DELIVERED / RETURNED
 * only). Settlement labels come from non-void supplier settlement invoice lines
 * for this supplier. A line is settled only when line_payment_status is PAID.
 * Delivery alone does not create a payment. Missing settlement stays unsettled
 * and has no amount.
 *
 * Dispatch Summary reads DISPATCHED, DELIVERED, and RETURNED orders and their
 * shipments for pending courier handover counts. History lists persisted
 * Dispatch Notes only. Creating a note does not change order status.
 *
 * Order Payments lists DELIVERED orders and persisted supplier settlement
 * invoices/payments. Courier remittance uploads are not created here.
 */
class SupplierAfterDispatchWorkspaceService
{
    public function __construct(
        private readonly SupplierDispatchNoteService $dispatchNoteService,
    ) {
    }

    public const SETTLEMENT_PENDING = 'SETTLEMENT_PENDING';

    public const SETTLEMENT_SETTLED = 'SETTLED';

    public const SETTLEMENT_NONE = 'NO_SETTLEMENT';

    public const PAYMENT_UNPAID = 'UNPAID';

    public const PAYMENT_PAID = 'PAID';

    public const PAYMENT_NONE = 'NONE';

    /**
     * @return array{
     *     summaryCounts: array{delivered: int, returned: int, settlement_pending: int, settled: int},
     *     orders: list<array<string, mixed>>,
     *     courierOptions: list<array{id: string, name: string}>
     * }
     */
    public function completed(int $supplierId): array
    {
        return $this->outcomeWorkspace($supplierId, true);
    }

    /**
     * @return array{
     *     summaryCounts: array{delivered: int, returned: int, settlement_pending: int, settled: int},
     *     orders: list<array<string, mixed>>,
     *     courierOptions: list<array{id: string, name: string}>
     * }
     */
    public function archived(int $supplierId): array
    {
        return $this->outcomeWorkspace($supplierId, false);
    }

    /**
     * @return array{
     *     pendingCouriers: list<array<string, mixed>>,
     *     dispatchHistory: list<array<string, mixed>>,
     *     courierOptions: list<array{id: string, name: string}>,
     *     supplierName: string
     * }
     */
    public function dispatchSummary(int $supplierId): array
    {
        $supplier = $this->supplier($supplierId);
        $orders = $this->shipmentOrders($supplierId);
        $notes = $this->dispatchNoteService->listForSupplier($supplierId);
        $history = $notes
            ->map(fn ($note) => $this->dispatchNoteService->serializeNote($note))
            ->values()
            ->all();

        return [
            'pendingCouriers' => $this->courierSummaries($orders),
            'dispatchHistory' => $history,
            'courierOptions' => $this->courierOptionsForSummary($orders, $history),
            'supplierName' => $this->supplierLabel($supplier),
        ];
    }

    /**
     * @return array{
     *     summaryCounts: array{
     *         pending: int,
     *         received: int,
     *         missing: int,
     *         pending_amount: ?string,
     *         currency_code: string,
     *         last_upload_at: string,
     *         last_upload_courier: string
     *     },
     *     courierUploads: list<empty>,
     *     orders: list<array<string, mixed>>,
     *     uploadHistory: list<empty>,
     *     unmatchedWaybills: list<empty>,
     *     duplicateRows: list<empty>,
     *     courierOptions: list<array{id: string, name: string}>
     * }
     */
    public function orderPayments(int $supplierId): array
    {
        $orders = $this->deliveredOrders($supplierId);
        $rows = $orders
            ->map(fn (Order $order) => $this->serializePayment($order, $supplierId))
            ->values();

        $unpaid = $rows->filter(fn (array $row) => $row['payment_status'] === self::PAYMENT_UNPAID);
        $currencies = $unpaid->pluck('settlement_currency_code')->filter()->unique()->values();
        $pendingAmount = null;

        if ($unpaid->isNotEmpty() && $currencies->count() === 1) {
            $pendingAmount = $this->formatMoney($this->sumMoney($unpaid->pluck('settlement_amount_raw')));
        }

        if ($unpaid->isEmpty()) {
            $pendingAmount = $this->formatMoney('0.00');
        }

        return [
            'summaryCounts' => [
                'pending' => $unpaid->count(),
                'received' => $rows->where('payment_status', self::PAYMENT_PAID)->count(),
                'missing' => $rows->where('payment_status', self::PAYMENT_NONE)->count(),
                'pending_amount' => $pendingAmount,
                'currency_code' => $currencies->count() === 1 ? (string) $currencies->first() : '',
                'last_upload_at' => '—',
                'last_upload_courier' => 'Not recorded',
            ],
            'courierUploads' => [],
            'orders' => $rows->map(function (array $row) {
                unset($row['settlement_amount_raw']);

                return $row;
            })->all(),
            'uploadHistory' => [],
            'unmatchedWaybills' => [],
            'duplicateRows' => [],
            'courierOptions' => $this->courierOptions($orders),
        ];
    }

    /**
     * @return array{
     *     summaryCounts: array{delivered: int, returned: int, settlement_pending: int, settled: int},
     *     orders: list<array<string, mixed>>,
     *     courierOptions: list<array{id: string, name: string}>
     * }
     */
    private function outcomeWorkspace(int $supplierId, bool $withinWindow): array
    {
        $orders = $this->outcomeOrders($supplierId, $withinWindow);
        $rows = $orders->map(fn (Order $order) => $this->serializeOutcome($order, $supplierId));
        $settled = $rows->where('settlement_status', self::SETTLEMENT_SETTLED)->count();

        return [
            'summaryCounts' => [
                'delivered' => $orders->filter(fn (Order $order) => $this->status($order) === OrderStatus::DELIVERED)->count(),
                'returned' => $orders->filter(fn (Order $order) => $this->status($order) === OrderStatus::RETURNED)->count(),
                'settlement_pending' => $rows->count() - $settled,
                'settled' => $settled,
            ],
            'orders' => $rows->values()->all(),
            'courierOptions' => $this->courierOptions($orders),
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    private function outcomeOrders(int $supplierId, bool $withinWindow): Collection
    {
        if ($supplierId <= 0) {
            return collect();
        }

        $query = $this->ownedOrdersQuery($supplierId);
        CompletedOrderArchiveWindow::apply($query, $withinWindow);

        return $query
            ->with($this->outcomeRelations($supplierId))
            ->orderByDesc('delivered_at')
            ->orderByDesc('returned_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    private function shipmentOrders(int $supplierId): Collection
    {
        if ($supplierId <= 0) {
            return collect();
        }

        return $this->ownedOrdersQuery($supplierId)
            ->whereIn('status', [
                OrderStatus::DISPATCHED->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::RETURNED->value,
            ])
            ->with([
                'items.product',
                'shipment.courier',
                'draftCourier',
                'fulfillment',
                'statusHistories',
                'dispatchNoteOrder',
            ])
            ->orderByDesc('id')
            ->get()
            ->sortByDesc(fn (Order $order) => $this->outcomeAt($order)?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @return Collection<int, Order>
     */
    private function deliveredOrders(int $supplierId): Collection
    {
        if ($supplierId <= 0) {
            return collect();
        }

        return $this->ownedOrdersQuery($supplierId)
            ->where('status', OrderStatus::DELIVERED->value)
            ->with($this->outcomeRelations($supplierId))
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * orders.supplier_id is the existing order owner. Item products that belong
     * only to another supplier exclude the order. A mixed order stays visible
     * to the order owner; foreign lines are dropped during serialization.
     */
    private function ownedOrdersQuery(int $supplierId): Builder
    {
        return Order::query()
            ->where('supplier_id', $supplierId)
            ->where(function (Builder $ownership) use ($supplierId): void {
                $ownership->whereHas('items.product', function (Builder $product) use ($supplierId): void {
                    $product->where('supplier_id', $supplierId);
                })->orWhereDoesntHave('items.product');
            });
    }

    /**
     * @return array<int|string, mixed>
     */
    private function outcomeRelations(int $supplierId): array
    {
        return [
            'items.product',
            'shipment.courier',
            'draftCourier',
            'fulfillment',
            'supplierSettlementInvoiceLines' => function ($query) use ($supplierId): void {
                $query->where('supplier_user_id', $supplierId)
                    ->with(['invoice.effectivePayment', 'product']);
            },
            'supplierSettlementReturnedReferences' => function ($query) use ($supplierId): void {
                $query->where('supplier_user_id', $supplierId)
                    ->with('product');
            },
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array<string, mixed>>
     */
    private function courierSummaries(Collection $orders): array
    {
        $groups = [];

        foreach ($orders as $order) {
            $courier = $this->courier($order);
            $key = $courier !== null ? (string) $courier->id : '';

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $key,
                    'name' => $courier?->name ?: 'No courier recorded',
                    'pending_count' => 0,
                    'delivered_count' => 0,
                    'returned_count' => 0,
                    'last_dispatch' => null,
                    'last_dispatch_at' => null,
                    'total_cod_raw' => '0.00',
                    'currency_code' => null,
                    'currency_conflict' => false,
                    'orders' => [],
                ];
            }

            $status = $this->status($order);

            if ($status === OrderStatus::DISPATCHED) {
                $dispatchedAt = $this->dispatchedAt($order);
                if ($dispatchedAt !== null && (
                    $groups[$key]['last_dispatch_at'] === null
                    || $dispatchedAt->greaterThan($groups[$key]['last_dispatch_at'])
                )) {
                    $groups[$key]['last_dispatch_at'] = $dispatchedAt;
                    $groups[$key]['last_dispatch'] = $this->formatTimestamp($dispatchedAt)['display'];
                }

                // Pending handover excludes orders already on a Dispatch Note.
                if ($order->dispatchNoteOrder === null) {
                    $groups[$key]['pending_count']++;
                    $this->addCod($groups[$key], $order);
                    $groups[$key]['orders'][] = $this->serializeShipmentOrder($order);
                }
            } elseif ($status === OrderStatus::DELIVERED) {
                $groups[$key]['delivered_count']++;
            } elseif ($status === OrderStatus::RETURNED) {
                $groups[$key]['returned_count']++;
            }
        }

        $rows = [];

        foreach ($groups as $group) {
            unset($group['last_dispatch_at']);
            $group['total_cod'] = $group['currency_conflict']
                ? null
                : $this->formatMoney($group['total_cod_raw']);
            $group['currency_code'] = $group['currency_conflict'] ? '' : (string) ($group['currency_code'] ?? '');
            unset($group['total_cod_raw'], $group['currency_conflict']);
            $rows[] = $group;
        }

        usort($rows, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function addCod(array &$group, Order $order): void
    {
        $currency = (string) ($order->currency_code_snapshot ?? '');

        if ($group['currency_code'] === null) {
            $group['currency_code'] = $currency;
        } elseif ($group['currency_code'] !== $currency) {
            $group['currency_conflict'] = true;
        }

        if ($order->customer_payable_amount !== null) {
            $group['total_cod_raw'] = $this->addMoney(
                (string) $group['total_cod_raw'],
                (string) $order->customer_payable_amount
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOutcome(Order $order, int $supplierId): array
    {
        $courier = $this->courier($order);
        $completedAt = $this->status($order) === OrderStatus::RETURNED
            ? $order->returned_at
            : $order->delivered_at;
        $formatted = $this->formatTimestamp($completedAt);
        $settlement = $this->settlementPresentation($order, $supplierId);

        return [
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) $order->customer_name_snapshot,
            'primary_phone' => (string) $order->primary_phone_snapshot,
            'courier' => $courier?->name,
            'courier_id' => $courier !== null ? (string) $courier->id : '',
            'waybill' => $order->shipment?->tracking_number,
            'cod_amount' => $this->formatMoney(
                $order->customer_payable_amount === null ? null : (string) $order->customer_payable_amount
            ),
            'currency_code' => (string) ($order->currency_code_snapshot ?? ''),
            'outcome' => $this->status($order)->value,
            'completed_at' => $formatted['display'],
            'completed_date' => $formatted['date'],
            'settlement_status' => $settlement['settlement_status'],
            'settlement_amount' => $settlement['settlement_amount'],
            'settlement_currency_code' => $settlement['currency_code'],
            'invoice_number' => $settlement['invoice_number'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipmentOrder(Order $order): array
    {
        return [
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) $order->customer_name_snapshot,
            'primary_phone' => (string) $order->primary_phone_snapshot,
            'waybill' => $order->shipment?->tracking_number,
            'invoice_number' => $order->fulfillment?->invoice_number,
            'cod_amount' => $this->formatMoney(
                $order->customer_payable_amount === null ? null : (string) $order->customer_payable_amount
            ),
            'currency_code' => (string) ($order->currency_code_snapshot ?? ''),
            'state' => $this->status($order)->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Order $order, int $supplierId): array
    {
        $courier = $this->courier($order);
        $settlement = $this->settlementPresentation($order, $supplierId);
        $paymentStatus = match ($settlement['settlement_status']) {
            self::SETTLEMENT_SETTLED => self::PAYMENT_PAID,
            self::SETTLEMENT_PENDING => self::PAYMENT_UNPAID,
            default => self::PAYMENT_NONE,
        };
        $delivered = $this->formatTimestamp($order->delivered_at);
        $updated = $this->formatTimestamp($order->updated_at);
        $received = $this->formatTimestamp($settlement['paid_at']);

        return [
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) $order->customer_name_snapshot,
            'primary_phone' => (string) $order->primary_phone_snapshot,
            'courier' => $courier?->name,
            'courier_id' => $courier !== null ? (string) $courier->id : '',
            'waybill' => $order->shipment?->tracking_number,
            'invoice_number' => $settlement['invoice_number'],
            'cod_amount' => $this->formatMoney(
                $order->customer_payable_amount === null ? null : (string) $order->customer_payable_amount
            ),
            'currency_code' => (string) ($order->currency_code_snapshot ?? ''),
            'delivery_status' => OrderStatus::DELIVERED->value,
            'payment_status' => $paymentStatus,
            'payment_received_at' => $paymentStatus === self::PAYMENT_PAID ? $received['display'] : null,
            'updated_at' => $updated['display'],
            'payment_date' => $delivered['date'],
            'source' => $settlement['invoice_number'] !== null
                ? 'Supplier settlement '.$settlement['invoice_number']
                : null,
            'settlement_amount' => $settlement['settlement_amount'],
            'settlement_amount_raw' => $settlement['amount_raw'],
            'settlement_currency_code' => $settlement['currency_code'],
        ];
    }

    /**
     * @return array{
     *     settlement_status: string,
     *     settlement_amount: ?string,
     *     amount_raw: ?string,
     *     currency_code: string,
     *     invoice_number: ?string,
     *     paid_at: ?Carbon
     * }
     */
    private function settlementPresentation(Order $order, int $supplierId): array
    {
        $lines = $this->activeLines($order, $supplierId);
        $references = $this->ownedReturnedReferences($order, $supplierId);
        $paid = $lines->contains(
            fn (SupplierSettlementInvoiceLine $line) => (string) $line->line_payment_status === SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID
        );

        if ($lines->isEmpty() && $references->isEmpty()) {
            return [
                'settlement_status' => self::SETTLEMENT_NONE,
                'settlement_amount' => null,
                'amount_raw' => null,
                'currency_code' => '',
                'invoice_number' => null,
                'paid_at' => null,
            ];
        }

        $amountSource = $lines->isNotEmpty()
            ? $lines->map(fn (SupplierSettlementInvoiceLine $line) => (string) $line->line_payable)
            : $references->map(fn (SupplierSettlementReturnedReference $reference) => (string) $reference->display_payable);
        $currencies = ($lines->isNotEmpty() ? $lines : $references)
            ->map(fn ($row) => (string) ($row->currency_code_snapshot ?? ''))
            ->unique()
            ->values();
        $amountRaw = $currencies->count() === 1 ? $this->sumMoney($amountSource) : null;
        $invoice = $lines->sortByDesc('id')->first()?->invoice;
        $paidAt = null;

        if ($paid) {
            $paidAt = $lines
                ->filter(fn (SupplierSettlementInvoiceLine $line) => (string) $line->line_payment_status === SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID)
                ->map(fn (SupplierSettlementInvoiceLine $line) => $line->invoice?->effectivePayment?->confirmed_at)
                ->filter()
                ->sortByDesc(fn (Carbon $confirmedAt) => $confirmedAt->getTimestamp())
                ->first();
        }

        return [
            'settlement_status' => $paid ? self::SETTLEMENT_SETTLED : self::SETTLEMENT_PENDING,
            'settlement_amount' => $amountRaw === null ? null : $this->formatMoney($amountRaw),
            'amount_raw' => $amountRaw,
            'currency_code' => $currencies->count() === 1 ? (string) $currencies->first() : '',
            'invoice_number' => $invoice?->invoice_number,
            'paid_at' => $paidAt,
        ];
    }

    /**
     * @return Collection<int, SupplierSettlementInvoiceLine>
     */
    private function activeLines(Order $order, int $supplierId): Collection
    {
        return $order->supplierSettlementInvoiceLines
            ->filter(function (SupplierSettlementInvoiceLine $line) use ($supplierId): bool {
                if ((int) $line->supplier_user_id !== $supplierId) {
                    return false;
                }

                $invoice = $line->invoice;
                if ($invoice === null
                    || (string) $invoice->instruction_status === SupplierSettlementInvoice::INSTRUCTION_STATUS_VOIDED
                ) {
                    return false;
                }

                return $this->productBelongsToSupplier($line->product?->supplier_id, $supplierId);
            })
            ->values();
    }

    /**
     * @return Collection<int, SupplierSettlementReturnedReference>
     */
    private function ownedReturnedReferences(Order $order, int $supplierId): Collection
    {
        return $order->supplierSettlementReturnedReferences
            ->filter(function (SupplierSettlementReturnedReference $reference) use ($supplierId): bool {
                if ((int) $reference->supplier_user_id !== $supplierId) {
                    return false;
                }

                return $this->productBelongsToSupplier($reference->product?->supplier_id, $supplierId);
            })
            ->values();
    }

    private function productBelongsToSupplier(mixed $productSupplierId, int $supplierId): bool
    {
        if ($productSupplierId === null) {
            return true;
        }

        return (int) $productSupplierId === $supplierId;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: string, name: string}>
     */
    private function courierOptions(Collection $orders): array
    {
        $options = [];

        foreach ($orders as $order) {
            $courier = $this->courier($order);
            if ($courier === null) {
                continue;
            }

            $options[(string) $courier->id] = [
                'id' => (string) $courier->id,
                'name' => (string) $courier->name,
            ];
        }

        $rows = array_values($options);
        usort($rows, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $rows;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<array<string, mixed>>  $history
     * @return list<array{id: string, name: string}>
     */
    private function courierOptionsForSummary(Collection $orders, array $history): array
    {
        $options = [];

        foreach ($this->courierOptions($orders) as $option) {
            $options[$option['id']] = $option;
        }

        foreach ($history as $note) {
            $id = (string) ($note['courier_id'] ?? '');
            $name = trim((string) ($note['courier'] ?? ''));
            if ($id === '' || $name === '' || $name === 'No courier recorded') {
                continue;
            }

            $options[$id] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        $rows = array_values($options);
        usort($rows, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $rows;
    }

    private function courier(Order $order): ?Courier
    {
        return $order->shipment?->courier ?? $order->draftCourier;
    }

    private function status(Order $order): OrderStatus
    {
        return $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);
    }

    private function outcomeAt(Order $order): ?Carbon
    {
        return match ($this->status($order)) {
            OrderStatus::DELIVERED => $order->delivered_at,
            OrderStatus::RETURNED => $order->returned_at,
            OrderStatus::DISPATCHED => $this->dispatchedAt($order),
            default => null,
        };
    }

    private function dispatchedAt(Order $order): ?Carbon
    {
        $history = $order->statusHistories
            ->filter(function (OrderStatusHistory $history): bool {
                $to = $history->to_status instanceof OrderStatus
                    ? $history->to_status
                    : OrderStatus::tryFrom((string) $history->to_status);

                return $to === OrderStatus::DISPATCHED;
            })
            ->sortByDesc(fn (OrderStatusHistory $history) => $history->created_at?->getTimestamp() ?? 0)
            ->first();

        return $history?->created_at;
    }

    private function supplier(int $supplierId): ?User
    {
        if ($supplierId <= 0) {
            return null;
        }

        return User::query()->with('company')->find($supplierId);
    }

    private function supplierLabel(?User $supplier): string
    {
        $company = trim((string) ($supplier?->company?->name ?? ''));

        if ($company !== '') {
            return $company;
        }

        $email = trim((string) ($supplier?->email ?? ''));

        return $email !== '' ? $email : 'Supplier';
    }

    /**
     * @return array{display: ?string, date: string, date_display: ?string, time: ?string, time_display: ?string}
     */
    private function formatTimestamp(mixed $value): array
    {
        if (! $value instanceof Carbon) {
            return [
                'display' => null,
                'date' => '',
                'date_display' => null,
                'time' => null,
                'time_display' => null,
            ];
        }

        $local = $value->copy()->timezone(config('app.timezone'));

        return [
            'display' => $local->format('d M Y, H:i'),
            'date' => $local->toDateString(),
            'date_display' => $local->format('d M Y'),
            'time' => $local->format('H:i'),
            'time_display' => $local->format('h:i A'),
        ];
    }

    private function formatMoney(?string $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return number_format((float) $amount, 2);
    }

    /**
     * @param  iterable<mixed>  $amounts
     */
    private function sumMoney(iterable $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }

            $total = $this->addMoney($total, (string) $amount);
        }

        return $total;
    }

    private function addMoney(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 2);
        }

        return number_format(((float) $left) + ((float) $right), 2, '.', '');
    }
}
