<?php

namespace Tests\Feature\Order;

use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierArchivedOrdersUiTest extends TestCase
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

    public function test_archived_orders_page_loads_for_authorized_supplier(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.archived'))
            ->assertOk()
            ->assertSee('Archived Orders')
            ->assertSee('Historical delivered and returned orders from the completed-order archive.')
            ->assertSee('id="supplierArchivedOrdersPage"', false)
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
            ->assertDontSee('Mahesh Wickrama')
            ->assertSee('Search order, customer, phone or waybill')
            ->assertSee('All Couriers')
            ->assertSee('Completed-order archive')
            ->assertSee('Archived Orders contains older completed orders. Recent delivered and returned orders remain in Completed Orders.')
            ->assertSee('Archived Orders keeps older completed records separate from the active completed-order workspace.')
            ->assertSee('View Completed Orders')
            ->assertSee('href="'.route('orders.ui.completed').'"', false)
            ->assertSee('id="archivedOrdersViewModal"', false)
            ->assertSee('search-filter-card', false)
            ->assertSee('orders-table', false)
            ->assertSee('completed-orders-summary-cards', false)
            ->assertDontSee('Coming soon')
            ->assertDontSee('will be available in a later Phase 2B step')
            ->assertDontSee('Payment Pending')
            ->assertDontSee('Payment Received')
            ->assertDontSee('Payment Paid')
            ->assertDontSee('Order Payments')
            ->assertDontSee('Courier Payment')
            ->assertDontSee('UI prototype only')
            ->assertDontSee('after backend integration')
            ->assertDontSee('Mock data')
            ->assertDontSee('Mark Returned')
            ->assertDontSee('Scan Return')
            ->assertDontSee('Create Dispatch Note')
            ->assertDontSee('archived-order-dispatch', false)
            ->assertDontSee('Mark as Returned');
    }

    public function test_archived_orders_uses_supplier_settlement_terminology_not_courier_payment_lifecycle(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.archived'))
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
        $this->assertStringContainsString(route('orders.ui.completed'), $content);
        $this->assertStringContainsString('View', $content);

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
        $this->assertStringNotContainsString('Mark Returned', $content);
        $this->assertStringNotContainsString('Scan Return', $content);
        $this->assertStringNotContainsString('Create Dispatch Note', $content);
    }

    public function test_archived_orders_requires_orders_view_permission(): void
    {
        $this->allowPermissions([]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.archived'))
            ->assertForbidden();
    }

    public function test_archived_orders_page_does_not_introduce_backend_list_api_hooks(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.archived'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-orders=', $content);
        $this->assertStringNotContainsString('data-mock-orders', $content);
        $this->assertStringNotContainsString('fetch(', $content);
        $this->assertStringNotContainsString('axios.', $content);
        $this->assertStringNotContainsString('XMLHttpRequest', $content);
    }
}
