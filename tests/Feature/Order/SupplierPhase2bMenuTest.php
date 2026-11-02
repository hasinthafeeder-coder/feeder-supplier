<?php

namespace Tests\Feature\Order;

use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierPhase2bMenuTest extends TestCase
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

    public function test_supplier_sidebar_shows_phase_2b_order_menu_and_hides_order_payments(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch'))
            ->assertOk()
            ->assertSee('New Orders')
            ->assertSee('Print Orders')
            ->assertSee('Packing Orders')
            ->assertSee('Dispatch Orders')
            ->assertSee('Completed Orders')
            ->assertSee('Archived Orders')
            ->assertSee('Dispatch Summary')
            ->assertDontSee('>Order Payments<', false)
            ->assertDontSee("title\">Order Payments", false);
    }

    public function test_completed_and_archived_orders_menu_targets_load_list_ui(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.completed'))
            ->assertOk()
            ->assertSee('Completed Orders')
            ->assertSee('id="supplierCompletedOrdersPage"', false)
            ->assertSee('Supplier Settlement')
            ->assertDontSee('Coming soon');

        $this->actingAs($supplier)
            ->get(route('orders.ui.archived'))
            ->assertOk()
            ->assertSee('Archived Orders')
            ->assertSee('id="supplierArchivedOrdersPage"', false)
            ->assertSee('Supplier Settlement')
            ->assertSee('View Completed Orders')
            ->assertDontSee('Coming soon');
    }
}
