<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\SupplierFulfillmentStatus;
use Feeder\Core\Support\PackagingBarcode;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierPackagingOrdersUiTest extends TestCase
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
            'orders.pack',
            'orders.send-to-print',
            'orders.print',
            'orders.send-to-packing',
            'orders.cancel',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_packaging_page_loads_for_authorized_supplier(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.packaging'))
            ->assertOk()
            ->assertSee('Packing Orders')
            ->assertSee('No orders waiting for packaging.', false)
            ->assertSee('Scan Waybill / Order ID', false)
            ->assertSee('Scan waybill or order ID and press Enter', false)
            ->assertSee('id="supplierPackagingOrdersPage"', false)
            ->assertSee('id="packagingOrderScanInput"', false)
            ->assertSee('id="packagingWorkspaceModal"', false)
            ->assertSee('data-list-url="'.route('orders.packaging.index').'"', false)
            ->assertSee('data-scan-order-url="'.route('orders.packaging.scan-order').'"', false)
            ->assertSee('data-scan-product-url="'.route('orders.packaging.scan-product').'"', false)
            ->assertSee('data-complete-url="'.route('orders.packaging.complete').'"', false)
            ->assertSee('data-reprint-url="'.route('orders.reprint-data').'"', false)
            ->assertSee('data-can-print="1"', false)
            ->assertSee('data-complete-barcode="'.PackagingBarcode::COMPLETE_PACKAGE.'"', false)
            ->assertSee(PackagingBarcode::COMPLETE_PACKAGE)
            ->assertSee('id="packagingCompleteBarcodeDownload"', false)
            ->assertSee('Download barcode', false)
            ->assertSee('supplier-code128.js', false)
            ->assertSee('supplier-invoice-print.js', false)
            ->assertSee('id="supplierInvoicePrintSurface"', false)
            ->assertDontSee('coming soon', false);
    }

    public function test_packaging_actions_hidden_without_pack_permission(): void
    {
        $this->allowPermissions(['orders.view']);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.packaging'))
            ->assertOk()
            ->assertSee('Packing Orders')
            ->assertSee('data-can-pack="0"', false)
            ->assertSee('data-can-print="0"', false)
            ->assertDontSee('id="packagingOrderScanInput"', false)
            ->assertDontSee('id="packagingWorkspaceModal"', false)
            ->assertDontSee('id="packagingCompleteBtn"', false)
            ->assertDontSee('supplier-invoice-print.js', false)
            ->assertSee('orders.pack', false);
    }

    public function test_packaging_page_shows_reprint_when_print_permission_without_pack(): void
    {
        $this->allowPermissions(['orders.view', 'orders.print']);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.packaging'))
            ->assertOk()
            ->assertSee('data-can-pack="0"', false)
            ->assertSee('data-can-print="1"', false)
            ->assertSee('data-reprint-url="'.route('orders.reprint-data').'"', false)
            ->assertSee('supplier-invoice-print.js', false)
            ->assertDontSee('id="packagingOrderScanInput"', false)
            ->assertDontSee('id="packagingWorkspaceModal"', false);
    }

    public function test_reprint_data_returns_invoice_payload_for_packaging_order(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);

        $response = $this->actingAs($context['supplier'])
            ->postJson(route('orders.reprint-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.order.order_number', $order->order_number);

        $this->assertSame(
            SupplierFulfillmentStatus::PACKAGING,
            $order->fresh('fulfillment')->fulfillment->status
        );
        $this->assertNotEmpty($response->json('data.0.courier.tracking_number'));
    }

    public function test_reprint_data_rejects_print_queue_orders(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.reprint-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_PACKAGING');
    }

    public function test_reprint_data_requires_print_permission(): void
    {
        $this->allowPermissions(['orders.view', 'orders.pack']);
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.reprint-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertForbidden();
    }

    public function test_packaging_queue_lists_packaging_orders_with_progress_and_isolation(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToPackaging($owner);

        $other = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->moveToPackaging($other);

        $response = $this->actingAs($owner['supplier'])
            ->getJson(route('orders.packaging.index', [
                'search' => $order->order_number,
                'courier_id' => $order->fresh('shipment')->shipment->courier_id,
                'product_id' => $owner['product']->id,
            ]))
            ->assertOk();

        $response->assertJsonPath('data.0.id', $order->id);
        $response->assertJsonPath('data.0.fulfillment_status', SupplierFulfillmentStatus::PACKAGING->value);
        $response->assertJsonPath('data.0.ordered_units', 2);
        $response->assertJsonPath('data.0.packed_units', 0);
        $response->assertJsonPath('data.0.remaining_units', 2);
        $this->assertNotEmpty($response->json('data.0.invoice_number'));
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertArrayHasKey('products', $response->json('meta.filter_options'));
        $this->assertArrayHasKey('couriers', $response->json('meta.filter_options'));

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($other['order']->id));
    }

    public function test_packaging_queue_paginates_and_empty_state(): void
    {
        $supplier = $this->makeSupplierUser();

        $empty = $this->actingAs($supplier)
            ->getJson(route('orders.packaging.index'))
            ->assertOk();

        $this->assertSame(0, $empty->json('meta.total'));
        $this->assertSame([], $empty->json('data'));

        $first = $this->makeConfirmedOrderContext(stockQuantity: 10, orderQuantity: 1);
        $this->moveToPackaging($first);

        $response = $this->actingAs($first['supplier'])
            ->getJson(route('orders.packaging.index', ['page' => 1, 'per_page' => 1]))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(1, $response->json('meta.per_page'));
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_order_scan_opens_valid_packaging_order_and_rejects_invalid(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToPackaging($context);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $order->order_number,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.fulfillment_status', SupplierFulfillmentStatus::PACKAGING->value)
            ->assertJsonPath('data.items.0.ordered_quantity', 2)
            ->assertJsonPath('data.items.0.packed_quantity', 0)
            ->assertJsonPath('data.ordered_units', 2);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => 'DOES-NOT-EXIST',
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED');

        $fresh = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->actingAs($fresh['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $fresh['order']->order_number,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_PACKAGING');
    }

    public function test_order_scan_resolves_waybill_tracking_number(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);
        $trackingNumber = (string) $order->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $trackingNumber,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.fulfillment_status', SupplierFulfillmentStatus::PACKAGING->value)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_order_scan_still_resolves_uuid_and_legacy_order_number(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);
        $order->forceFill(['legacy_order_number' => 'ORD-LEGACY-PACK-001'])->save();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => (string) $order->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => 'ORD-LEGACY-PACK-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_order_scan_waybill_rejects_other_supplier_without_leak(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($owner);
        $trackingNumber = (string) $order->shipment->tracking_number;

        $intruder = $this->makeSupplierUser('Intruder Pack Co');

        $this->actingAs($intruder)
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $trackingNumber,
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED')
            ->assertJsonPath('message', 'Order not found.')
            ->assertJsonMissing(['data' => ['id' => $order->id]]);
    }

    public function test_order_scan_waybill_preserves_non_packaging_protection(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);
        $trackingNumber = (string) $order->fresh('shipment')->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $trackingNumber,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_PACKAGING');
    }

    public function test_order_scan_rejects_ambiguous_waybill_without_choosing(): void
    {
        $first = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $orderA = $this->moveToPackaging($first);

        $second = $this->makeConfirmedOrderContext(
            stockQuantity: 5,
            orderQuantity: 1,
            supplier: $first['supplier'],
        );
        $orderB = $this->moveToPackaging($second);

        $sharedWaybill = 'WB-AMBIGUOUS-PACK-001';
        $orderA->shipment->forceFill(['tracking_number' => $sharedWaybill])->save();
        $orderB->shipment->forceFill(['tracking_number' => $sharedWaybill])->save();

        $this->actingAs($first['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $sharedWaybill,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AMBIGUOUS_TRACKING_NUMBER')
            ->assertJsonPath(
                'message',
                'Multiple orders found for this waybill. Please scan the correct order identifier or contact support.',
            )
            ->assertJsonMissingPath('data.id');
    }

    public function test_order_scan_accepts_long_waybill_within_column_limit(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);
        $longWaybill = str_repeat('W', 128);
        $order->shipment->forceFill(['tracking_number' => $longWaybill])->save();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $longWaybill,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_product_scan_updates_quantities_and_handles_wrong_and_over_scan(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);
        $barcode = $order->items->first()->variant->barcode;

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
            ->assertJsonPath('data.items.0.remaining_quantity', 0)
            ->assertJsonPath('data.packed_units', 1)
            ->assertJsonPath('data.remaining_units', 0);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OVER_SCAN');
    }

    public function test_incomplete_package_cannot_complete_and_success_dispatches(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToPackaging($context);
        $barcode = $order->items->first()->variant->barcode;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.complete'), ['order_id' => $order->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PACKAGE_INCOMPLETE');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertOk();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => $barcode,
            ])
            ->assertOk();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.complete'), ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.fulfillment_status', SupplierFulfillmentStatus::DISPATCHED->value);

        $queue = $this->actingAs($context['supplier'])
            ->getJson(route('orders.packaging.index'))
            ->assertOk();

        $ids = collect($queue->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($order->id));

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $order->order_number,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ALREADY_DISPATCHED');
    }

    public function test_pack_permission_required_for_scan_and_complete_apis(): void
    {
        $this->allowPermissions(['orders.view']);
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPackaging($context);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-order'), [
                'order_reference' => $order->order_number,
            ])
            ->assertForbidden();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.scan-product'), [
                'order_id' => $order->id,
                'product_barcode' => 'ANY',
            ])
            ->assertForbidden();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.packaging.complete'), [
                'order_id' => $order->id,
            ])
            ->assertForbidden();
    }

    public function test_complete_package_barcode_constant_is_stable(): void
    {
        $this->assertSame('FCP', PackagingBarcode::COMPLETE_PACKAGE);
        $this->assertTrue(PackagingBarcode::matches('FCP'));
        $this->assertTrue(PackagingBarcode::matches('fcp'));
        $this->assertTrue(PackagingBarcode::matches('FEEDER-COMPLETE-PACKAGE'));
        $this->assertFalse(PackagingBarcode::matches('OTHER'));
    }
}
