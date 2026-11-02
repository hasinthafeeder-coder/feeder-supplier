<?php

namespace Tests\Feature\Order;

use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierDispatchSummaryUiTest extends TestCase
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

    public function test_dispatch_summary_page_loads_for_authorized_supplier(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch-summary'))
            ->assertOk()
            ->assertSee('Dispatch Summary')
            ->assertSee('Review pending courier handover and recorded Dispatch Notes. Creating a note does not change order status.')
            ->assertSee('id="supplierDispatchSummaryPage"', false)
            ->assertSee('Pending Courier Handover')
            ->assertSee('Orders Awaiting Handover')
            ->assertSee('Dispatch Notes History')
            ->assertSee('Create Dispatch Note')
            ->assertSee('View Dispatch Note')
            ->assertSee('COD on Handover')
            ->assertSee('COD on this handover')
            ->assertSee('Print Dispatch Note')
            ->assertDontSee('DO-20261005-001')
            ->assertDontSee('Royal Express')
            ->assertSee('Search order ID, customer, waybill or invoice')
            ->assertSee('All Couriers')
            ->assertSee('Courier handover batch tracking')
            ->assertSee('Dispatch Orders is where dispatch and return operations are handled.')
            ->assertSee('Confirming records a Dispatch Note for this courier handover only.')
            ->assertSee('These orders remain')
            ->assertSee('DISPATCHED')
            ->assertSee('data-create-url=', false)
            ->assertSee('data-print-url-template=', false)
            ->assertSee('No Dispatch Notes recorded yet.')
            ->assertDontSee('Create Dispatch Order')
            ->assertDontSee('Dispatch Order No.')
            ->assertDontSee('Pending Courier Dispatch')
            ->assertDontSee('>Dispatch History<', false)
            ->assertDontSee('Shipment Outcomes')
            ->assertDontSee('Dispatch Notes are not stored')
            ->assertDontSee('UI prototype only')
            ->assertDontSee('after backend integration')
            ->assertDontSee('Example Supplier')
            ->assertDontSee('Coming soon')
            ->assertDontSee('Pending Payment')
            ->assertDontSee('Payment Received')
            ->assertDontSee('Total Pending Amount');
    }

    public function test_dispatch_summary_requires_orders_view_permission(): void
    {
        $this->allowPermissions([]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch-summary'))
            ->assertForbidden();
    }

    public function test_dispatch_summary_scripts_use_dispatch_note_terminology(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch-summary'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Create Dispatch Note', $content);
        $this->assertStringContainsString('Dispatch Notes', $content);
        $this->assertStringContainsString('COD on this handover', $content);
        $this->assertStringContainsString('COD on Handover', $content);
        $this->assertStringContainsString('orders awaiting handover', $content);
        $this->assertStringContainsString('Showing ${from}–${to} of ${total} Dispatch Notes', $content);
        $this->assertStringContainsString('orders remain DISPATCHED', $content);
        $this->assertStringContainsString('Confirming records a Dispatch Note for this courier handover only.', $content);
        $this->assertStringContainsString('window.location.reload()', $content);
        $this->assertStringNotContainsString('Create Dispatch Order', $content);
        $this->assertStringNotContainsString('Dispatch Order created successfully.', $content);
        $this->assertStringNotContainsString('Showing ${from}–${to} of ${total} dispatch orders', $content);
        $this->assertStringNotContainsString('Dispatch Notes are not stored.', $content);
        $this->assertStringNotContainsString('UI prototype only', $content);
        $this->assertStringNotContainsString('this is a UI prototype', $content);
        $this->assertStringNotContainsString('>PENDING<', $content);
        $this->assertStringNotContainsString('Payment Received', $content);
        $this->assertStringNotContainsString('Total Pending Amount', $content);
    }
}
