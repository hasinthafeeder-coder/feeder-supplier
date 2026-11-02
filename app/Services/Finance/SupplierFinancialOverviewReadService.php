<?php

namespace App\Services\Finance;

use Feeder\Core\Models\Market;
use Feeder\Core\Models\SupplierSettlementBatch;
use Feeder\Core\Models\SupplierSettlementInvoice;
use Feeder\Core\Models\User;
use Feeder\Core\Support\MarketBusinessDateResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only supplier financial overview for the authenticated supplier user.
 *
 * Totals are sums of that user's own supplier_settlement_invoice_lines.line_payable
 * values. The company invoice payable_total, supplier payment rows, bank-export
 * lines, and returned references are not totals on this screen.
 */
class SupplierFinancialOverviewReadService
{
    public function __construct(
        private readonly MarketBusinessDateResolver $businessDates,
    ) {}

    /**
     * Summary cards are lifetime totals, except today's payable.
     * The date filter applies only to the batch table.
     *
     * @param  Collection<int, Market>  $markets
     * @return array{
     *     summaries: array<string, array{
     *         currency_code: string,
     *         total_payable: ?string,
     *         total_paid: ?string,
     *         outstanding: ?string,
     *         today_payable: ?string,
     *         business_date: string,
     *         notices: list<string>
     *     }>,
     *     rows: LengthAwarePaginator
     * }
     */
    public function forOverview(
        User $actor,
        Collection $markets,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage = 15,
    ): array {
        $lines = $this->eligibleLines((int) $actor->id);

        return [
            'summaries' => $this->summaries($markets, $lines),
            'rows' => $this->paginateRows($lines, $markets, $dateFrom, $dateTo, $perPage),
        ];
    }

    /**
     * Owned batch detail: summary from the supplier's eligible lines on the batch,
     * plus a paginated order-wise line breakdown. Uses persisted line snapshots only.
     *
     * @return array{
     *     batch: SupplierSettlementBatch,
     *     summary: array{
     *         currency_code: string,
     *         net_payable: ?string,
     *         payment_status: string,
     *         instruction_status: string,
     *         batch_status: string,
     *         line_count: int,
     *         invoice_count: int
     *     },
     *     lines: LengthAwarePaginator
     * }
     */
    public function loadOwnedBatchDetail(
        User $actor,
        SupplierSettlementBatch $batch,
        int $perPage = 25,
    ): array {
        $this->assertOwnedBatch($actor, $batch);

        $ownedLines = $this->ownedBatchLineQuery((int) $actor->id, (int) $batch->id)
            ->orderBy('lines.id')
            ->get();

        if ($ownedLines->isEmpty()) {
            throw new NotFoundHttpException('Settlement batch not found.');
        }

        $perPage = max(1, $perPage);
        $page = Paginator::resolveCurrentPage();
        $slice = $ownedLines->slice(($page - 1) * $perPage, $perPage)->values();
        $rows = $slice->map(fn (object $line): array => $this->mapBatchLineRow($line));

        $lines = (new Paginator($rows, $ownedLines->count(), $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]))->withQueryString();

