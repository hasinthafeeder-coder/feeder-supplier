<?php

namespace Tests\Feature\Order;

use App\Models\User;
use App\Services\Order\SupplierAfterDispatchWorkspaceService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\ShipmentStatus;
use Feeder\Core\Enums\SupplierFulfillmentStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderFulfillment;
use Feeder\Core\Models\OrderItem;
use Feeder\Core\Models\OrderStatusHistory;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\SupplierPayment;
use Feeder\Core\Models\SupplierSettlementInvoice;
use Feeder\Core\Models\SupplierSettlementInvoiceLine;
use Feeder\Core\Models\SupplierSettlementReturnedReference;
use Feeder\Core\Services\Order\CompletedOrderArchiveWindow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

/**
 * Live Completed, Archived, Dispatch Summary, and Order Payments screens.
 *
 * Uses SQLite memory when the driver exists, otherwise the separate database
 * dropshipping_supplier_after_dispatch_test. It never connects to dropshipping.
 */
class SupplierAfterDispatchLiveTest extends TestCase
{
    private Portal $portal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedDatabase();
        $this->createSchema();
        config(['cache.default' => 'array']);

        $this->portal = Portal::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => PortalCode::SUPPLIER->value,
            'name' => 'Supplier Portal',
            'subdomain' => 'supplier-test',
            'description' => 'Supplier Portal',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_completed_and_archived_use_live_status_window_and_supplier_ownership(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));

        $supplier = $this->makeSupplier(['orders.view'], 'Owned Supplier Co');
        $other = $this->makeSupplier(['orders.view'], 'Other Supplier Co');
        $ownedProduct = $this->makeProduct($supplier, 'Owned Serum');
        $foreignProduct = $this->makeProduct($other, 'Foreign Cream');
        $courier = $this->makeCourier('City Courier');
        $hiddenCourier = $this->makeCourier('Hidden Courier');

        $recent = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-DEL-RECENT', 'Recent Delivered', '0772000001', [
            'delivered_at' => now()->subDays(3),
            'customer_payable_amount' => 4500,
        ]);
        $recentItem = $recent->items()->first();
        $this->makeShipment($recent, $courier, 'WB-RECENT', ShipmentStatus::DELIVERED);
        $this->makeSettlementLine($recent, $recentItem, $supplier, 'INV-RECENT', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '125.50', now()->subHour());

        $boundary = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-DEL-BOUNDARY', 'Boundary Delivered', '0772000002', [
            'delivered_at' => now()->subDays(CompletedOrderArchiveWindow::DAYS),
        ]);

        $undated = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-DEL-NULL', 'Undated Delivered', '0772000003', [
            'delivered_at' => null,
        ]);

        $recentReturned = $this->makeOrder($supplier, $ownedProduct, OrderStatus::RETURNED, 'ORD-RET-RECENT', 'Recent Returned', '0772000004', [
            'delivered_at' => now()->subDays(30),
            'returned_at' => now()->subDay(),
        ]);
        $this->makeReturnedReference($recentReturned, $recentReturned->items()->first(), $supplier);

        $oldDelivered = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-DEL-OLD', 'Old Delivered', '0773000001', [
            'delivered_at' => now()->subDays(CompletedOrderArchiveWindow::DAYS)->subSecond(),
            'customer_payable_amount' => 3200,
        ]);
        $this->makeShipment($oldDelivered, $courier, 'WB-OLD', ShipmentStatus::DELIVERED);
        $this->makeSettlementLine(
            $oldDelivered,
            $oldDelivered->items()->first(),
            $supplier,
            'INV-OLD',
            SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_UNPAID,
            '80.25',
        );

        $oldReturned = $this->makeOrder($supplier, $ownedProduct, OrderStatus::RETURNED, 'ORD-RET-OLD', 'Old Returned', '0773000002', [
            'delivered_at' => now()->subDay(),
            'returned_at' => now()->subDays(10),
        ]);

        $voided = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-DEL-VOID', 'Voided Settlement', '0772000005', [
            'delivered_at' => now()->subDays(2),
        ]);
        $this->makeSettlementLine(
            $voided,
            $voided->items()->first(),
            $supplier,
            'INV-VOID',
            SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID,
            '50.00',
            now(),
            SupplierSettlementInvoice::INSTRUCTION_STATUS_VOIDED,
        );

        $missing = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-DEL-NONE', 'Missing Settlement', '0772000006', [
            'delivered_at' => now()->subDays(1),
            'customer_payable_amount' => 7777,
        ]);

        $mixed = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-MIXED', 'Mixed Customer', '0772000007', [
            'delivered_at' => now()->subDays(2),
            'customer_payable_amount' => 5000,
        ]);
        $ownedItem = $mixed->items()->first();
        $foreignItem = $this->addItem($mixed, $foreignProduct, 'Foreign Cream');
        $this->makeShipment($mixed, $courier, 'WB-MIXED', ShipmentStatus::DELIVERED);
        $this->makeSettlementLine($mixed, $ownedItem, $supplier, 'INV-OWN', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '125.50', now()->subHours(2));
        $this->makeSettlementLine($mixed, $foreignItem, $other, 'INV-FOREIGN', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '9999.00', now()->subHours(2));

        $mistagged = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DELIVERED, 'ORD-MISTAGGED', 'Mistagged Customer', '0772000008', [
            'delivered_at' => now()->subDays(2),
        ]);
        $foreignOnOwnedOrder = $this->addItem($mistagged, $foreignProduct, 'Foreign Cream');
        $this->makeSettlementLine($mistagged, $foreignOnOwnedOrder, $supplier, 'INV-MISTAG', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '4444.00', now());

        $onlyForeign = $this->makeOrder($supplier, $foreignProduct, OrderStatus::DELIVERED, 'ORD-ONLY-FOREIGN', 'Only Foreign', '0772000009', [
            'delivered_at' => now()->subDay(),
        ]);
        $this->makeSettlementLine($onlyForeign, $onlyForeign->items()->first(), $other, 'INV-ONLY-FOREIGN', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '3333.00', now());

        $dispatched = $this->makeOrder($supplier, $ownedProduct, OrderStatus::DISPATCHED, 'ORD-DISPATCHED', 'Dispatch Customer', '0771000001');
        $this->makeShipment($dispatched, $courier, 'WB-DISPATCH', ShipmentStatus::BOOKED);
        OrderFulfillment::query()->create([
            'order_id' => $dispatched->id,
            'status' => SupplierFulfillmentStatus::DISPATCHED,
            'invoice_number' => 'FUL-DISPATCH',
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $dispatched->id,
            'from_status' => OrderStatus::PACKING->value,
            'to_status' => OrderStatus::DISPATCHED->value,
            'changed_by' => $supplier->id,
            'created_at' => now()->subHours(5),
        ]);

        $returnedNoShipment = $this->makeOrder($supplier, $ownedProduct, OrderStatus::RETURNED, 'ORD-RET-NOSHIP', 'Returned Without Shipment', '0771000002', [
            'returned_at' => now()->subHours(3),
        ]);

        $foreignOrder = $this->makeOrder($other, $foreignProduct, OrderStatus::DELIVERED, 'ORD-FOREIGN', 'Foreign Customer', '0780000001', [
            'delivered_at' => now()->subDay(),
        ]);
        $this->makeShipment($foreignOrder, $hiddenCourier, 'WB-FOREIGN', ShipmentStatus::DELIVERED);
        $this->makeSettlementLine($foreignOrder, $foreignOrder->items()->first(), $other, 'INV-OTHER', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '2222.00', now());

        $completed = $this->actingAs($supplier)
            ->get(route('orders.ui.completed', [
                'supplier_id' => $other->id,
                'order' => $foreignOrder->uuid,
            ]))
            ->assertOk()
            ->assertSee('data-orders=', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('Kasun Perera')
            ->assertDontSee('ORD-FOREIGN')
            ->assertDontSee('Foreign Cream')
            ->assertDontSee('9999.00')
            ->assertDontSee('4444.00')
            ->assertDontSee('3333.00')
            ->assertDontSee('INV-FOREIGN')
            ->assertDontSee('Hidden Courier');

        $completedOrders = $this->ordersFrom($completed->getContent(), 'supplierCompletedOrdersPage');
        $completedNumbers = array_column($completedOrders, 'order_number');

        $this->assertEqualsCanonicalizing([
            'ORD-DEL-RECENT',
            'ORD-DEL-BOUNDARY',
            'ORD-DEL-NULL',
            'ORD-RET-RECENT',
            'ORD-DEL-VOID',
            'ORD-DEL-NONE',
            'ORD-MIXED',
            'ORD-MISTAGGED',
            'ORD-RET-NOSHIP',
        ], $completedNumbers);
        $this->assertNotContains('ORD-DEL-OLD', $completedNumbers);
        $this->assertNotContains('ORD-RET-OLD', $completedNumbers);
        $this->assertNotContains('ORD-DISPATCHED', $completedNumbers);
        $this->assertNotContains('ORD-ONLY-FOREIGN', $completedNumbers);
        $this->assertNotContains('ORD-FOREIGN', $completedNumbers);

        $recentRow = $this->row($completedOrders, 'ORD-DEL-RECENT');
        $this->assertSame(OrderStatus::DELIVERED->value, $recentRow['outcome']);
        $this->assertSame('City Courier', $recentRow['courier']);
        $this->assertSame('WB-RECENT', $recentRow['waybill']);
        $this->assertSame('4,500.00', $recentRow['cod_amount']);
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_SETTLED, $recentRow['settlement_status']);
        $this->assertSame('125.50', $recentRow['settlement_amount']);
        $this->assertSame('INV-RECENT', $recentRow['invoice_number']);
        $this->assertSame(
            now()->subDays(3)->timezone(config('app.timezone'))->toDateString(),
            $recentRow['completed_date']
        );

        $returnedRow = $this->row($completedOrders, 'ORD-RET-RECENT');
        $this->assertSame(OrderStatus::RETURNED->value, $returnedRow['outcome']);
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_PENDING, $returnedRow['settlement_status']);
        $this->assertSame('0.00', $returnedRow['settlement_amount']);

        $voidedRow = $this->row($completedOrders, 'ORD-DEL-VOID');
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_NONE, $voidedRow['settlement_status']);
        $this->assertNull($voidedRow['settlement_amount']);

        $missingRow = $this->row($completedOrders, 'ORD-DEL-NONE');
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_NONE, $missingRow['settlement_status']);
        $this->assertNull($missingRow['settlement_amount']);
        $this->assertNull($missingRow['waybill']);
        $this->assertNull($missingRow['courier']);

        $mixedRow = $this->row($completedOrders, 'ORD-MIXED');
        $this->assertSame('125.50', $mixedRow['settlement_amount']);
        $this->assertSame('INV-OWN', $mixedRow['invoice_number']);
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_SETTLED, $mixedRow['settlement_status']);

        $mistaggedRow = $this->row($completedOrders, 'ORD-MISTAGGED');
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_NONE, $mistaggedRow['settlement_status']);
        $this->assertNull($mistaggedRow['settlement_amount']);

        $undatedRow = $this->row($completedOrders, 'ORD-DEL-NULL');
        $this->assertSame('', $undatedRow['completed_date']);

        $this->assertSame(7, $this->summaryCount($completed->getContent(), 'completedSummaryDelivered'));
        $this->assertSame(2, $this->summaryCount($completed->getContent(), 'completedSummaryReturned'));
        $this->assertSame(7, $this->summaryCount($completed->getContent(), 'completedSummarySettlementPending'));
        $this->assertSame(2, $this->summaryCount($completed->getContent(), 'completedSummarySettled'));

        $archived = $this->actingAs($supplier)
            ->get(route('orders.ui.archived'))
            ->assertOk()
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('ORD-FOREIGN')
            ->assertDontSee('ORD-DEL-RECENT');

        $archivedOrders = $this->ordersFrom($archived->getContent(), 'supplierArchivedOrdersPage');
        $this->assertEqualsCanonicalizing(
            ['ORD-DEL-OLD', 'ORD-RET-OLD'],
            array_column($archivedOrders, 'order_number')
        );
        $oldRow = $this->row($archivedOrders, 'ORD-DEL-OLD');
        $this->assertSame('WB-OLD', $oldRow['waybill']);
        $this->assertSame(SupplierAfterDispatchWorkspaceService::SETTLEMENT_PENDING, $oldRow['settlement_status']);
        $this->assertSame('80.25', $oldRow['settlement_amount']);
        $this->assertSame('INV-OLD', $oldRow['invoice_number']);
        $this->assertSame(1, $this->summaryCount($archived->getContent(), 'archivedSummaryDelivered'));
        $this->assertSame(1, $this->summaryCount($archived->getContent(), 'archivedSummaryReturned'));

        $otherView = $this->actingAs($other)
            ->get(route('orders.ui.completed'))
            ->assertOk()
            ->assertDontSee('ORD-MIXED')
            ->assertDontSee('INV-OWN')
            ->assertDontSee('Owned Serum')
            ->assertDontSee('125.50');

        $otherOrders = $this->ordersFrom($otherView->getContent(), 'supplierCompletedOrdersPage');
        $this->assertSame(['ORD-FOREIGN'], array_column($otherOrders, 'order_number'));
        $this->assertSame('2,222.00', $this->row($otherOrders, 'ORD-FOREIGN')['settlement_amount']);
    }

    public function test_dispatch_summary_distinguishes_live_shipment_states_and_hides_other_suppliers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));

        $supplier = $this->makeSupplier(['orders.view'], 'Dispatch Supplier Co');
        $other = $this->makeSupplier(['orders.view'], 'Other Dispatch Co');
        $product = $this->makeProduct($supplier, 'Owned Serum');
        $foreignProduct = $this->makeProduct($other, 'Foreign Cream');
        $courier = $this->makeCourier('City Courier');
        $hiddenCourier = $this->makeCourier('Hidden Courier');

        $dispatched = $this->makeOrder($supplier, $product, OrderStatus::DISPATCHED, 'ORD-DISPATCHED', 'Dispatch Customer', '0771000001', [
            'customer_payable_amount' => 1500,
        ]);
        $this->makeShipment($dispatched, $courier, 'WB-DISPATCH', ShipmentStatus::BOOKED);
        OrderFulfillment::query()->create([
            'order_id' => $dispatched->id,
            'status' => SupplierFulfillmentStatus::DISPATCHED,
            'invoice_number' => 'FUL-DISPATCH',
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $dispatched->id,
            'from_status' => OrderStatus::PACKING->value,
            'to_status' => OrderStatus::DISPATCHED->value,
            'created_at' => now()->subHours(5),
        ]);

        $delivered = $this->makeOrder($supplier, $product, OrderStatus::DELIVERED, 'ORD-DELIVERED', 'Delivered Customer', '0771000002', [
            'delivered_at' => now()->subDay(),
            'customer_payable_amount' => 2200,
        ]);
        $this->makeShipment($delivered, $courier, 'WB-DELIVERED', ShipmentStatus::DELIVERED);

        $returned = $this->makeOrder($supplier, $product, OrderStatus::RETURNED, 'ORD-RETURNED', 'Returned Customer', '0771000003', [
            'returned_at' => now()->subHours(2),
            'customer_payable_amount' => 900,
        ]);

        $foreign = $this->makeOrder($other, $foreignProduct, OrderStatus::DISPATCHED, 'ORD-FOREIGN-DISPATCH', 'Foreign Dispatch', '0780000009');
        $this->makeShipment($foreign, $hiddenCourier, 'WB-HIDDEN', ShipmentStatus::BOOKED);

        $response = $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch-summary', ['supplier_id' => $other->id]))
            ->assertOk()
            ->assertDontSee('ORD-FOREIGN-DISPATCH')
            ->assertDontSee('Hidden Courier')
            ->assertDontSee('DO-20261005-001')
            ->assertDontSee('Foreign Cream')
            ->assertSee('data-pending-couriers=', false)
            ->assertSee('data-dispatch-history=', false);

        $couriers = $this->dataset($response->getContent(), 'pendingCouriers');
        $history = $this->dataset($response->getContent(), 'dispatchHistory');

        $this->assertCount(2, $couriers);
        $city = $this->namedRow($couriers, 'City Courier');
        $this->assertSame(1, $city['pending_count']);
        $this->assertSame(1, $city['delivered_count']);
        $this->assertSame(0, $city['returned_count']);
        $this->assertSame('1,500.00', $city['total_cod']);
        $this->assertNotNull($city['last_dispatch']);
        $this->assertSame(['ORD-DISPATCHED'], array_column($city['orders'], 'order_number'));
        $this->assertSame('WB-DISPATCH', $city['orders'][0]['waybill']);
        $this->assertSame('FUL-DISPATCH', $city['orders'][0]['invoice_number']);

        $missingCourier = $this->namedRow($couriers, 'No courier recorded');
        $this->assertSame(0, $missingCourier['pending_count']);
        $this->assertSame(0, $missingCourier['delivered_count']);
        $this->assertSame(1, $missingCourier['returned_count']);
        $this->assertNull($missingCourier['last_dispatch']);

        // History lists persisted Dispatch Notes only (none created yet).
        $this->assertSame([], $history);
        $this->assertStringNotContainsString('ORD-FOREIGN-DISPATCH', $response->getContent());

        $this->assertStringContainsString('id="dispatchSummaryFilterSearch"', $response->getContent());
        $this->assertStringContainsString('data-per-page="25"', $response->getContent());
        $this->assertStringContainsString('id="dispatchSummaryHistoryPaginationHost"', $response->getContent());
        $this->assertStringContainsString('Dispatch Notes History', $response->getContent());
    }

    public function test_creating_dispatch_note_records_batch_without_changing_order_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));

        $supplier = $this->makeSupplier(['orders.view'], 'Note Supplier Co');
        $product = $this->makeProduct($supplier, 'Owned Serum');
        $courier = $this->makeCourier('City Courier');

        $first = $this->makeOrder($supplier, $product, OrderStatus::DISPATCHED, 'ORD-NOTE-1', 'Note Customer 1', '0773000001', [
            'customer_payable_amount' => 1000,
            'currency_code_snapshot' => 'LKR',
        ]);
        $this->makeShipment($first, $courier, 'WB-NOTE-1', ShipmentStatus::BOOKED);
        OrderFulfillment::query()->create([
            'order_id' => $first->id,
            'status' => SupplierFulfillmentStatus::DISPATCHED,
            'invoice_number' => 'FUL-NOTE-1',
        ]);

        $second = $this->makeOrder($supplier, $product, OrderStatus::DISPATCHED, 'ORD-NOTE-2', 'Note Customer 2', '0773000002', [
            'customer_payable_amount' => 500,
            'currency_code_snapshot' => 'LKR',
        ]);
        $this->makeShipment($second, $courier, 'WB-NOTE-2', ShipmentStatus::BOOKED);

        $create = $this->actingAs($supplier)
            ->postJson(route('orders.dispatch-summary.notes.store'), [
                'courier_id' => $courier->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.note_recorded', true)
            ->assertJsonPath('data.orders_count', 2)
            ->assertJsonPath('data.total_cod', '1,500.00')
            ->assertJsonPath('data.courier', 'City Courier');

        $dispatchNo = $create->json('data.dispatch_no');
        $noteUuid = $create->json('data.uuid');
        $this->assertNotEmpty($dispatchNo);
        $this->assertNotEmpty($noteUuid);
        $this->assertSame(OrderStatus::DISPATCHED, $first->fresh()->status);
        $this->assertSame(OrderStatus::DISPATCHED, $second->fresh()->status);

        $page = $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch-summary'))
            ->assertOk();

        $couriers = $this->dataset($page->getContent(), 'pendingCouriers');
        $history = $this->dataset($page->getContent(), 'dispatchHistory');

        $city = $this->namedRow($couriers, 'City Courier');
        $this->assertSame(0, $city['pending_count']);
        $this->assertSame([], $city['orders']);

        $this->assertCount(1, $history);
        $this->assertSame($dispatchNo, $history[0]['dispatch_no']);
        $this->assertTrue($history[0]['note_recorded']);
        $this->assertSame($supplier->email, $history[0]['operator']);
        $this->assertSame(['ORD-NOTE-1', 'ORD-NOTE-2'], array_column($history[0]['orders'], 'order_number'));

        $this->actingAs($supplier)
            ->get(route('orders.dispatch-summary.notes.print', ['uuid' => $noteUuid]))
            ->assertOk()
            ->assertSee($dispatchNo)
            ->assertSee('ORD-NOTE-1')
            ->assertSee('ORD-NOTE-2')
            ->assertSee('City Courier');

        $this->actingAs($supplier)
            ->postJson(route('orders.dispatch-summary.notes.store'), [
                'courier_id' => $courier->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'NO_PENDING_HANDOVER_ORDERS');
    }

    public function test_order_payments_use_settlement_records_and_do_not_mark_delivery_as_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));

        $supplier = $this->makeSupplier(['orders.view'], 'Payment Supplier Co');
        $other = $this->makeSupplier(['orders.view'], 'Other Payment Co');
        $product = $this->makeProduct($supplier, 'Owned Serum');
        $foreignProduct = $this->makeProduct($other, 'Foreign Cream');
        $courier = $this->makeCourier('City Courier');

        $paid = $this->makeOrder($supplier, $product, OrderStatus::DELIVERED, 'ORD-PAID', 'Paid Customer', '0772000011', [
            'delivered_at' => now()->subDays(2),
            'customer_payable_amount' => 4500,
        ]);
        $this->makeShipment($paid, $courier, 'WB-PAID', ShipmentStatus::DELIVERED);
        $paidAt = now()->subHours(4);
        $this->makeSettlementLine($paid, $paid->items()->first(), $supplier, 'INV-PAID', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '125.50', $paidAt);

        $unpaid = $this->makeOrder($supplier, $product, OrderStatus::DELIVERED, 'ORD-UNPAID', 'Unpaid Customer', '0772000012', [
            'delivered_at' => now()->subDay(),
            'customer_payable_amount' => 3000,
        ]);
        $this->makeShipment($unpaid, $courier, 'WB-UNPAID', ShipmentStatus::DELIVERED);
        $this->makeSettlementLine($unpaid, $unpaid->items()->first(), $supplier, 'INV-UNPAID', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_UNPAID, '80.25');

        $missing = $this->makeOrder($supplier, $product, OrderStatus::DELIVERED, 'ORD-MISSING', 'Missing Customer', '0772000013', [
            'delivered_at' => now()->subDays(4),
            'customer_payable_amount' => 7777,
        ]);

        $returned = $this->makeOrder($supplier, $product, OrderStatus::RETURNED, 'ORD-RETURNED-PAY', 'Returned Pay', '0772000014', [
            'returned_at' => now()->subDay(),
        ]);

        $mixed = $this->makeOrder($supplier, $product, OrderStatus::DELIVERED, 'ORD-PAY-MIXED', 'Mixed Pay', '0772000015', [
            'delivered_at' => now()->subDays(2),
        ]);
        $foreignItem = $this->addItem($mixed, $foreignProduct, 'Foreign Cream');
        $this->makeSettlementLine($mixed, $mixed->items()->first(), $supplier, 'INV-PAY-OWN', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_UNPAID, '40.00');
        $this->makeSettlementLine($mixed, $foreignItem, $other, 'INV-PAY-FOREIGN', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '9999.00', now());

        $foreign = $this->makeOrder($other, $foreignProduct, OrderStatus::DELIVERED, 'ORD-PAY-FOREIGN', 'Foreign Pay', '0780000002', [
            'delivered_at' => now()->subDay(),
        ]);
        $this->makeSettlementLine($foreign, $foreign->items()->first(), $other, 'INV-PAY-OTHER', SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID, '2222.00', now());

        $response = $this->actingAs($supplier)
            ->get(route('orders.ui.order-payments', ['supplier_id' => $other->id]))
            ->assertOk()
            ->assertSee('data-orders=', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('Kasun Perera')
            ->assertDontSee('ORD-PAY-FOREIGN')
            ->assertDontSee('ORD-RETURNED-PAY')
            ->assertDontSee('Foreign Cream')
            ->assertDontSee('9999.00')
            ->assertDontSee('INV-PAY-FOREIGN')
            ->assertDontSee('royal_2026-10-06.xlsx')
            ->assertSee('Courier payment file upload is not available on this screen.')
            ->assertSee('Not recorded');

        $orders = $this->ordersFrom($response->getContent(), 'supplierOrderPaymentsPage');
        $numbers = array_column($orders, 'order_number');
        $this->assertEqualsCanonicalizing([
            'ORD-PAID',
            'ORD-UNPAID',
            'ORD-MISSING',
            'ORD-PAY-MIXED',
        ], $numbers);

        $paidRow = $this->row($orders, 'ORD-PAID');
        $this->assertSame(OrderStatus::DELIVERED->value, $paidRow['delivery_status']);
        $this->assertSame(SupplierAfterDispatchWorkspaceService::PAYMENT_PAID, $paidRow['payment_status']);
        $this->assertSame('INV-PAID', $paidRow['invoice_number']);
        $this->assertSame('WB-PAID', $paidRow['waybill']);
        $this->assertSame('Supplier settlement INV-PAID', $paidRow['source']);
        $this->assertSame(
            $paidAt->copy()->timezone(config('app.timezone'))->format('d M Y, H:i'),
            $paidRow['payment_received_at']
        );
        $this->assertSame(
            $paid->delivered_at->timezone(config('app.timezone'))->toDateString(),
            $paidRow['payment_date']
        );

        $unpaidRow = $this->row($orders, 'ORD-UNPAID');
        $this->assertSame(SupplierAfterDispatchWorkspaceService::PAYMENT_UNPAID, $unpaidRow['payment_status']);
        $this->assertNull($unpaidRow['payment_received_at']);
        $this->assertSame('80.25', $unpaidRow['settlement_amount']);

        $missingRow = $this->row($orders, 'ORD-MISSING');
        $this->assertSame(SupplierAfterDispatchWorkspaceService::PAYMENT_NONE, $missingRow['payment_status']);
        $this->assertNull($missingRow['settlement_amount']);
        $this->assertNull($missingRow['waybill']);
        $this->assertNull($missingRow['invoice_number']);
        $this->assertNull($missingRow['source']);

        $mixedRow = $this->row($orders, 'ORD-PAY-MIXED');
        $this->assertSame(SupplierAfterDispatchWorkspaceService::PAYMENT_UNPAID, $mixedRow['payment_status']);
        $this->assertSame('40.00', $mixedRow['settlement_amount']);
        $this->assertSame('INV-PAY-OWN', $mixedRow['invoice_number']);

        $this->assertSame(2, $this->summaryCount($response->getContent(), 'orderPaymentsSummaryPending'));
        $this->assertSame(1, $this->summaryCount($response->getContent(), 'orderPaymentsSummaryReceived'));
        $this->assertStringContainsString('LKR 120.25', $response->getContent());
        $this->assertStringNotContainsString('7,777.00', $this->summaryAmount($response->getContent()));

        $this->assertStringContainsString('id="orderPaymentsFilterSearch"', $response->getContent());
        $this->assertStringContainsString('value="UNPAID"', $response->getContent());
        $this->assertStringContainsString('value="PAID"', $response->getContent());
        $this->assertStringContainsString('value="NONE"', $response->getContent());
        $this->assertStringContainsString('data-per-page="25"', $response->getContent());
        $this->assertStringContainsString('>City Courier<', $response->getContent());
    }

    public function test_empty_screens_have_no_mock_fallback_and_keep_filters(): void
    {
        $supplier = $this->makeSupplier(['orders.view'], 'Empty Supplier Co');
        $other = $this->makeSupplier(['orders.view'], 'Filled Supplier Co');
        $product = $this->makeProduct($other, 'Foreign Cream');
        $this->makeOrder($other, $product, OrderStatus::DELIVERED, 'ORD-NOT-MINE', 'Not Mine', '0780000099', [
            'delivered_at' => now()->subDay(),
        ]);

        foreach ([
            'orders.ui.completed' => 'supplierCompletedOrdersPage',
            'orders.ui.archived' => 'supplierArchivedOrdersPage',
            'orders.ui.order-payments' => 'supplierOrderPaymentsPage',
        ] as $route => $pageId) {
            $response = $this->actingAs($supplier)
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('data-mock-orders', false)
                ->assertDontSee('Kasun Perera')
                ->assertDontSee('ORD-NOT-MINE')
                ->assertDontSee('Royal Express')
                ->assertSee('data-per-page="25"', false)
                ->assertSee('All Couriers');

            $this->assertSame([], $this->ordersFrom($response->getContent(), $pageId));
        }

        $dispatch = $this->actingAs($supplier)
            ->get(route('orders.ui.dispatch-summary'))
            ->assertOk()
            ->assertDontSee('DO-20261005-001')
            ->assertDontSee('ORD-NOT-MINE')
            ->assertSee('data-per-page="25"', false);

        $this->assertSame([], $this->dataset($dispatch->getContent(), 'pendingCouriers'));
        $this->assertSame([], $this->dataset($dispatch->getContent(), 'dispatchHistory'));
    }

    public function test_completed_orders_json_supports_existing_search_and_page_size(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));

        $supplier = $this->makeSupplier(['orders.view'], 'Page Supplier Co');
        $product = $this->makeProduct($supplier, 'Owned Serum');
        $courier = $this->makeCourier('City Courier');

        for ($i = 1; $i <= 26; $i++) {
            $order = $this->makeOrder(
                $supplier,
                $product,
                OrderStatus::DELIVERED,
                'ORD-PAGE-'.$i,
                'Customer '.$i,
                '077'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                ['delivered_at' => now()->subDays(1)],
            );
            $this->makeShipment($order, $courier, 'WB-PAGE-'.$i, ShipmentStatus::DELIVERED);
        }

        $response = $this->actingAs($supplier)
            ->get(route('orders.ui.completed'))
            ->assertOk()
            ->assertSee('Search order, customer, phone or waybill')
            ->assertSee('id="completedFilterCourier"', false)
            ->assertSee('id="completedFilterDateFrom"', false)
            ->assertSee('data-per-page="25"', false)
            ->assertSee('id="completedOrdersPaginationHost"', false)
            ->assertSee('completed-page-btn', false);

        $orders = $this->ordersFrom($response->getContent(), 'supplierCompletedOrdersPage');
        $this->assertCount(26, $orders);
        $this->assertSame('WB-PAGE-1', $this->row($orders, 'ORD-PAGE-1')['waybill']);
        $this->assertSame('Customer 26', $this->row($orders, 'ORD-PAGE-26')['customer_name']);
        $this->assertSame(26, $this->summaryCount($response->getContent(), 'completedSummaryDelivered'));
    }

    public function test_after_dispatch_screens_require_orders_view(): void
    {
        $supplier = $this->makeSupplier(['products.view'], 'Denied Supplier Co');

        $this->actingAs($supplier)->get(route('orders.ui.completed'))->assertForbidden();
        $this->actingAs($supplier)->get(route('orders.ui.archived'))->assertForbidden();
        $this->actingAs($supplier)->get(route('orders.ui.dispatch-summary'))->assertForbidden();
        $this->actingAs($supplier)->get(route('orders.ui.order-payments'))->assertForbidden();
    }

    private function useIsolatedDatabase(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.prefix' => '',
                'database.connections.sqlite.foreign_key_constraints' => false,
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');
        } else {
            $database = 'dropshipping_supplier_after_dispatch_test';
            $this->assertNotSame('dropshipping', $database);

            $pdo = new PDO(
                'mysql:host=127.0.0.1;port=3306',
                'root',
                'admin',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec(
                "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            config([
                'database.default' => 'mysql',
                'database.connections.mysql.url' => null,
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => '3306',
                'database.connections.mysql.database' => $database,
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => 'admin',
            ]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        $activeDatabase = (string) DB::connection()->getDatabaseName();
        $this->assertNotSame('dropshipping', $activeDatabase);
        $this->assertNotSame('', $activeDatabase);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('portals', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('subdomain')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('user_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->string('module')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('allowed')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('market_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('status')->nullable();
            $table->boolean('system_visible')->default(true);
            $table->boolean('web_visible')->default(true);
            $table->boolean('price_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('order_number')->nullable();
            $table->string('source')->nullable();
            $table->string('order_type')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('market_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('market_code_snapshot')->nullable();
            $table->string('currency_code_snapshot')->nullable();
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->unsignedBigInteger('reseller_company_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('draft_courier_id')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('primary_phone_snapshot')->nullable();
            $table->unsignedBigInteger('primary_phone_country_id')->nullable();
            $table->decimal('items_subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('courier_fee_amount', 15, 2)->default(0);
            $table->decimal('customer_payable_amount', 15, 2)->default(0);
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->boolean('after_hours')->default(false);
            $table->boolean('after_hours_warning_shown')->default(false);
            $table->decimal('after_hours_penalty_amount', 15, 2)->default(0);
            $table->boolean('duplicate_warning_shown')->default(false);
            $table->boolean('duplicate_warning_overridden')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->string('barcode_snapshot')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_selling_price', 15, 2)->default(0);
            $table->decimal('unit_cost_snapshot', 15, 2)->default(0);
            $table->decimal('unit_company_commission_snapshot', 15, 2)->default(0);
            $table->decimal('unit_weight_snapshot', 12, 3)->default(0);
            $table->decimal('line_selling_total', 15, 2)->default(0);
            $table->decimal('line_weight_total', 12, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('order_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->string('status')->nullable();
            $table->string('invoice_number')->nullable();
            $table->timestamp('invoice_generated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->unsignedBigInteger('changed_by_company_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->decimal('weight_snapshot', 12, 3)->default(0);
            $table->decimal('courier_fee_snapshot', 15, 2)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('supplier_company_id')->nullable();
            $table->string('supplier_company_name_snapshot')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->decimal('payable_total', 15, 2)->default(0);
            $table->decimal('transfer_fee_amount', 15, 2)->default(0);
            $table->string('transfer_fee_status')->nullable();
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->string('payment_status')->nullable();
            $table->string('instruction_status')->nullable();
            $table->unsignedBigInteger('effective_payment_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('invoice_id');
            $table->date('settlement_date')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('order_number_snapshot')->nullable();
            $table->string('order_status_snapshot')->nullable();
            $table->unsignedBigInteger('supplier_user_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('tracking_number_snapshot')->nullable();
            $table->unsignedBigInteger('courier_service_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('line_payable', 15, 2)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->string('line_payment_status')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_returned_references', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('supplier_company_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('order_number_snapshot')->nullable();
            $table->string('order_status_snapshot')->nullable();
            $table->unsignedBigInteger('supplier_user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('display_payable', 15, 2)->default(0);
            $table->date('returned_at_business_date')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('prior_payable_invoice_id')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('payment_number')->nullable();
            $table->string('entry_type')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('supplier_company_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatch_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('dispatch_number')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_cod_amount', 15, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatch_note_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('dispatch_note_id');
            $table->unsignedBigInteger('order_id');
            $table->timestamps();
        });
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeSupplier(array $permissionSlugs, string $companyName): User
    {
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $this->portal->id,
            'name' => $companyName.' '.Str::lower(Str::random(4)),
            'email' => 'supplier-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        $role = Role::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $this->portal->id,
            'company_id' => null,
            'name' => 'Owner Test',
            'slug' => 'owner-test-'.Str::lower(Str::random(4)),
            'description' => 'Owner Test',
            'is_system' => false,
        ]);

        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->create([
                'uuid' => (string) Str::uuid(),
                'portal_id' => $this->portal->id,
                'module' => 'Orders',
                'name' => $slug,
                'slug' => $slug,
            ]);
            $permissionIds[] = $permission->id;
        }

        if ($permissionIds !== []) {
            $role->permissions()->sync($permissionIds);
        }

        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company', 'role']);
    }

    private function makeProduct(User $supplier, string $name): Product
    {
        return Product::query()->create([
            'supplier_id' => $supplier->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => ProductStatus::ACTIVE->value,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => false,
        ]);
    }

    private function makeCourier(string $name): Courier
    {
        return Courier::query()->create([
            'code' => Str::lower(Str::random(8)),
            'name' => $name,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(
        User $supplier,
        Product $product,
        OrderStatus $status,
        string $orderNumber,
        string $customerName,
        string $phone,
        array $overrides = [],
    ): Order {
        $order = Order::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'order_number' => $orderNumber,
            'source' => OrderSource::MANUAL,
            'order_type' => 'NEW',
            'status' => $status,
            'market_id' => 1,
            'currency_id' => 1,
            'market_code_snapshot' => 'lk',
            'currency_code_snapshot' => 'LKR',
            'supplier_id' => $supplier->id,
            'customer_id' => 1,
            'customer_name_snapshot' => $customerName,
            'primary_phone_snapshot' => $phone,
            'items_subtotal' => 250,
            'discount_amount' => 0,
            'courier_fee_amount' => 0,
            'customer_payable_amount' => 250,
            'total_weight' => 0.5,
            'after_hours' => false,
            'after_hours_warning_shown' => false,
            'after_hours_penalty_amount' => 0,
            'duplicate_warning_shown' => false,
            'duplicate_warning_overridden' => false,
            'created_by' => $supplier->id,
        ], $overrides));

        $this->addItem($order, $product, $product->name);

        return $order->fresh('items');
    }

    private function addItem(Order $order, Product $product, string $name): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => 1,
            'product_name_snapshot' => $name,
            'variant_name_snapshot' => 'Default',
            'barcode_snapshot' => 'BC'.$order->id.$product->id,
            'quantity' => 1,
            'unit_selling_price' => 250,
            'unit_cost_snapshot' => 100,
            'unit_company_commission_snapshot' => 20,
            'unit_weight_snapshot' => 0.5,
            'line_selling_total' => 250,
            'line_weight_total' => 0.5,
        ]);
    }

    private function makeShipment(Order $order, Courier $courier, ?string $trackingNumber, ShipmentStatus $status): Shipment
    {
        return Shipment::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'courier_id' => $courier->id,
            'tracking_number' => $trackingNumber,
            'weight_snapshot' => 0.5,
            'courier_fee_snapshot' => 0,
            'currency_id' => 1,
            'status' => $status,
            'booked_at' => now(),
        ]);
    }

    private function makeSettlementLine(
        Order $order,
        OrderItem $item,
        User $supplier,
        string $invoiceNumber,
        string $lineStatus,
        string $amount,
        ?Carbon $paidAt = null,
        string $instructionStatus = SupplierSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
    ): void {
        $invoice = SupplierSettlementInvoice::query()->create([
            'invoice_number' => $invoiceNumber,
            'supplier_company_id' => $supplier->company_id,
            'supplier_company_name_snapshot' => $supplier->company?->name,
            'currency_id' => 1,
            'currency_code_snapshot' => 'LKR',
            'payable_total' => $amount,
            'payment_status' => $lineStatus === SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID
                ? SupplierSettlementInvoice::PAYMENT_STATUS_PAID
                : SupplierSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => $instructionStatus,
            'issued_at' => now(),
        ]);

        if ($paidAt !== null && $instructionStatus !== SupplierSettlementInvoice::INSTRUCTION_STATUS_VOIDED) {
            $payment = SupplierPayment::query()->create([
                'payment_number' => 'PAY-'.$invoice->id,
                'entry_type' => SupplierPayment::ENTRY_TYPE_PAYMENT,
                'invoice_id' => $invoice->id,
                'supplier_company_id' => $supplier->company_id,
                'currency_id' => 1,
                'currency_code_snapshot' => 'LKR',
                'amount' => $amount,
                'confirmed_at' => $paidAt,
                'confirmed_by' => $supplier->id,
            ]);
            $invoice->forceFill(['effective_payment_id' => $payment->id])->save();
        }

        SupplierSettlementInvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'settlement_date' => now()->toDateString(),
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'order_number_snapshot' => $order->order_number,
            'order_status_snapshot' => OrderStatus::DELIVERED->value,
            'supplier_user_id' => $supplier->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name_snapshot' => $item->product_name_snapshot,
            'variant_name_snapshot' => $item->variant_name_snapshot,
            'quantity' => 1,
            'unit_cost' => $amount,
            'line_payable' => $amount,
            'currency_id' => 1,
            'currency_code_snapshot' => 'LKR',
            'line_payment_status' => $lineStatus,
        ]);
    }

    private function makeReturnedReference(Order $order, OrderItem $item, User $supplier): void
    {
        SupplierSettlementReturnedReference::query()->create([
            'supplier_company_id' => $supplier->company_id,
            'currency_id' => 1,
            'currency_code_snapshot' => 'LKR',
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'order_number_snapshot' => $order->order_number,
            'order_status_snapshot' => OrderStatus::RETURNED->value,
            'supplier_user_id' => $supplier->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name_snapshot' => $item->product_name_snapshot,
            'variant_name_snapshot' => $item->variant_name_snapshot,
            'quantity' => 1,
            'unit_cost' => '100.00',
            'display_payable' => '0.00',
            'returned_at_business_date' => now()->toDateString(),
            'source' => SupplierSettlementReturnedReference::SOURCE_PRE_LOCK,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordersFrom(string $html, string $pageId): array
    {
        $this->assertSame(1, preg_match('/id="'.preg_quote($pageId, '/').'"[^>]*data-orders=\'([^\']*)\'/', $html, $matches));
        $orders = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($orders);

        return $orders;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataset(string $html, string $attribute): array
    {
        $attribute = strtolower((string) preg_replace('/[A-Z]/', '-$0', $attribute));

        $this->assertSame(
            1,
            preg_match('/data-'.preg_quote($attribute, '/').'=\'([^\']*)\'/', $html, $matches)
        );
        $rows = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($rows);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return array<string, mixed>
     */
    private function row(array $orders, string $orderNumber): array
    {
        foreach ($orders as $order) {
            if (($order['order_number'] ?? null) === $orderNumber) {
                return $order;
            }
        }

        $this->fail('Order '.$orderNumber.' was not in the payload.');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function namedRow(array $rows, string $name): array
    {
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $name) {
                return $row;
            }
        }

        $this->fail('Row '.$name.' was not in the payload.');
    }

    private function summaryCount(string $html, string $elementId): int
    {
        $this->assertSame(
            1,
            preg_match('/id="'.preg_quote($elementId, '/').'">([^<]*)</', $html, $matches)
        );

        return (int) str_replace(',', '', trim($matches[1]));
    }

    private function summaryAmount(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/id="orderPaymentsSummaryAmount">([^<]*)</', $html, $matches)
        );

        return trim(preg_replace('/\s+/', ' ', $matches[1]) ?? '');
    }
}
