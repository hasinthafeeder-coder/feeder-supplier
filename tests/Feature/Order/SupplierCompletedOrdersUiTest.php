<?php

namespace Tests\Feature\Order;

use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierCompletedOrdersUiTest extends TestCase
{
    use SetsUpSupplierOrderApiData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->allowPermissions(['orders.view']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_completed_orders_page_loads_for_authorized_supplier(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.completed'))
            ->assertOk()
            ->assertSee('Completed Orders')
            ->assertSee('View recent delivered and returned orders and their supplier settlement status.')
            ->assertSee('id="supplierCompletedOrdersPage"', false)
            ->assertSee('Delivered')
            ->assertSee('Returned')
            ->assertSee('Supplier Settlement Pending')
            ->assertSee('Supplier Settled')
            ->assertSee('Supplier Settlement')
            ->assertSee('Settlement Pending')
            ->assertSee('Settled')
            ->assertSee('value="DELIVERED"', false)
            ->assertSee('value="RETURNED"', false)
            ->assertSee('data-orders=', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('Kasun Perera')
            ->assertSee('Search order, customer, phone or waybill')
            ->assertSee('All Couriers')
            ->assertSee('Recent completed window')
            ->assertSee('Completed Orders shows recent delivered and returned orders.')
            ->assertSee('Older completed orders are available in Archived Orders.')
            ->assertSee('View Archived Orders')
            ->assertSee('href="'.route('orders.ui.archived').'"', false)
            ->assertSee('id="completedOrdersViewModal"', false)
            ->assertSee('search-filter-card', false)
            ->assertSee('orders-table', false)
            ->assertDontSee('Coming soon')
            ->assertDontSee('will be available in a later Phase 2B step')
            ->assertDontSee('Payment Pending')
            ->assertDontSee('Payment Received')
            ->assertDontSee('Payment Paid')
            ->assertDontSee('Order Payments')
            ->assertDontSee('Courier Payment')
            ->assertDontSee('UI prototype only')
            ->assertDontSee('after backend integration');
    }

    public function test_completed_orders_uses_supplier_settlement_terminology_not_courier_payment_lifecycle(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.completed'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Supplier Settlement', $content);
        $this->assertStringContainsString('Settlement Pending', $content);
        $this->assertStringContainsString('Settled', $content);
        $this->assertStringContainsString('DELIVERED', $content);
        $this->assertStringContainsString('RETURNED', $content);
        $this->assertStringContainsString('SETTLEMENT_PENDING', $content);
        $this->assertStringContainsString('NO_SETTLEMENT', $content);
        $this->assertStringContainsString('data-orders=', $content);
        $this->assertStringNotContainsString('data-mock-orders', $content);
        $this->assertStringContainsString(route('orders.ui.archived'), $content);

        $this->assertStringNotContainsString('Payment Pending', $content);
        $this->assertStringNotContainsString('Payment Received', $content);
        $this->assertStringNotContainsString('Payment Paid', $content);
        $this->assertStringNotContainsString('>PENDING<', $content);
        $this->assertStringNotContainsString('>RECEIVED<', $content);
        $this->assertStringNotContainsString('>PAID<', $content);
        $this->assertStringNotContainsString('value="PENDING"', $content);
        $this->assertStringNotContainsString('value="RECEIVED"', $content);
        $this->assertStringNotContainsString('value="PAID"', $content);
        $this->assertStringNotContainsString('payment_status', $content);
        $this->assertStringNotContainsString('Coming soon', $content);
        $this->assertStringNotContainsString('courier Excel', $content);
    }

    public function test_completed_orders_requires_orders_view_permission(): void
    {
        $this->allowPermissions([]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.completed'))
            ->assertForbidden();
    }

    public function test_completed_orders_page_does_not_introduce_backend_list_api_hooks(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.completed'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-orders=', $content);
        $this->assertStringNotContainsString('data-mock-orders', $content);
        $this->assertStringNotContainsString('fetch(', $content);
        $this->assertStringNotContainsString('axios.', $content);
        $this->assertStringNotContainsString('XMLHttpRequest', $content);
    }
}
