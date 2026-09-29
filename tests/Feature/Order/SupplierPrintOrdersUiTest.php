<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\StockReservationStatus;
use Feeder\Core\Enums\SupplierFulfillmentStatus;
use Feeder\Core\Enums\OrderPaymentMethod;
use Feeder\Core\Enums\OrderPaymentReviewStatus;
use Feeder\Core\Models\OrderPaymentSubmission;
use Feeder\Core\Models\StockReservation;
use Feeder\Core\Services\Order\SupplierFulfillmentBatchService;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierPrintOrdersUiTest extends TestCase
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
            'orders.print',
            'orders.send-to-packing',
            'orders.send-to-print',
            'orders.cancel',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_print_orders_page_loads_for_authorized_supplier(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.print'))
            ->assertOk()
            ->assertSee('Print Orders')
            ->assertSee('No orders waiting for printing.', false)
            ->assertSee('data-list-url="'.route('orders.print.index').'"', false)
            ->assertSee('data-print-data-url="'.route('orders.print-data').'"', false)
            ->assertSee('data-send-to-packing-url="'.route('orders.send-to-packing').'"', false)
            ->assertSee('data-max-batch="'.SupplierFulfillmentBatchService::MAX_BATCH_SIZE.'"', false)
            ->assertSee('(max '.SupplierFulfillmentBatchService::MAX_BATCH_SIZE.')', false)
            ->assertSee('Feeder Invoice ID', false)
            ->assertSee('>Reseller</th>', false)
            ->assertDontSee('>Feeder Order ID<', false)
            ->assertDontSee('>Invoice</th>', false)
            ->assertDontSee('>Fulfillment</th>', false)
            ->assertSee('id="printItemListBtn"', false)
            ->assertSee('id="sendToPackingBtn"', false)
            ->assertSee('id="printOrdersSelectAll"', false)
            ->assertSee('supplier-code128.js', false)
            ->assertSee('@page {', false)
            ->assertSee('size: A5 portrait;', false)
            ->assertSee('page-break-after: always;', false)
            ->assertSee('inv-section-title', false)
            ->assertSee('COD AMOUNT', false)
            ->assertSee('Payment Collected', false)
            ->assertDontSee('coming soon', false);
    }

    public function test_print_selected_renders_invoices_in_current_window(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.print'))
            ->assertOk()
            ->assertSee('id="supplierInvoicePrintSurface"', false)
            ->assertSee('size: A5 portrait;', false)
            ->getContent();

        $this->assertStringContainsString('function showPrintSurface(', $content);
        $this->assertStringContainsString('function closePrintSurface(', $content);
        $this->assertStringContainsString('function collectSelectedOrderIds()', $content);
        $this->assertStringNotContainsString('invoiceBarcodeBlock(invoiceNumber,', $content);
        $this->assertStringContainsString('barcodeSvg(value', $content);
        $this->assertStringContainsString('prepareAndPrint(collectSelectedOrderIds())', $content);
        $this->assertStringContainsString('resolveSelectedOrdersForPrint', $content);
        $this->assertStringContainsString('function selectedOrdersAreAllPrinted()', $content);
        $this->assertStringContainsString('canSendPrintedSelection', $content);
        $this->assertStringContainsString('Select printed orders only', $content);
        $this->assertStringContainsString('Delivery Information', $content);
        $this->assertStringContainsString('barcode-container', $content);
        $this->assertStringContainsString('invoiceBarcodeBlock(courier.tracking_number', $content);
        $this->assertStringContainsString('Invoice Number', $content);
        $this->assertStringContainsString('Company Name', $content);
        $this->assertStringContainsString('inv-care-number', $content);
        $this->assertStringContainsString('function currentPageOrdersForItemList()', $content);
        $this->assertStringContainsString('currentPageOrdersForItemList()', $content);
        $this->assertStringContainsString("pageSize: 'A4'", $content);
        $this->assertStringContainsString('item-list-line', $content);
        $this->assertStringContainsString('item-list-rule', $content);
        $this->assertStringContainsString('(${escapeHtml(variantName)}) x ${escapeHtml(variant.quantity)}', $content);
        $this->assertStringContainsString('size: ${pageSize} portrait;', $content);
        $this->assertStringNotContainsString('fetchFilteredOrdersForItemList', $content);
        $this->assertStringNotContainsString('Barcode: ${escapeHtml(variant.barcode)}', $content);
        $this->assertStringNotContainsString('function openBlankPrintWindow()', $content);
        $this->assertStringNotContainsString('window.open(', $content);
    }

    public function test_reprint_invoice_script_omits_invoice_id_barcode_keeps_waybill(): void
    {
        $scriptPath = public_path('assets/js/supplier-invoice-print.js');
        $this->assertFileExists($scriptPath);

        $content = (string) file_get_contents($scriptPath);

        $this->assertStringContainsString('invoiceBarcodeBlock(courier.tracking_number', $content);
        $this->assertStringContainsString('Invoice Number', $content);
        $this->assertStringContainsString('font-size: 17px;', $content);
        $this->assertStringContainsString('font-size: 16px;', $content);
        $this->assertStringNotContainsString('invoiceBarcodeBlock(invoiceNumber', $content);
        $this->assertStringNotContainsString("className: 'is-invoice'", $content);
    }

    public function test_print_actions_hidden_without_permissions(): void
    {
        $this->allowPermissions(['orders.view']);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.print'))
            ->assertOk()
            ->assertSee('Print Orders')
            ->assertDontSee('id="printSelectedBtn"', false)
            ->assertDontSee('id="printItemListBtn"', false)
            ->assertDontSee('id="sendToPackingBtn"', false)
            ->assertDontSee('id="printOrdersSelectAll"', false)
            ->assertSee('data-can-print="0"', false)
            ->assertSee('data-can-send-to-packing="0"', false);
    }

    public function test_print_list_api_returns_print_orders_with_filters_and_isolation(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($owner);
        $this->ensurePrintableShipment($order);

        $other = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $this->moveToPrint($other);

        $response = $this->actingAs($owner['supplier'])
            ->getJson(route('orders.print.index', [
                'search' => $order->order_number,
                'courier_id' => $order->fresh('shipment')->shipment->courier_id,
                'product_id' => $owner['product']->id,
            ]))
            ->assertOk();

        $response->assertJsonPath('data.0.id', $order->id);
        $response->assertJsonPath('data.0.fulfillment_status', SupplierFulfillmentStatus::PRINT->value);
        $this->assertFalse((bool) $response->json('data.0.is_printed'));
        $this->assertTrue((bool) $response->json('data.0.is_print_ready'));
        $this->assertSame(
            $order->fresh('resellerCompany')->resellerCompany->name,
            $response->json('data.0.reseller_company_name')
        );
        $this->assertArrayHasKey('reseller_name', $response->json('data.0'));
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertArrayHasKey('products', $response->json('meta.filter_options'));
        $this->assertArrayHasKey('couriers', $response->json('meta.filter_options'));

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($other['order']->id));
    }

    public function test_print_list_api_paginates(): void
    {
        $first = $this->makeConfirmedOrderContext(stockQuantity: 10, orderQuantity: 1);
        $order = $this->moveToPrint($first);
        $this->ensurePrintableShipment($order);

        $response = $this->actingAs($first['supplier'])
            ->getJson(route('orders.print.index', ['page' => 1, 'per_page' => 1]))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(1, $response->json('meta.per_page'));
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_print_data_payload_contains_invoice_layout_fields(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToPrint($context);
        $shipment = $this->ensurePrintableShipment($order);
        $order = $order->fresh(['resellerCompany', 'supplier.company', 'address', 'items', 'shipment.city']);

        $response = $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertOk();

        $payload = $response->json('data.0');

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($order->supplier->company->name, $payload['supplier']['company_name']);
        $this->assertSame($order->resellerCompany->phone, $payload['customer_care']['reseller_company_phone']);
        $this->assertSame($order->resellerCompany->name, $payload['order']['reseller_company_name']);
        $this->assertNotEmpty($payload['order']['issued_date']);
        $this->assertNotEmpty($payload['order']['invoice_number']);
        $this->assertSame($order->order_number, $payload['order']['invoice_number']);
        $this->assertMatchesRegularExpression('/^\d{11}$/', $payload['order']['invoice_number']);
        $this->assertSame($order->order_number, $payload['order']['order_number']);
        $this->assertSame($shipment->tracking_number, $payload['courier']['tracking_number']);
        $this->assertNotEmpty($payload['courier']['label'] ?? $payload['courier']['name']);
        $this->assertSame('Colombo 03', $payload['courier']['city']);
        $this->assertSame('Colombo', $payload['courier']['district']);
        $this->assertSame($order->customer_name_snapshot ?? 'API Customer', $payload['customer']['name']);
        $this->assertNotEmpty($payload['customer']['address']);
        $this->assertNotEmpty($payload['customer']['contact_1']);
        $this->assertSame('350.00', $payload['charges']['courier_fee']);
        $this->assertSame('250.00', $payload['items'][0]['unit_selling_price']);
        $this->assertSame(2, $payload['items'][0]['quantity']);
        $this->assertFalse($payload['charges']['is_payment_collected']);
        $this->assertSame('COD', $payload['charges']['payment_status']);
        $this->assertNotNull($payload['charges']['cod_amount']);
        $this->assertSame((string) $order->customer_payable_amount, $payload['charges']['cod_amount']);

        $again = $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertOk();

        $this->assertSame(
            $payload['order']['invoice_number'],
            $again->json('data.0.order.invoice_number')
        );
    }

    public function test_print_data_returns_one_invoice_payload_per_selected_order(): void
    {
        $first = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $orderA = $this->moveToPrint($first);
        $this->ensurePrintableShipment($orderA);

        $orderB = $this->createAndConfirmOrder(
            $first['reseller'],
            $first['supplier'],
            $first['variant']->id,
            1,
        );
        $orderB = $this->moveToPrint([
            'order' => $orderB,
            'supplier' => $first['supplier'],
            'reseller' => $first['reseller'],
            'variant' => $first['variant'],
            'product' => $first['product'],
        ]);
        $this->ensurePrintableShipment($orderB);

        $response = $this->actingAs($first['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$orderA->id, $orderB->id],
            ])
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($orderA->order_number, $response->json('data.0.order.invoice_number'));
        $this->assertSame($orderB->order_number, $response->json('data.1.order.invoice_number'));
        $this->assertNotSame(
            $response->json('data.0.order.invoice_number'),
            $response->json('data.1.order.invoice_number')
        );
    }

    public function test_print_data_shows_payment_collected_for_approved_bank_transfer(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);

        OrderPaymentSubmission::query()->create([
            'order_id' => $order->id,
            'method' => OrderPaymentMethod::BANK_TRANSFER,
            'review_status' => OrderPaymentReviewStatus::APPROVED,
            'reference_number' => 'BT-REF-1001',
            'amount' => $order->customer_payable_amount,
            'submitted_by_user_id' => $context['reseller']->id,
            'submitted_by_company_id' => $context['reseller']->company_id,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $payload = $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertOk()
            ->json('data.0');

        $this->assertTrue($payload['charges']['is_payment_collected']);
        $this->assertSame('Payment Collected', $payload['charges']['payment_status']);
        $this->assertNull($payload['charges']['cod_amount']);
        $this->assertTrue($payload['order']['payment']['is_payment_collected']);
        $this->assertSame('Payment Collected', $payload['order']['payment']['label']);
    }

    public function test_print_data_rejects_foreign_supplier_order_ids(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($owner);
        $this->ensurePrintableShipment($order);

        $intruder = $this->makeSupplierUser('Intruder Print Co');

        $this->actingAs($intruder)
            ->postJson(route('orders.print-data'), [
                'order_ids' => [$order->id],
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUPPLIER_NOT_AUTHORIZED');
    }

    public function test_print_data_rejects_batch_over_max(): void
    {
        $this->assertSame(24, SupplierFulfillmentBatchService::MAX_BATCH_SIZE);

        $context = $this->makeConfirmedOrderContext();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => range(1, SupplierFulfillmentBatchService::MAX_BATCH_SIZE + 1),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BATCH_LIMIT_EXCEEDED');
    }

    public function test_send_to_packing_accepts_24_and_rejects_25(): void
    {
        $this->assertSame(24, SupplierFulfillmentBatchService::MAX_BATCH_SIZE);

        $context = $this->makeConfirmedOrderContext(stockQuantity: 30, orderQuantity: 1);
        $orderIds = [];

        $first = $this->moveToPrint($context);
        $this->ensurePrintableShipment($first);
        $orderIds[] = $first->id;

        for ($i = 1; $i < 24; $i++) {
            $order = $this->createAndConfirmOrder(
                $context['reseller'],
                $context['supplier'],
                $context['variant']->id,
                1,
            );
            $printed = $this->moveToPrint([
                'order' => $order,
                'supplier' => $context['supplier'],
                'reseller' => $context['reseller'],
                'variant' => $context['variant'],
                'product' => $context['product'],
            ]);
            $this->ensurePrintableShipment($printed);
            $orderIds[] = $printed->id;
        }

        $this->assertCount(24, $orderIds);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), [
                'order_ids' => $orderIds,
            ])
            ->assertOk()
            ->assertJsonCount(24, 'data');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), [
                'order_ids' => $orderIds,
            ])
            ->assertOk()
            ->assertJsonCount(24, 'data');

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), [
                'order_ids' => array_merge($orderIds, [999999]),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BATCH_LIMIT_EXCEEDED');
    }

    public function test_send_to_packing_from_print_workspace_rules(): void
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

        $list = $this->actingAs($context['supplier'])
            ->getJson(route('orders.print.index'))
            ->assertOk();

        $ids = collect($list->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($order->id));
    }

    public function test_print_and_send_to_packing_blocked_without_permission(): void
    {
        $this->allowPermissions(['orders.view']);
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.print-data'), ['order_ids' => [$order->id]])
            ->assertForbidden();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-packing'), ['order_ids' => [$order->id]])
            ->assertForbidden();
    }

    public function test_item_list_aggregation_uses_variant_quantities_from_list_payload(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 10, orderQuantity: 3);
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);

        $response = $this->actingAs($context['supplier'])
            ->getJson(route('orders.print.index'))
            ->assertOk();

        $items = $response->json('data.0.items');
        $this->assertNotEmpty($items);
        $this->assertSame(3, $items[0]['quantity']);
        $this->assertArrayHasKey('product_variant_id', $items[0]);
        $this->assertArrayHasKey('barcode', $items[0]);
        $this->assertArrayHasKey('variant', $items[0]);
    }
}
