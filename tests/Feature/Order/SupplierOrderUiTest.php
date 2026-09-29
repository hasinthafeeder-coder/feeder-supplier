<?php

namespace Tests\Feature\Order;

use Feeder\Core\Models\Courier;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierOrderUiTest extends TestCase
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
            'orders.cancel',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_new_orders_page_renders_with_mode_filters(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.new'))
            ->assertOk()
            ->assertSee('New Orders')
            ->assertSee('data-mode="fresh"', false)
            ->assertSee('data-list-url-fresh="'.route('orders.fresh').'"', false)
            ->assertSee('data-list-url-out-of-stock="'.route('orders.out-of-stock').'"', false)
            ->assertSee('id="ordersSendToPrintBtn"', false)
            ->assertSee('orders-mode-btn', false)
            ->assertSee('workspace-tab', false)
            ->assertSee('data-mode="fresh"', false)
            ->assertSee('id="ordersSendCountInput"', false)
            ->assertSee('id="ordersSendCountBtn"', false)
            ->assertSee('id="ordersSendEligibleCount"', false)
            ->assertSee('id="ordersSearchLocationHost"', false)
            ->assertDontSee('data-max-batch="25"', false);
    }

    public function test_send_to_print_script_shows_alert_after_list_refresh(): void
    {
        $supplier = $this->makeSupplierUser();

        $content = $this->actingAs($supplier)
            ->get(route('orders.ui.new'))
            ->assertOk()
            ->getContent();

        // loadOrders() clears #ordersAlertHost — success/error must be shown after refresh,
        // otherwise Send-to-Print appears to do nothing (especially on INSUFFICIENT_STOCK).
        $this->assertMatchesRegularExpression(
            '/state\.selectedIds\.clear\(\);\s*(?:\/\/[^\n]*\n\s*)*await loadOrders\(\);\s*showAlert\(\'success\'/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/catch\s*\(\s*error\s*\)\s*\{\s*await loadOrders\(\);\s*showAlert\(\'error\'/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/showAlert\(\'success\',\s*payload\.message \|\| \'Orders sent to print\.\'\);\s*await loadOrders\(\)/',
            $content
        );
    }

    public function test_new_orders_page_accepts_out_of_stock_mode_query(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.new', ['mode' => 'out-of-stock']))
            ->assertOk()
            ->assertSee('data-mode="out-of-stock"', false)
            ->assertSee('id="ordersSendToPrintBtn"', false);
    }

    public function test_new_orders_page_accepts_exchange_mode_query(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.new', ['mode' => 'exchange']))
            ->assertOk()
            ->assertSee('data-mode="exchange"', false)
            ->assertSee('Exchange orders coming soon');
    }

    public function test_legacy_new_order_ui_paths_redirect_to_unified_page(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.fresh'))
            ->assertRedirect(route('orders.ui.new', ['mode' => 'fresh']));

        $this->actingAs($supplier)
            ->get(route('orders.ui.out-of-stock'))
            ->assertRedirect(route('orders.ui.new', ['mode' => 'out-of-stock']));

        $this->actingAs($supplier)
            ->get(route('orders.ui.exchange'))
            ->assertRedirect(route('orders.ui.new', ['mode' => 'exchange']));
    }

    public function test_print_orders_page_is_no_longer_a_placeholder(): void
    {
        $this->allowPermissions([
            'orders.view',
            'orders.print',
            'orders.send-to-packing',
        ]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.print'))
            ->assertOk()
            ->assertSee('Print Orders')
            ->assertSee('id="supplierPrintOrdersPage"', false)
            ->assertDontSee('coming soon', false);
    }

    public function test_packaging_orders_page_is_no_longer_a_placeholder(): void
    {
        $this->allowPermissions([
            'orders.view',
            'orders.pack',
        ]);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.packaging'))
            ->assertOk()
            ->assertSee('Packing Orders')
            ->assertSee('id="supplierPackagingOrdersPage"', false)
            ->assertDontSee('coming soon', false);
    }

    public function test_new_orders_page_hides_actions_without_permissions(): void
    {
        $this->allowPermissions(['orders.view']);
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('orders.ui.new'))
            ->assertOk()
            ->assertDontSee('id="ordersSendToPrintBtn"', false)
            ->assertDontSee('id="orderCancelModal"', false)
            ->assertSee('data-can-send-to-print="0"', false)
            ->assertSee('data-can-cancel="0"', false)
            ->assertSee('View Order', false)
            ->assertSee('id="ordersSearchLocationHost"', false)
            ->assertSee('id="ordersTable"', false)
            ->assertDontSee('id="ordersSendEligibleCount"', false);
    }

    public function test_order_show_page_renders_for_owned_order_with_cancel(): void
    {
        $context = $this->makeConfirmedOrderContext();

        $this->actingAs($context['supplier'])
            ->get(route('orders.show', $context['order']))
            ->assertOk()
            ->assertSee($context['order']->order_number)
            ->assertSee('Cancel Order')
            ->assertSee('id="orderShowCancelBtn"', false);
    }

    public function test_order_show_page_hides_foreign_supplier_orders(): void
    {
        $ownerContext = $this->makeConfirmedOrderContext();
        $intruder = $this->makeSupplierUser('Intruder Supplier');

        $this->actingAs($intruder)
            ->get(route('orders.show', $ownerContext['order']))
            ->assertNotFound();
    }

    public function test_fresh_search_reports_print_location_for_exact_order_id(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.send-to-print'), [
                'mode' => 'manual',
                'order_ids' => [$context['order']->id],
            ])
            ->assertOk();

        $response = $this->actingAs($context['supplier'])
            ->getJson(route('orders.fresh', ['search' => $context['order']->order_number]))
            ->assertOk();

        $this->assertSame(0, $response->json('meta.total'));
        $this->assertSame('print', $response->json('meta.search_location.queue'));
        $this->assertStringContainsString('Print Orders', (string) $response->json('meta.search_location.message'));
    }

    public function test_fresh_list_api_includes_payable_and_pagination_meta(): void
    {
        $context = $this->makeConfirmedOrderContext();

        $response = $this->actingAs($context['supplier'])
            ->getJson(route('orders.fresh'))
            ->assertOk();

        $response->assertJsonPath('data.0.id', $context['order']->id);
        $this->assertArrayHasKey('customer_payable_amount', $response->json('data.0'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertArrayHasKey('products', $response->json('meta.filter_options'));
        $this->assertSame(1, $response->json('meta.mode_counts.fresh'));
        $this->assertSame(0, $response->json('meta.mode_counts.exchange'));
        $this->assertSame(0, $response->json('meta.mode_counts.out_of_stock'));
    }

    public function test_fresh_list_api_filters_by_product_and_draft_courier(): void
    {
        $matching = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $otherProduct = $this->makeConfirmedOrderContext(
            stockQuantity: 5,
            orderQuantity: 1,
            supplier: $matching['supplier'],
        );

        $courierA = Courier::query()->create([
            'code' => 'CA'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Courier Alpha',
            'is_active' => true,
        ]);
        $courierB = Courier::query()->create([
            'code' => 'CB'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Courier Beta',
            'is_active' => true,
        ]);

        $matching['order']->forceFill(['draft_courier_id' => $courierA->id])->save();
        $otherProduct['order']->forceFill(['draft_courier_id' => $courierB->id])->save();

        $byCourier = $this->actingAs($matching['supplier'])
            ->getJson(route('orders.fresh', ['courier_id' => $courierA->id]))
            ->assertOk();

        $byCourier->assertJsonPath('meta.total', 1);
        $byCourier->assertJsonPath('data.0.id', $matching['order']->id);
        $byCourier->assertJsonPath('data.0.courier_id', $courierA->id);
        $byCourier->assertJsonPath('data.0.courier', 'Courier Alpha');
        $this->assertFalse(collect($byCourier->json('data'))->pluck('id')->contains($otherProduct['order']->id));

        $byProduct = $this->actingAs($matching['supplier'])
            ->getJson(route('orders.fresh', ['product_id' => $matching['product']->id]))
            ->assertOk();

        $byProduct->assertJsonPath('meta.total', 1);
        $byProduct->assertJsonPath('data.0.id', $matching['order']->id);
        $this->assertFalse(collect($byProduct->json('data'))->pluck('id')->contains($otherProduct['order']->id));

        $courierIds = collect($byCourier->json('meta.filter_options.couriers'))->pluck('id');
        $this->assertTrue($courierIds->contains($courierA->id));
    }

    public function test_new_orders_page_renders_mode_tab_counts(): void
    {
        $context = $this->makeConfirmedOrderContext();

        $this->actingAs($context['supplier'])
            ->get(route('orders.ui.new'))
            ->assertOk()
            ->assertSee('data-mode-count="fresh">1</span>', false)
            ->assertSee('data-mode-count="exchange">0</span>', false)
            ->assertSee('data-mode-count="out-of-stock">0</span>', false)
            ->assertSeeInOrder(['filterCourier', 'filterProduct', 'filterDateFrom', 'filterDateTo'], false);
    }
}
