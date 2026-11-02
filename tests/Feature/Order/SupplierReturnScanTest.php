<?php

namespace Tests\Feature\Order;

use Carbon\Carbon;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\ResellerReturnReason;
use Feeder\Core\Enums\ShipmentStatus;
use Feeder\Core\Enums\StockReservationStatus;
use Feeder\Core\Enums\SupplierFulfillmentStatus;
use Feeder\Core\Models\OrderStatusHistory;
use Feeder\Core\Models\StockConsumption;
use Feeder\Core\Models\StockReservation;
use Feeder\Core\Models\StockRestoration;
use Feeder\Core\Services\Order\OrderStatusService;
use Feeder\Core\Services\Order\SupplierReturnScanService;
use Feeder\Core\Services\StockService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierReturnScanTest extends TestCase
{
    use SetsUpSupplierOrderApiData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->allowPermissions([
            'orders.view',
            'orders.return',
            'orders.pack',
            'orders.send-to-print',
            'orders.print',
            'orders.send-to-packing',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_successful_return_scan_for_owned_dispatched_order(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;

        $response = $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Order marked as returned. Stock was restored.')
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.tracking_number', $trackingNumber)
            ->assertJsonPath('data.status', OrderStatus::RETURNED->value)
            ->assertJsonPath('data.is_damaged', false)
            ->assertJsonPath('data.stock_restored', true);

        $this->assertNotEmpty($response->json('data.returned_at'));
        $this->assertNotEmpty($response->json('data.restored_items'));

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::RETURNED, $fresh->status);
        $this->assertNotNull($fresh->returned_at);
        $this->assertFalse((bool) $fresh->return_is_damaged);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::DISPATCHED->value,
            'to_status' => OrderStatus::RETURNED->value,
            'reason' => SupplierReturnScanService::HISTORY_REASON,
            'changed_by' => $context['supplier']->id,
        ]);
    }

    public function test_valid_scan_restores_consumed_stock_quantities(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $variantId = (int) $context['variant']->id;
        $stockService = app(StockService::class);

        $this->assertSame(2, $stockService->consumedQuantityForVariant($variantId));
        $this->assertSame(0, $stockService->restoredQuantityForVariant($variantId));
        $this->assertSame(3, $stockService->availableQuantityForVariant($variantId));

        $consumption = StockConsumption::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($consumption);
        $this->assertSame(2, (int) $consumption->quantity);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => (string) $order->shipment->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('data.stock_restored', true)
            ->assertJsonPath('data.restored_items.0.quantity', 2)
            ->assertJsonPath('data.restored_items.0.product_variant_id', $variantId);

        $this->assertDatabaseHas('stock_restorations', [
            'order_id' => $order->id,
            'stock_consumption_id' => $consumption->id,
            'product_variant_id' => $variantId,
            'quantity' => 2,
            'restored_by' => $context['supplier']->id,
        ]);

        // Historical consumption is preserved; availability accounts for restoration.
        $this->assertSame(2, $stockService->consumedQuantityForVariant($variantId));
        $this->assertSame(2, $stockService->restoredQuantityForVariant($variantId));
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));
        $this->assertSame(1, StockConsumption::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
    }

    public function test_return_scan_keeps_fulfillment_dispatched(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $shipmentStatusBefore = $order->shipment->status;

        $reservationBefore = StockReservation::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($reservationBefore);
        $this->assertSame(StockReservationStatus::CONSUMED, $reservationBefore->status);
        $reservationUpdatedAt = $reservationBefore->updated_at?->toDateTimeString();

        $fulfillment = $order->fulfillment;
        $this->assertNotNull($fulfillment);
        $this->assertSame(SupplierFulfillmentStatus::DISPATCHED, $fulfillment->status);
        $this->assertNull($fulfillment->cancelled_at);
        $fulfillmentUpdatedAt = $fulfillment->updated_at?->toDateTimeString();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertOk();

        $fresh = $order->fresh(['fulfillment', 'shipment']);
        $this->assertSame(OrderStatus::RETURNED, $fresh->status);
        $this->assertSame(SupplierFulfillmentStatus::DISPATCHED, $fresh->fulfillment?->status);
        $this->assertNull($fresh->fulfillment?->cancelled_at);
        $this->assertSame($fulfillmentUpdatedAt, $fresh->fulfillment?->updated_at?->toDateTimeString());
        $this->assertSame($shipmentStatusBefore, $fresh->shipment?->status);
        $this->assertSame(ShipmentStatus::BOOKED, $fresh->shipment?->status);

        $reservationAfter = StockReservation::query()->where('order_id', $order->id)->first();
        $this->assertSame(StockReservationStatus::CONSUMED, $reservationAfter?->status);
        $this->assertSame($reservationUpdatedAt, $reservationAfter?->updated_at?->toDateTimeString());
    }

    public function test_missing_and_malformed_waybill_are_rejected(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->postJson(route('orders.dispatch.scan-return'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tracking_number']);

        $this->actingAs($supplier)
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tracking_number']);

        $this->actingAs($supplier)
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => str_repeat('W', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tracking_number']);
    }

    public function test_unknown_waybill_returns_not_found_without_leak(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => 'WB-DOES-NOT-EXIST',
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED')
            ->assertJsonPath('message', 'Order not found.')
            ->assertJsonMissingPath('data.id');
    }

    public function test_ambiguous_waybill_is_rejected_without_choosing(): void
    {
        $first = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $orderA = $this->moveToDispatched($first);

        $second = $this->makeConfirmedOrderContext(
            stockQuantity: 5,
            orderQuantity: 1,
            supplier: $first['supplier'],
        );
        $orderB = $this->moveToDispatched($second);

        $sharedWaybill = 'WB-AMBIGUOUS-RETURN-001';
        $orderA->shipment->forceFill(['tracking_number' => $sharedWaybill])->save();
        $orderB->shipment->forceFill(['tracking_number' => $sharedWaybill])->save();

        $this->actingAs($first['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $sharedWaybill,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AMBIGUOUS_TRACKING_NUMBER')
            ->assertJsonPath(
                'message',
                'Multiple orders found for this waybill. Please scan the correct order identifier or contact support.',
            )
            ->assertJsonMissingPath('data.id');

        $this->assertSame(OrderStatus::DISPATCHED, $orderA->fresh()->status);
        $this->assertSame(OrderStatus::DISPATCHED, $orderB->fresh()->status);
        $this->assertSame(0, StockRestoration::query()->whereIn('order_id', [$orderA->id, $orderB->id])->count());
    }

    public function test_waybill_belonging_to_another_supplier_is_rejected_without_leak(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($owner);
        $trackingNumber = (string) $order->shipment->tracking_number;

        $intruder = $this->makeSupplierUser('Intruder Return Co');

        $this->actingAs($intruder)
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED')
            ->assertJsonPath('message', 'Order not found.')
            ->assertJsonMissing(['data' => ['id' => $order->id]]);

        $this->assertSame(OrderStatus::DISPATCHED, $order->fresh()->status);
        $this->assertNull($order->fresh()->returned_at);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
    }

    public function test_non_dispatched_orders_cannot_be_returned(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $packing = $this->moveToPackaging($context);
        $trackingNumber = (string) $packing->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertSame(OrderStatus::PACKING, $packing->fresh()->status);

        $dispatched = $this->moveToDispatched($this->makeConfirmedOrderContext(
            stockQuantity: 5,
            orderQuantity: 1,
            supplier: $context['supplier'],
        ));
        app(OrderStatusService::class)->transition(
            $dispatched,
            OrderStatus::DELIVERED,
            $context['supplier']->id,
            null,
            'Delivered before return attempt',
        );
        $deliveredTracking = (string) $dispatched->fresh('shipment')->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $deliveredTracking,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertSame(OrderStatus::DELIVERED, $dispatched->fresh()->status);
        $this->assertNull($dispatched->fresh()->returned_at);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $dispatched->id)->count());
    }

    public function test_cancelled_order_cannot_be_returned(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);
        $trackingNumber = (string) $order->shipment->tracking_number;

        app(OrderStatusService::class)->transition(
            $order,
            OrderStatus::CANCELLED,
            $context['supplier']->id,
            null,
            'Cancelled before return',
        );

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
    }

    public function test_repeated_scan_does_not_duplicate_history_or_restock(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $variantId = (int) $context['variant']->id;
        $stockService = app(StockService::class);
        $fixedAt = Carbon::parse('2026-10-09 11:00:00');
        Carbon::setTestNow($fixedAt);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertOk();

        $returned = $order->fresh();
        $originalReturnedAt = $returned->returned_at?->toDateTimeString();
        $historyCount = OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('to_status', OrderStatus::RETURNED->value)
            ->count();

        $this->assertSame(1, $historyCount);
        $this->assertNotNull($originalReturnedAt);
        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));

        Carbon::setTestNow($fixedAt->copy()->addHours(3));

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::RETURNED, $fresh->status);
        $this->assertSame($originalReturnedAt, $fresh->returned_at?->toDateTimeString());
        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('to_status', OrderStatus::RETURNED->value)
                ->count()
        );
        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::RETURNED->value,
            'reason' => SupplierReturnScanService::HISTORY_REASON,
        ]);
    }

    public function test_unauthorized_access_is_rejected(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;

        $this->postJson(route('orders.dispatch.scan-return'), [
            'tracking_number' => $trackingNumber,
        ])->assertUnauthorized();

        $this->allowPermissions(['orders.view']);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertForbidden();

        $this->assertSame(OrderStatus::DISPATCHED, $order->fresh()->status);
    }

    public function test_stale_status_after_delivery_rejects_return_scan(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;

        app(OrderStatusService::class)->transition(
            $order,
            OrderStatus::DELIVERED,
            $context['supplier']->id,
            null,
            'Concurrent delivery wins',
        );

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::DELIVERED, $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNull($fresh->returned_at);
        $this->assertSame(
            0,
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('to_status', OrderStatus::RETURNED->value)
                ->count()
        );
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
    }

    public function test_stock_restoration_and_order_transition_roll_back_together(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $variantId = (int) $context['variant']->id;
        $stockService = app(StockService::class);

        $availableBefore = $stockService->availableQuantityForVariant($variantId);

        $mock = Mockery::mock(StockService::class)->makePartial();
        $mock->shouldReceive('restoreConsumptionsForOrder')
            ->once()
            ->andThrow(new \RuntimeException('Forced restoration failure'));
        $this->app->instance(StockService::class, $mock);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOCK_RESTORATION_FAILED');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::DISPATCHED, $fresh->status);
        $this->assertNull($fresh->returned_at);
        $this->assertSame(
            0,
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('to_status', OrderStatus::RETURNED->value)
                ->count()
        );
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame($availableBefore, app(StockService::class)->availableQuantityForVariant($variantId));
    }

    public function test_concurrent_return_attempts_do_not_duplicate_restoration(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $service = app(SupplierReturnScanService::class);
        $supplierId = (int) $context['supplier']->id;

        $first = $service->markReturnedByTrackingNumber($supplierId, $trackingNumber, $supplierId);
        $this->assertTrue($first['stock_restored']);

        try {
            $service->markReturnedByTrackingNumber($supplierId, $trackingNumber, $supplierId);
            $this->fail('Second return scan should be rejected.');
        } catch (\Feeder\Core\Exceptions\SupplierFulfillmentException $exception) {
            $this->assertSame('ORDER_NOT_DISPATCHED', $exception->errorCode);
        }

        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('to_status', OrderStatus::RETURNED->value)
                ->count()
        );
        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
    }

    public function test_concurrent_delivery_and_return_cannot_both_succeed(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $statusService = app(OrderStatusService::class);
        $returnService = app(SupplierReturnScanService::class);
        $supplierId = (int) $context['supplier']->id;

        // Simulate delivery winning the locked race before return evaluates status.
        DB::transaction(function () use ($order, $statusService, $supplierId): void {
            $locked = \Feeder\Core\Models\Order::query()->lockForUpdate()->findOrFail($order->id);
            $statusService->transition(
                $locked,
                OrderStatus::DELIVERED,
                $supplierId,
                null,
                'Delivery race winner',
            );
        });

        try {
            $returnService->markReturnedByTrackingNumber($supplierId, $trackingNumber, $supplierId);
            $this->fail('Return must not succeed after delivery.');
        } catch (\Feeder\Core\Exceptions\SupplierFulfillmentException $exception) {
            $this->assertSame('ORDER_NOT_DISPATCHED', $exception->errorCode);
        }

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::DELIVERED, $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNull($fresh->returned_at);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
    }

    public function test_scan_auto_classifies_return_reason_from_order_flags(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => false,
            'duplicate_warning_overridden' => true,
        ])->save();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => (string) $order->shipment->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('data.return_reason', 'DUPLICATE')
            ->assertJsonPath('data.stock_restored', true)
            ->assertJsonPath('data.is_damaged', false);

        $this->assertSame('DUPLICATE', (string) $order->fresh()->return_reason->value);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::RETURNED->value,
            'reason' => SupplierReturnScanService::HISTORY_REASON,
        ]);
    }

    public function test_normal_scan_with_is_damaged_false_restores_stock_once(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $variantId = (int) $context['variant']->id;
        $stockService = app(StockService::class);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
                'is_damaged' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_damaged', false)
            ->assertJsonPath('data.stock_restored', true);

        $this->assertFalse((bool) $order->fresh()->return_is_damaged);
        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
                'is_damaged' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));
    }

    public function test_damaged_scan_marks_returned_without_restoring_stock(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;
        $variantId = (int) $context['variant']->id;
        $stockService = app(StockService::class);

        $availableBefore = $stockService->availableQuantityForVariant($variantId);
        $this->assertSame(3, $availableBefore);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
                'is_damaged' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Order marked as returned as damaged. Stock was not restored.')
            ->assertJsonPath('data.status', OrderStatus::RETURNED->value)
            ->assertJsonPath('data.is_damaged', true)
            ->assertJsonPath('data.stock_restored', false)
            ->assertJsonPath('data.restored_items', []);

        $fresh = $order->fresh(['fulfillment']);
        $this->assertSame(OrderStatus::RETURNED, $fresh->status);
        $this->assertTrue((bool) $fresh->return_is_damaged);
        $this->assertNotNull($fresh->returned_at);
        $this->assertSame(SupplierFulfillmentStatus::DISPATCHED, $fresh->fulfillment?->status);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame($availableBefore, $stockService->availableQuantityForVariant($variantId));
        $this->assertSame(2, $stockService->consumedQuantityForVariant($variantId));
        $this->assertSame(0, $stockService->restoredQuantityForVariant($variantId));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::RETURNED->value,
            'reason' => SupplierReturnScanService::HISTORY_REASON_DAMAGED,
        ]);

        // Rescan must not restock a damaged return.
        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
                'is_damaged' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertTrue((bool) $order->fresh()->return_is_damaged);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame($availableBefore, $stockService->availableQuantityForVariant($variantId));
    }

    public function test_omitted_is_damaged_defaults_to_normal_restock(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => (string) $order->shipment->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_damaged', false)
            ->assertJsonPath('data.stock_restored', true);

        $this->assertFalse((bool) $order->fresh()->return_is_damaged);
        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
    }

    public function test_invalid_is_damaged_values_are_rejected(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $trackingNumber = (string) $order->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $trackingNumber,
                'is_damaged' => 'not-a-boolean',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_damaged']);

        $this->assertSame(OrderStatus::DISPATCHED, $order->fresh()->status);
        $this->assertSame(0, StockRestoration::query()->where('order_id', $order->id)->count());
    }
}
