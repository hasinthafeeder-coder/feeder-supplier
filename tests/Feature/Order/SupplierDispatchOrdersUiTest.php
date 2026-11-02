<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Models\StockRestoration;
use Feeder\Core\Services\StockService;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierDispatchOrdersUiTest extends TestCase
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
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_dispatch_orders_page_loads_with_inline_scanner_and_without_modal(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        $this->actingAs($context['supplier'])
            ->get(route('orders.ui.dispatch'))
            ->assertOk()
            ->assertSee('Dispatch Orders')
            ->assertSee('Manage dispatched orders and receive returned packages.')
            ->assertSee('id="supplierDispatchOrdersPage"', false)
            ->assertSee('data-list-url="'.route('orders.dispatch.index').'"', false)
            ->assertSee('data-scan-return-url="'.route('orders.dispatch.scan-return').'"', false)
            ->assertSee('id="dispatchReturnScanCard"', false)
            ->assertSee('Scan Returned Order')
            ->assertSee('Damaged Order — Do Not Restock')
            ->assertSee('id="dispatchReturnDamagedCheckbox"', false)
            ->assertSee('restores its consumed stock unless')
            ->assertSee('Scan waybill...')
            ->assertSee('id="dispatchReturnScanInput"', false)
            ->assertSee('is_damaged', false)
            ->assertDontSee('dispatchScanReturnedModal')
            ->assertDontSee('dispatchScanReturnedBtn')
            ->assertDontSee('Mark as RETURNED')
            ->assertDontSee('Step 3 — Confirm return')
            ->assertDontSee('id="dispatchReturnReasonSelect"', false)
            ->assertDontSee('Select return reason')
            ->assertDontSee('Duplicate Return')
            ->assertDontSee('Out-of-Office-Hours Return')
            ->assertSee('determined automatically from the order')
            ->assertDontSee('Kasun Perera')
            ->assertDontSee('Nimal Silva')
            ->assertDontSee('Ayesha Fernando')
            ->assertDontSee('mockOrders')
            ->assertDontSee('data-mock-orders', false)
            ->assertSee('Courier handover is recorded in Dispatch Summary')
            ->assertSee('does not change the order status')
            ->assertSee('Search order, customer, phone or waybill')
            ->assertSee('All Couriers')
            ->assertSee('Dispatched')
            ->assertSee('Returned Today')
            ->assertSee('value="DISPATCHED"', false)
            ->assertSee('value="RETURNED"', false)
            ->assertDontSee('value="DELIVERED"', false)
            ->assertDontSee('Pending Payment')
            ->assertDontSee('Payment Received')
            ->assertDontSee('Total Pending Amount');

        $page = $this->actingAs($context['supplier'])
            ->get(route('orders.ui.dispatch'))
            ->assertOk();

        $this->assertSame(1, (int) ($page->viewData('summaryCounts')['dispatched'] ?? 0));
        $this->assertNotNull($order->order_number);

        $content = $page->getContent();
        $this->assertMatchesRegularExpression(
            '/id="dispatchReturnDamagedCheckbox"[^>]*>/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="dispatchReturnDamagedCheckbox"[^>]*\bchecked\b/',
            $content
        );
    }

    public function test_dispatch_orders_requires_orders_view_permission(): void
    {
        $this->allowPermissions([]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch'))
            ->assertForbidden();
    }

    public function test_dispatch_list_api_returns_owned_dispatched_orders(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $owned = $this->moveToDispatched($owner);

        $other = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $foreign = $this->moveToDispatched($other);

        $response = $this->actingAs($owner['supplier'])
            ->getJson(route('orders.dispatch.index', [
                'status' => 'DISPATCHED',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.summary_counts.dispatched', 1);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($owned->id, $ids);
        $this->assertNotContains($foreign->id, $ids);

        $row = collect($response->json('data'))->firstWhere('id', $owned->id);
        $this->assertSame($owned->order_number, $row['order_number']);
        $this->assertSame((string) $owned->shipment->tracking_number, $row['waybill']);
        $this->assertSame(OrderStatus::DISPATCHED->value, $row['status']);
        $this->assertNotEmpty($row['dispatched_at']);
        $this->assertSame($owned->fulfillment?->invoice_number, $row['invoice_number']);
    }

    public function test_dispatch_list_filters_search_status_and_pagination(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 10, orderQuantity: 1);
        $first = $this->moveToDispatched($context);

        $secondContext = $this->makeConfirmedOrderContext(
            stockQuantity: 10,
            orderQuantity: 1,
            supplier: $context['supplier'],
        );
        $second = $this->moveToDispatched($secondContext);

        $tracking = (string) $first->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->getJson(route('orders.dispatch.index', [
                'status' => 'DISPATCHED',
                'search' => $tracking,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $first->id);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => (string) $second->shipment->tracking_number,
            ])
            ->assertOk();

        $this->actingAs($context['supplier'])
            ->getJson(route('orders.dispatch.index', [
                'status' => 'RETURNED',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.status', OrderStatus::RETURNED->value);

        $this->actingAs($context['supplier'])
            ->getJson(route('orders.dispatch.index', [
                'status' => 'DISPATCHED',
                'per_page' => 1,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_dispatch_list_does_not_expose_other_supplier_orders_via_search(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($owner);
        $tracking = (string) $order->shipment->tracking_number;

        $intruder = $this->makeSupplierUser('Intruder Dispatch Co');

        $this->actingAs($intruder)
            ->getJson(route('orders.dispatch.index', [
                'search' => $tracking,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_view_action_opens_real_owned_order_and_blocks_foreign_order(): void
    {
        $owner = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($owner);

        $this->actingAs($owner['supplier'])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number);

        $intruder = $this->makeSupplierUser('Foreign Viewer');

        $this->actingAs($intruder)
            ->get(route('orders.show', $order))
            ->assertNotFound();
    }

    public function test_inline_return_scan_marks_returned_and_restores_stock_once(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 2);
        $order = $this->moveToDispatched($context);
        $tracking = (string) $order->shipment->tracking_number;
        $variantId = (int) $context['variant']->id;
        $stockService = app(StockService::class);

        $this->assertSame(3, $stockService->availableQuantityForVariant($variantId));

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $tracking,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::RETURNED->value)
            ->assertJsonPath('data.stock_restored', true);

        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));
        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $tracking,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertSame(1, StockRestoration::query()->where('order_id', $order->id)->count());
        $this->assertSame(5, $stockService->availableQuantityForVariant($variantId));
    }

    public function test_order_payments_page_loads_for_authorized_supplier(): void
    {
        $this->allowPermissions(['orders.view']);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.order-payments'))
            ->assertOk()
            ->assertSee('Order Payments')
            ->assertSee('Review delivered orders against supplier settlement invoices and recorded payments.')
            ->assertSee('id="supplierOrderPaymentsPage"', false)
            ->assertSee('Upload Courier Payment File')
            ->assertSee('Upload History')
            ->assertSee('Unpaid Settlement')
            ->assertSee('Settlement Paid')
            ->assertSee('Unpaid Payable')
            ->assertSee('Last Upload')
            ->assertSee('Not recorded')
            ->assertDontSee('Kasun Perera')
            ->assertDontSee('Royal Express')
            ->assertSee('Search order, customer, waybill or invoice')
            ->assertSee('All Couriers')
            ->assertSee('Unpaid')
            ->assertSee('No settlement record')
            ->assertSee('Paid')
            ->assertSee('data-orders=', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertSee('id="orderPaymentsUploadModal"', false)
            ->assertSee('id="orderPaymentsReviewModal"', false)
            ->assertSee('id="orderPaymentsDetailModal"', false)
            ->assertSee('Returned orders are handled separately')
            ->assertDontSee('Coming soon')
            ->assertDontSee('will be available in a later Phase 2B step');
    }

    public function test_order_payments_requires_orders_view_permission(): void
    {
        $this->allowPermissions([]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.order-payments'))
            ->assertForbidden();
    }
}
