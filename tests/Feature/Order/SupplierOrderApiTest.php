<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\OrderType;
use Feeder\Core\Enums\StockReservationStatus;
use Feeder\Core\Enums\SupplierFulfillmentEventType;
use Feeder\Core\Enums\SupplierFulfillmentStatus;
use Feeder\Core\Models\OrderFulfillmentEvent;
use Feeder\Core\Models\StockReservation;
use Feeder\Core\Services\StockService;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierOrderApiTest extends TestCase
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
            'orders.send-to-print',
            'orders.print',
            'orders.send-to-packing',
            'orders.pack',
            'orders.cancel',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_supplier_cannot_access_another_suppliers_order(): void
    {
        $ownerContext = $this->makeConfirmedOrderContext();
        $intruder = $this->makeSupplierUser('Intruder Supplier');

        $this->actingAs($intruder)
            ->postJson(route('orders.cancel', $ownerContext['order']), ['reason' => 'hack'])
            ->assertNotFound()
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED');

        $this->actingAs($intruder)
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$ownerContext['order']->id],
            ])
            ->assertNotFound()
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED');
    }

    public function test_fresh_lists_confirmed_new_orders_for_authenticated_supplier_only(): void
    {
        $context = $this->makeConfirmedOrderContext();
        $exchange = $this->makeConfirmedOrderContext(
            stockQuantity: 5,
            orderQuantity: 1,
            orderType: OrderType::EXCHANGE,
            supplier: $context['supplier'],
        );
        $other = $this->makeConfirmedOrderContext();

        $response = $this->actingAs($context['supplier'])
            ->getJson(route('orders.fresh'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($context['order']->id));
        $this->assertFalse($ids->contains($exchange['order']->id));
        $this->assertFalse($ids->contains($other['order']->id));
    }

    public function test_send_to_print_moves_fresh_order_and_reserves_stock(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 10, orderQuantity: 2);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$context['order']->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.fulfillment_status', SupplierFulfillmentStatus::PRINT->value);

        $this->assertSame(2, app(StockService::class)->reservedQuantityForVariant($context['variant']->id));
        $this->assertDatabaseHas('stock_reservations', [
            'order_id' => $context['order']->id,
            'status' => StockReservationStatus::ACTIVE->value,
            'quantity' => 2,
        ]);
    }

    public function test_send_to_print_count_mode_is_not_capped_at_25(): void
    {
        $context = $this->makeConfirmedOrderContext();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'count',
                'count' => 26,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $context['order']->id)
            ->assertJsonPath('data.0.fulfillment_status', SupplierFulfillmentStatus::PRINT->value);
    }

    public function test_send_to_print_rolls_back_entire_batch_on_insufficient_stock(): void
    {
        $supplier = $this->makeSupplierUser();
        $reseller = $this->makeResellerUser();
        $this->assignSupplierToReseller($reseller, $supplier);
        $catalog = $this->makeSupplierProductVariant($supplier);
        $this->seedStock($supplier, $catalog['product']->id, $catalog['variant']->id, 3);

        $orderA = $this->createAndConfirmOrder($reseller, $supplier, $catalog['variant']->id, 2);
        $orderB = $this->createAndConfirmOrder($reseller, $supplier, $catalog['variant']->id, 2);

        $this->actingAs($supplier)
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$orderA->id, $orderB->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK');

        $this->assertDatabaseMissing('stock_reservations', ['order_id' => $orderA->id]);
        $this->assertDatabaseMissing('stock_reservations', ['order_id' => $orderB->id]);
        $this->assertDatabaseMissing('order_fulfillments', ['order_id' => $orderA->id]);
        $this->assertDatabaseMissing('order_fulfillments', ['order_id' => $orderB->id]);
    }

    public function test_duplicate_send_to_print_is_rejected(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->moveToPrint($context);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$context['order']->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_FRESH');
    }

    public function test_print_data_requires_shipment_and_records_printed_event(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->moveToPrint($context);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$context['order']->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_PRINTABLE');

        $this->ensurePrintableShipment($context['order']->fresh());

        $response = $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$context['order']->id],
            ])
            ->assertOk();

        $response->assertJsonPath('data.0.order.order_number', $context['order']->order_number);
        $response->assertJsonPath('data.0.items.0.unit_selling_price', '250.00');
        $this->assertNotEmpty($response->json('data.0.courier.tracking_number'));

        $this->assertTrue(
            OrderFulfillmentEvent::query()
                ->where('order_id', $context['order']->id)
                ->where('event_type', SupplierFulfillmentEventType::PRINTED->value)
                ->exists()
        );

        $this->assertSame(
            SupplierFulfillmentStatus::PRINT,
            $context['order']->fresh('fulfillment')->fulfillment->status
        );
        $this->assertSame(
            \Feeder\Core\Enums\OrderStatus::PRINTED,
            $context['order']->fresh()->status
        );
    }

    public function test_send_to_packing_requires_printed_and_keeps_reservation_active(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), [
                'order_ids' => [$order->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_PRINTED');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), ['order_ids' => [$order->id]])
            ->assertOk();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), [
                'order_ids' => [$order->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.fulfillment_status', SupplierFulfillmentStatus::PACKAGING->value);

        $reservation = StockReservation::query()->where('order_id', $order->id)->first();
        $this->assertSame(StockReservationStatus::ACTIVE, $reservation->status);
    }

    public function test_send_to_packing_rejects_batch_over_24(): void
    {
        $this->assertSame(24, \Feeder\Core\Services\Order\SupplierFulfillmentBatchService::MAX_BATCH_SIZE);

        $context = $this->makeConfirmedOrderContext();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), [
                'order_ids' => range(1, 25),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BATCH_LIMIT_EXCEEDED');

        $allowed = $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), [
                'order_ids' => range(1, 24),
            ]);

        $this->assertNotSame('BATCH_LIMIT_EXCEEDED', $allowed->json('code'));
    }

    public function test_cancellation_rules_by_fulfillment_state(): void
    {
        $fresh = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->actingAs($fresh['supplier'])
            ->postJson(route('orders.cancel', $fresh['order']))
            ->assertOk();

        $oos = $this->makeConfirmedOrderContext(stockQuantity: 0, orderQuantity: 1);
        app(\Feeder\Core\Services\Order\SupplierFulfillmentService::class)
            ->markOutOfStock($oos['order'], $oos['supplier']->id);
        $this->actingAs($oos['supplier'])
            ->postJson(route('orders.cancel', $oos['order']))
            ->assertOk();

        $print = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $this->moveToPrint($print);
        $this->actingAs($print['supplier'])
            ->postJson(route('orders.cancel', $print['order']))
            ->assertOk();
        $this->assertSame(
            StockReservationStatus::RELEASED,
            StockReservation::query()->where('order_id', $print['order']->id)->first()->status
        );

        $packaging = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->moveToPackaging($packaging);
        $this->actingAs($packaging['supplier'])
            ->postJson(route('orders.cancel', $packaging['order']))
            ->assertOk();
        $this->assertSame(
            \Feeder\Core\Enums\OrderStatus::CANCELLED,
            $packaging['order']->fresh()->status
        );

        $dispatched = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($dispatched);
        $barcode = $order->items->first()->variant->barcode;
        $this->actingAs($dispatched['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertOk();
        $this->actingAs($dispatched['supplier'])
            ->postJson(route('orders.packaging.complete'), ['order_id' => $order->id])
            ->assertOk();
        $this->actingAs($dispatched['supplier'])
            ->postJson(route('orders.cancel', $order))
            ->assertStatus(422);
    }

    public function test_packaging_scan_and_complete_flow(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToPackaging($context);
        $barcode = $order->items->first()->variant->barcode;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $order->order_number,
            ])
            ->assertOk()
            ->assertJsonPath('data.fulfillment_status', SupplierFulfillmentStatus::PACKAGING->value)
            ->assertJsonPath('data.items.0.remaining_quantity', 2);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => 'WRONG-BARCODE',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'WRONG_PRODUCT_BARCODE');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.packed_quantity', 1)
            ->assertJsonPath('data.items.0.remaining_quantity', 1);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.complete'), ['order_id' => $order->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PACKAGE_INCOMPLETE');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.packed_quantity', 2);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OVER_SCAN');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.complete'), ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.fulfillment_status', SupplierFulfillmentStatus::DISPATCHED->value);

        $this->assertSame(
            \Feeder\Core\Enums\OrderStatus::DISPATCHED,
            $order->fresh()->status
        );

        $this->assertSame(2, app(StockService::class)->consumedQuantityForVariant($context['variant']->id));
        $this->assertSame(0, app(StockService::class)->reservedQuantityForVariant($context['variant']->id));

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.complete'), ['order_id' => $order->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ALREADY_DISPATCHED');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $order->order_number,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ALREADY_DISPATCHED');
    }

    public function test_out_of_stock_lists_insufficient_stock_orders_excluding_them_from_fresh(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 0, orderQuantity: 1);

        $fresh = $this->actingAs($context['supplier'])
            ->getJson(route('orders.fresh'))
            ->assertOk();

        $this->assertFalse(
            collect($fresh->json('data'))->pluck('id')->contains($context['order']->id),
            'Insufficient-stock order must not appear in Fresh'
        );

        $oos = $this->actingAs($context['supplier'])
            ->getJson(route('orders.out-of-stock'))
            ->assertOk();

        $this->assertTrue(
            collect($oos->json('data'))->pluck('id')->contains($context['order']->id),
            'Insufficient-stock Fresh-eligible order must appear in Out of Stock'
        );
        $this->assertSame(1, $oos->json('meta.mode_counts.out_of_stock'));
        $this->assertSame(0, $oos->json('meta.mode_counts.fresh'));
    }

    public function test_out_of_stock_visibility_one_item_insufficient_shows_in_oos_only(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplierToReseller($reseller, $supplier);

        $available = $this->makeSupplierProductVariant($supplier);
        $insufficient = $this->makeSupplierProductVariant($supplier);
        $this->seedStock($supplier, $available['product']->id, $available['variant']->id, 5);
        // insufficient variant: no stock

        $order = $this->createAndConfirmOrderWithItems($reseller, $supplier, [
            ['product_variant_id' => $available['variant']->id, 'quantity' => 1],
            ['product_variant_id' => $insufficient['variant']->id, 'quantity' => 1],
        ]);

        $freshIds = collect(
            $this->actingAs($supplier)->getJson(route('orders.fresh'))->assertOk()->json('data')
        )->pluck('id');
        $oosIds = collect(
            $this->actingAs($supplier)->getJson(route('orders.out-of-stock'))->assertOk()->json('data')
        )->pluck('id');

        $this->assertFalse($freshIds->contains($order->id));
        $this->assertTrue($oosIds->contains($order->id));
    }

    public function test_out_of_stock_visibility_all_items_available_excludes_from_oos(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplierToReseller($reseller, $supplier);

        $productA = $this->makeSupplierProductVariant($supplier);
        $productB = $this->makeSupplierProductVariant($supplier);
        $this->seedStock($supplier, $productA['product']->id, $productA['variant']->id, 5);
        $this->seedStock($supplier, $productB['product']->id, $productB['variant']->id, 5);

        $order = $this->createAndConfirmOrderWithItems($reseller, $supplier, [
            ['product_variant_id' => $productA['variant']->id, 'quantity' => 1],
            ['product_variant_id' => $productB['variant']->id, 'quantity' => 2],
        ]);

        $freshIds = collect(
            $this->actingAs($supplier)->getJson(route('orders.fresh'))->assertOk()->json('data')
        )->pluck('id');
        $oosIds = collect(
            $this->actingAs($supplier)->getJson(route('orders.out-of-stock'))->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($freshIds->contains($order->id));
        $this->assertFalse($oosIds->contains($order->id));
    }

    public function test_out_of_stock_visibility_moves_to_fresh_when_stock_becomes_available(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplierToReseller($reseller, $supplier);

        $available = $this->makeSupplierProductVariant($supplier);
        $insufficient = $this->makeSupplierProductVariant($supplier);
        $this->seedStock($supplier, $available['product']->id, $available['variant']->id, 5);

        $order = $this->createAndConfirmOrderWithItems($reseller, $supplier, [
            ['product_variant_id' => $available['variant']->id, 'quantity' => 1],
            ['product_variant_id' => $insufficient['variant']->id, 'quantity' => 2],
        ]);

        $this->assertFalse(
            collect($this->actingAs($supplier)->getJson(route('orders.fresh'))->json('data'))
                ->pluck('id')
                ->contains($order->id)
        );
        $this->assertTrue(
            collect($this->actingAs($supplier)->getJson(route('orders.out-of-stock'))->json('data'))
                ->pluck('id')
                ->contains($order->id)
        );

        $this->seedStock($supplier, $insufficient['product']->id, $insufficient['variant']->id, 2);

        $this->assertTrue(
            collect($this->actingAs($supplier)->getJson(route('orders.fresh'))->json('data'))
                ->pluck('id')
                ->contains($order->id)
        );
        $this->assertFalse(
            collect($this->actingAs($supplier)->getJson(route('orders.out-of-stock'))->json('data'))
                ->pluck('id')
                ->contains($order->id)
        );
    }

    public function test_out_of_stock_visibility_multiple_insufficient_items(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplierToReseller($reseller, $supplier);

        $a = $this->makeSupplierProductVariant($supplier);
        $b = $this->makeSupplierProductVariant($supplier);
        $c = $this->makeSupplierProductVariant($supplier);
        $this->seedStock($supplier, $c['product']->id, $c['variant']->id, 5);

        $order = $this->createAndConfirmOrderWithItems($reseller, $supplier, [
            ['product_variant_id' => $a['variant']->id, 'quantity' => 1],
            ['product_variant_id' => $b['variant']->id, 'quantity' => 2],
            ['product_variant_id' => $c['variant']->id, 'quantity' => 1],
        ]);

        $freshIds = collect(
            $this->actingAs($supplier)->getJson(route('orders.fresh'))->assertOk()->json('data')
        )->pluck('id');
        $oosIds = collect(
            $this->actingAs($supplier)->getJson(route('orders.out-of-stock'))->assertOk()->json('data')
        )->pluck('id');

        $this->assertFalse($freshIds->contains($order->id));
        $this->assertTrue($oosIds->contains($order->id));
    }

    public function test_out_of_stock_visibility_exact_stock_is_not_oos(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 2, orderQuantity: 2);

        $freshIds = collect(
            $this->actingAs($context['supplier'])->getJson(route('orders.fresh'))->assertOk()->json('data')
        )->pluck('id');
        $oosIds = collect(
            $this->actingAs($context['supplier'])->getJson(route('orders.out-of-stock'))->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($freshIds->contains($context['order']->id));
        $this->assertFalse($oosIds->contains($context['order']->id));
    }

    public function test_out_of_stock_visibility_isolates_suppliers(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 0, orderQuantity: 1);
        $other = $this->makeConfirmedOrderContext(stockQuantity: 0, orderQuantity: 1);

        $freshIds = collect(
            $this->actingAs($owner['supplier'])->getJson(route('orders.fresh'))->assertOk()->json('data')
        )->pluck('id');
        $oosIds = collect(
            $this->actingAs($owner['supplier'])->getJson(route('orders.out-of-stock'))->assertOk()->json('data')
        )->pluck('id');

        $this->assertFalse($freshIds->contains($owner['order']->id));
        $this->assertTrue($oosIds->contains($owner['order']->id));
        $this->assertFalse($freshIds->contains($other['order']->id));
        $this->assertFalse($oosIds->contains($other['order']->id));
    }

    public function test_out_of_stock_visibility_does_not_break_send_to_print_when_stock_exists(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);

        $this->assertFalse(
            collect($this->actingAs($context['supplier'])->getJson(route('orders.out-of-stock'))->json('data'))
                ->pluck('id')
                ->contains($context['order']->id)
        );

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$context['order']->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.fulfillment_status', SupplierFulfillmentStatus::PRINT->value);

        $this->assertFalse(
            collect($this->actingAs($context['supplier'])->getJson(route('orders.fresh'))->json('data'))
                ->pluck('id')
                ->contains($context['order']->id)
        );
        $this->assertFalse(
            collect($this->actingAs($context['supplier'])->getJson(route('orders.out-of-stock'))->json('data'))
                ->pluck('id')
                ->contains($context['order']->id)
        );
    }

    public function test_out_of_stock_product_filter_matches_insufficient_item(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplierToReseller($reseller, $supplier);

        $available = $this->makeSupplierProductVariant($supplier);
        $insufficient = $this->makeSupplierProductVariant($supplier);
        $this->seedStock($supplier, $available['product']->id, $available['variant']->id, 5);

        $order = $this->createAndConfirmOrderWithItems($reseller, $supplier, [
            ['product_variant_id' => $available['variant']->id, 'quantity' => 1],
            ['product_variant_id' => $insufficient['variant']->id, 'quantity' => 1],
        ]);

        $filtered = $this->actingAs($supplier)
            ->getJson(route('orders.out-of-stock', [
                'product_id' => $insufficient['product']->id,
            ]))
            ->assertOk();

        $this->assertTrue(collect($filtered->json('data'))->pluck('id')->contains($order->id));

        $otherProduct = $this->makeSupplierProductVariant($supplier);
        $miss = $this->actingAs($supplier)
            ->getJson(route('orders.out-of-stock', [
                'product_id' => $otherProduct['product']->id,
            ]))
            ->assertOk();

        $this->assertFalse(collect($miss->json('data'))->pluck('id')->contains($order->id));
    }

    public function test_permission_middleware_blocks_unauthorized_actions(): void
    {
        $this->allowPermissions(['orders.view']);
        $context = $this->makeConfirmedOrderContext();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$context['order']->id],
            ])
            ->assertForbidden();
    }
}