        return [
            'batch' => $batch,
            'summary' => $this->batchSummary($batch, $ownedLines),
            'lines' => $lines,
        ];
    }

    public function assertOwnedBatch(User $actor, SupplierSettlementBatch $batch): void
    {
        if ($batch->voided_at !== null) {
            throw new NotFoundHttpException('Settlement batch not found.');
        }

        $owned = $this->ownedBatchLineQuery((int) $actor->id, (int) $batch->id)->exists();

        if (! $owned) {
            throw new NotFoundHttpException('Settlement batch not found.');
        }
    }

    /**
     * Owned lines on invoices that are still an active settlement instruction.
     *
     * Product ownership matches SupplierAfterDispatchWorkspaceService: a missing
     * product, a soft-deleted product, or a product with no supplier_id stays
     * with the line's supplier user. A product owned by someone else does not.
     *
     * @return Collection<int, object>
     */
    private function eligibleLines(int $supplierUserId): Collection
    {
        return $this->eligibleLineQuery($supplierUserId)->get();
    }

    private function eligibleLineQuery(int $supplierUserId): Builder
    {
        return $this->ownedLinesBaseQuery($supplierUserId)
            ->select([
                'lines.currency_code_snapshot',
                'lines.order_id',
                'lines.order_number_snapshot',
                'invoices.id as invoice_id',
                'invoices.payment_status',
                'invoices.instruction_status',
                'batches.id as batch_id',
                'batches.uuid as batch_uuid',
                'batches.batch_number',
                'batches.settlement_date',
                'batches.status as batch_status',
                'batches.market_code_snapshot',
            ])
            ->selectRaw('CAST(lines.line_payable AS CHAR) as line_payable');
    }

    private function ownedBatchLineQuery(int $supplierUserId, int $batchId): Builder
    {
        return $this->ownedLinesBaseQuery($supplierUserId)
            ->leftJoin('orders', 'orders.id', '=', 'lines.order_id')
            ->where('batches.id', $batchId)
            ->select([
                'lines.id',
                'lines.order_number_snapshot',
                'lines.tracking_number_snapshot',
                'lines.settlement_date',
                'lines.order_status_snapshot',
                'lines.product_name_snapshot',
                'lines.variant_name_snapshot',
                'lines.quantity',
                'lines.unit_cost',
                'lines.line_payable',
                'lines.currency_code_snapshot',
                'lines.line_payment_status',
                'invoices.id as invoice_id',
                'invoices.payment_status as invoice_payment_status',
                'invoices.instruction_status',
                'orders.created_at as order_created_at',
            ])
            ->selectRaw('CAST(lines.line_payable AS CHAR) as line_payable_raw')
            ->selectRaw('CAST(lines.unit_cost AS CHAR) as unit_cost_raw');
    }

    /**
     * Shared ownership + active-instruction filters for overview and batch detail.
     */
    private function ownedLinesBaseQuery(int $supplierUserId): Builder
    {
        return DB::table('supplier_settlement_invoice_lines as lines')
            ->join('supplier_settlement_invoices as invoices', 'invoices.id', '=', 'lines.invoice_id')
            ->join('supplier_settlement_batches as batches', 'batches.id', '=', 'invoices.batch_id')
            ->leftJoin('products', 'products.id', '=', 'lines.product_id')
            ->where('lines.supplier_user_id', $supplierUserId)
            ->where(function ($ownership) use ($supplierUserId): void {
                $ownership->whereNull('products.id')
                    ->orWhereNotNull('products.deleted_at')
                    ->orWhereNull('products.supplier_id')
                    ->orWhere('products.supplier_id', $supplierUserId);
            })
            ->whereNull('invoices.voided_at')
            ->whereNull('batches.voided_at')
            ->where('invoices.instruction_status', '!=', SupplierSettlementInvoice::INSTRUCTION_STATUS_VOIDED);
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{
     *     currency_code: string,
     *     net_payable: ?string,
     *     payment_status: string,
     *     instruction_status: string,
     *     batch_status: string,
     *     line_count: int,
     *     invoice_count: int
     * }
     */
    private function batchSummary(SupplierSettlementBatch $batch, Collection $lines): array
    {
        $currencies = [];
        $paymentStatuses = [];
        $instructionStatuses = [];
        $invoiceIds = [];
        $netPayable = '0.00';
        $netPayableValid = true;

        foreach ($lines as $line) {
            $currency = strtoupper(trim((string) ($line->currency_code_snapshot ?? '')));
            if ($currency !== '') {
                $currencies[$currency] = true;
            }

            $payment = trim((string) ($line->invoice_payment_status ?? ''));
            if ($payment !== '') {
                $paymentStatuses[$payment] = true;
            }

            $instruction = trim((string) ($line->instruction_status ?? ''));
            if ($instruction !== '') {
                $instructionStatuses[$instruction] = true;
            }

            $invoiceIds[(int) ($line->invoice_id ?? 0)] = true;

            if (! $netPayableValid) {
                continue;
            }

            $amount = $this->normalizeMoney($line->line_payable_raw ?? $line->line_payable ?? null);

            if ($amount === null) {
                $netPayableValid = false;
                $netPayable = null;

                continue;
            }

            $netPayable = bcadd($netPayable, $amount, 2);
        }

        unset($invoiceIds[0]);

        $currencyCodes = array_keys($currencies);
        $currencyCode = count($currencyCodes) === 1 ? $currencyCodes[0] : '';

        return [
            'currency_code' => $currencyCode,
            'net_payable' => $netPayableValid ? $netPayable : null,
            'payment_status' => $this->joinStatuses($paymentStatuses),
            'instruction_status' => $this->joinStatuses($instructionStatuses),
            'batch_status' => trim((string) ($batch->status ?? '')),
            'line_count' => $lines->count(),
            'invoice_count' => count($invoiceIds),
        ];
    }

    /**
     * @param  array<string, true>  $statuses
     */
    private function joinStatuses(array $statuses): string
    {
        $keys = array_keys($statuses);
        sort($keys);

        return implode(', ', $keys);
    }

    /**
     * @return array{
     *     order_number: string,
     *     waybill: ?string,
     *     settlement_date: ?string,
     *     order_date: ?string,
     *     order_status: ?string,
     *     product_name: ?string,
     *     variant_name: ?string,
     *     quantity: ?int,
     *     unit_cost: ?string,
     *     line_payable: ?string,
     *     currency_code: ?string,
     *     line_payment_status: ?string
     * }
     */
    private function mapBatchLineRow(object $line): array
    {
        $orderNumber = trim((string) ($line->order_number_snapshot ?? ''));
        $waybill = trim((string) ($line->tracking_number_snapshot ?? ''));
        $productName = trim((string) ($line->product_name_snapshot ?? ''));
        $variantName = trim((string) ($line->variant_name_snapshot ?? ''));
        $orderStatus = trim((string) ($line->order_status_snapshot ?? ''));
        $lineStatus = trim((string) ($line->line_payment_status ?? ''));
        $currency = strtoupper(trim((string) ($line->currency_code_snapshot ?? '')));

        return [
            'order_number' => $orderNumber !== '' ? $orderNumber : '—',
            'waybill' => $waybill !== '' ? $waybill : null,
            'settlement_date' => $this->dateString($line->settlement_date ?? null),
            'order_date' => $this->dateString($line->order_created_at ?? null),
            'order_status' => $orderStatus !== '' ? $orderStatus : null,
            'product_name' => $productName !== '' ? $productName : null,
            'variant_name' => $variantName !== '' ? $variantName : null,
            'quantity' => isset($line->quantity) ? (int) $line->quantity : null,
            'unit_cost' => $this->normalizeMoney($line->unit_cost_raw ?? $line->unit_cost ?? null),
            'line_payable' => $this->normalizeMoney($line->line_payable_raw ?? $line->line_payable ?? null),
            'currency_code' => $currency !== '' ? $currency : null,
            'line_payment_status' => $lineStatus !== '' ? $lineStatus : null,
        ];
    }

    /**
     * @param  Collection<int, Market>  $markets
     * @param  Collection<int, object>  $lines
     * @return array<string, array{
     *     currency_code: string,
     *     total_payable: ?string,
     *     total_paid: ?string,
     *     outstanding: ?string,
     *     today_payable: ?string,
     *     business_date: string,
     *     notices: list<string>
     * }>
     */
    private function summaries(Collection $markets, Collection $lines): array
    {
        $summaries = [];

        foreach ($markets as $market) {
            $marketCode = strtolower(trim((string) $market->code));
            $currency = strtoupper(trim((string) ($market->currency->iso_code ?? '')));
            $businessDate = $this->businessDates->businessDateString($market);

            if ($currency === '') {
                $summaries[$marketCode] = $this->emptySummary('', $businessDate, false);

                continue;
            }

            $summary = $this->emptySummary($currency, $businessDate, true);

            foreach ($lines as $line) {
                if (strtolower(trim((string) ($line->market_code_snapshot ?? ''))) !== $marketCode) {
                    continue;
                }

                $lineCurrency = strtoupper(trim((string) ($line->currency_code_snapshot ?? '')));

                if ($lineCurrency !== $currency) {
                    $summary = $this->addNotice($summary, $this->currencyNotice($lineCurrency, $currency));

                    continue;
                }

                $paymentStatus = trim((string) $line->payment_status);

                if (! $this->isRecognizedPaymentStatus($paymentStatus)) {
                    $summary = $this->addNotice(
                        $summary,
                        'An invoice payment status is not PAID or UNPAID. That amount is not included in these totals.',
                    );

                    continue;
                }

                $summary['total_payable'] = $this->addAmount($summary['total_payable'], $line->line_payable);

                if ($paymentStatus === SupplierSettlementInvoice::PAYMENT_STATUS_PAID) {
                    $summary['total_paid'] = $this->addAmount($summary['total_paid'], $line->line_payable);
                } else {
                    $summary['outstanding'] = $this->addAmount($summary['outstanding'], $line->line_payable);
                }

                if ($this->dateString($line->settlement_date) === $businessDate) {
                    $summary['today_payable'] = $this->addAmount($summary['today_payable'], $line->line_payable);
                }
            }

            $summaries[$marketCode] = $summary;
        }

        return $summaries;
    }

    /**
     * @param  Collection<int, object>  $lines
     * @param  Collection<int, Market>  $markets
     */
    private function paginateRows(
        Collection $lines,
        Collection $markets,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
    ): LengthAwarePaginator {
        $from = $this->filterDate($dateFrom);
        $to = $this->filterDate($dateTo);
        $perPage = max(1, $perPage);
        $marketCurrencies = $this->marketCurrencies($markets);
        $groups = [];

        foreach ($lines as $line) {
            $settlementDate = $this->dateString($line->settlement_date);

            if (! $this->inDateRange($settlementDate, $from, $to)) {
                continue;
            }

            $currency = strtoupper(trim((string) $line->currency_code_snapshot));
            $key = ((int) $line->invoice_id).'|'.$currency;
            $paymentStatus = trim((string) ($line->payment_status ?? ''));
            $lineMarket = strtolower(trim((string) ($line->market_code_snapshot ?? '')));
            $marketCurrency = $marketCurrencies[$lineMarket] ?? null;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'invoice_id' => (int) $line->invoice_id,
                    'batch_uuid' => trim((string) ($line->batch_uuid ?? '')),
                    'batch_number' => trim((string) ($line->batch_number ?? '')),
                    'settlement_date' => $settlementDate ?? '',
                    'currency_code' => $currency,
                    'invoice_count' => 1,
                    'order_keys' => [],
                    'net_payable' => '0.00',
                    'payment_status' => $paymentStatus,
                    'instruction_status' => trim((string) ($line->instruction_status ?? '')),
                    'batch_status' => trim((string) ($line->batch_status ?? '')),
                    'currency_notice' => $this->rowCurrencyNotice($currency, $marketCurrency),
                    'payment_notice' => $this->isRecognizedPaymentStatus($paymentStatus)
                        ? ''
                        : 'Payment status is not PAID or UNPAID. Not included in the summary totals.',
                ];
            }

            $orderKey = $this->overviewOrderKey($line);
            if ($orderKey !== null) {
                $groups[$key]['order_keys'][$orderKey] = true;
            }

            $groups[$key]['net_payable'] = $this->addAmount($groups[$key]['net_payable'], $line->line_payable);
        }

        $rows = array_values($groups);
        usort($rows, function (array $left, array $right): int {
            $byDate = strcmp($right['settlement_date'], $left['settlement_date']);

            if ($byDate !== 0) {
                return $byDate;
            }

            return $right['invoice_id'] <=> $left['invoice_id'];
        });

        $page = Paginator::resolveCurrentPage();
        $slice = array_map(function (array $row): array {
            $row['order_count'] = count($row['order_keys'] ?? []);
            unset($row['invoice_id'], $row['order_keys']);

            return $row;
        }, array_slice($rows, ($page - 1) * $perPage, $perPage));

        return (new Paginator($slice, count($rows), $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]))->withQueryString();
    }

    private function isRecognizedPaymentStatus(string $status): bool
    {
        return $status === SupplierSettlementInvoice::PAYMENT_STATUS_PAID
            || $status === SupplierSettlementInvoice::PAYMENT_STATUS_UNPAID;
    }

    private function overviewOrderKey(object $line): ?string
    {
        if ($line->order_id !== null && $line->order_id !== '') {
            return 'id:'.(int) $line->order_id;
        }

        $number = trim((string) ($line->order_number_snapshot ?? ''));

        return $number !== '' ? 'number:'.$number : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function addNotice(array $summary, string $notice): array
    {
        if (! in_array($notice, $summary['notices'], true)) {
            $summary['notices'][] = $notice;
        }

        return $summary;
    }

    private function currencyNotice(string $lineCurrency, string $marketCurrency): string
    {
        if ($lineCurrency === '') {
            return 'A settlement line has no currency recorded. That amount is not included in these totals.';
        }

        return 'A settlement line is stored in '.$lineCurrency.', not '.$marketCurrency.'. That amount is not included in these totals.';
    }

    private function rowCurrencyNotice(string $lineCurrency, ?string $marketCurrency): string
    {
        if ($marketCurrency === null || $marketCurrency === '' || $lineCurrency === $marketCurrency) {
            return '';
        }

        if ($lineCurrency === '') {
            return 'No currency recorded. Not included in the '.$marketCurrency.' market totals.';
        }

        return 'Stored in '.$lineCurrency.'. Not included in the '.$marketCurrency.' market totals.';
    }

    /**
     * @param  Collection<int, Market>  $markets
     * @return array<string, string>
     */
    private function marketCurrencies(Collection $markets): array
    {
        $currencies = [];

        foreach ($markets as $market) {
            $currencies[strtolower(trim((string) $market->code))] = strtoupper(trim((string) ($market->currency->iso_code ?? '')));
        }

        return $currencies;
    }

    /**
     * @return array{
     *     currency_code: string,
     *     total_payable: ?string,
     *     total_paid: ?string,
     *     outstanding: ?string,
     *     today_payable: ?string,
     *     business_date: string,
     *     notices: list<string>
     * }
     */
    private function emptySummary(string $currency, string $businessDate, bool $supported): array
    {
        $amount = $supported ? '0.00' : null;

        return [
            'currency_code' => $currency,
            'total_payable' => $amount,
            'total_paid' => $amount,
            'outstanding' => $amount,
            'today_payable' => $amount,
            'business_date' => $businessDate,
            'notices' => $currency === ''
                ? ['This market has no currency code, so amounts are not totaled.']
                : [],
        ];
    }

    private function inDateRange(?string $date, ?string $from, ?string $to): bool
    {
        if ($from !== null && ($date === null || $date < $from)) {
            return false;
        }

        if ($to !== null && ($date === null || $date > $to)) {
            return false;
        }

        return true;
    }

    private function filterDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        return $this->dateString($date);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $date = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    private function addAmount(?string $total, mixed $raw): ?string
    {
        if ($total === null) {
            return null;
        }

        $amount = $this->normalizeMoney($raw);

        if ($amount === null) {
            return null;
        }

        return bcadd($total, $amount, 2);
    }

    private function normalizeMoney(mixed $amount): ?string
    {
        $this->assertBcmath();

        if (is_int($amount)) {
            $amount = (string) $amount;
        }

        if (is_float($amount) || ! is_string($amount)) {
            return null;
        }

        $trimmed = trim($amount);

        if (preg_match('/^-?\d+(\.\d+)?$/', $trimmed) !== 1) {
            return null;
        }

        $negative = str_starts_with($trimmed, '-');
        $absolute = $negative ? substr($trimmed, 1) : $trimmed;
        $scaled = bcadd($absolute, '0', 2);

        return $negative ? '-'.$scaled : $scaled;
    }

    private function assertBcmath(): void
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('The bcmath extension is required for financial overview totals.');
        }
    }
}
