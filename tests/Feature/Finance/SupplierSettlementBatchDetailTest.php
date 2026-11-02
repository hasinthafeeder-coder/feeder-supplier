<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\SupplierFinancialOverviewReadService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Customer;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\SupplierSettlementBatch;
use Feeder\Core\Models\SupplierSettlementInvoice;
use Feeder\Core\Models\SupplierSettlementInvoiceLine;
use Feeder\Core\Models\User;
use Feeder\Core\Services\PortalFinancialOverviewMarkets;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * Supplier financial overview batch detail against the development MySQL database.
 * Transactional; rolled back after each test.
 */
class SupplierSettlementBatchDetailTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_batch_detail_uses_persisted_line_snapshots_reconciles_total_and_blocks_foreign_batches(): void
    {
        $currency = Currency::query()->where('iso_code', 'LKR')->firstOrFail();
        $market = Market::query()->where('code', 'lk')->firstOrFail();
        $owner = $this->makeSupplierWithPermission(['dashboard.view']);
        $stranger = $this->makeSupplierWithPermission(['dashboard.view']);

        $batch = $this->makeBatch($market, $owner, 'SSB-DET-', '2026-10-15', 41);
        $foreignBatch = $this->makeBatch($market, $stranger, 'SSB-FRN-', '2026-10-16', 42);

        $ownInvoice = $this->makeInvoice($batch, $owner, $currency, 'SSI-DET-', '9999.00');
        $foreignInvoice = $this->makeInvoice($foreignBatch, $stranger, $currency, 'SSI-FRN-', '888.00');

        $customer = Customer::query()->create([
            'uuid' => UuidService::generate(),
            'display_name' => 'Batch Detail Customer',
            'primary_country_id' => $market->country_id,
            'is_banned' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $order = Order::query()->create([
            'uuid' => UuidService::generate(),
            'order_number' => 'ORD-SBD-'.Str::upper(Str::random(4)),
            'source' => OrderSource::MANUAL,
            'status' => OrderStatus::DELIVERED,
            'market_id' => $market->id,
            'currency_id' => $currency->id,
            'market_code_snapshot' => 'lk',
            'currency_code_snapshot' => 'LKR',
            'reseller_id' => $owner->id,
            'reseller_company_id' => $owner->company_id,
            'supplier_id' => $owner->id,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => 'Batch Detail Customer',
            'primary_phone_snapshot' => '070'.random_int(1000000, 9999999),
            'primary_phone_country_id' => $market->country_id,
            'delivered_at' => '2026-10-10 09:15:00',
            'items_subtotal' => '500.00',
            'discount_amount' => '0.00',
            'courier_fee_amount' => '0.00',
            'customer_payable_amount' => '500.00',
            'total_weight' => '0.500',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->insertOwnedLine($ownInvoice, $owner, $currency, [
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'tracking_number_snapshot' => 'WB-SBD-1001',
            'product_name_snapshot' => 'Historic Widget',
            'variant_name_snapshot' => 'Blue',
            'quantity' => 2,
            'unit_cost' => '125.50',
            'line_payable' => '251.00',
            'settlement_date' => '2026-10-15',
        ]);

        $this->insertOwnedLine($ownInvoice, $owner, $currency, [
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'tracking_number_snapshot' => 'WB-SBD-1002',
            'product_name_snapshot' => 'Historic Gadget',
            'variant_name_snapshot' => 'Red',
            'quantity' => 1,
            'unit_cost' => '49.00',
            'line_payable' => '49.00',
            'settlement_date' => '2026-10-15',
        ]);

        $this->insertOwnedLine($foreignInvoice, $stranger, $currency, [
            'order_number_snapshot' => 'ORD-FOREIGN',
            'tracking_number_snapshot' => 'WB-FOREIGN',
            'product_name_snapshot' => 'Foreign Product',
            'variant_name_snapshot' => 'X',
            'quantity' => 9,
            'unit_cost' => '88.00',
            'line_payable' => '888.00',
            'settlement_date' => '2026-10-16',
        ]);

        $service = app(SupplierFinancialOverviewReadService::class);
        $detail = $service->loadOwnedBatchDetail($owner, $batch);
        $rows = $detail['lines']->getCollection();

        $this->assertSame('300.00', $detail['summary']['net_payable']);
        $this->assertSame('LKR', $detail['summary']['currency_code']);
        $this->assertSame(2, $detail['summary']['line_count']);
        $this->assertSame(2, $rows->count());
        $this->assertSame($order->order_number, $rows[0]['order_number']);
        $this->assertSame('WB-SBD-1001', $rows[0]['waybill']);
        $this->assertSame('Historic Widget', $rows[0]['product_name']);
        $this->assertSame('Blue', $rows[0]['variant_name']);
        $this->assertSame(2, $rows[0]['quantity']);
        $this->assertSame('125.50', $rows[0]['unit_cost']);
        $this->assertSame('251.00', $rows[0]['line_payable']);
        $this->assertSame('DELIVERED', $rows[0]['order_status']);
        $this->assertSame($order->created_at->format('Y-m-d'), $rows[0]['order_date']);
        $this->assertArrayNotHasKey('payable_total', $rows[0]);
        $this->assertArrayNotHasKey('amount_paid', $rows[0]);
        $this->assertArrayNotHasKey('outstanding', $rows[0]);

        $overview = $service->forOverview(
            $owner,
            app(PortalFinancialOverviewMarkets::class)->active(),
            null,
            null,
            15,
        );
        $overviewRow = collect($overview['rows']->items())
            ->first(fn (array $row): bool => ($row['batch_uuid'] ?? '') === $batch->uuid);

        $this->assertNotNull($overviewRow);
        $this->assertSame($detail['summary']['net_payable'], $overviewRow['net_payable']);

        $html = $this->actingAs($owner)
            ->get(route('finance.batches.show', $batch))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Order-wise breakdown', $html);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('WB-SBD-1001', $html);
        $this->assertStringContainsString('Historic Widget', $html);
        $this->assertStringContainsString('125.50', $html);
        $this->assertStringContainsString('251.00', $html);
        $this->assertStringContainsString('49.00', $html);
        $this->assertStringContainsString('Back to financial overview', $html);
        $this->assertStringNotContainsString('9999.00', $html);
        $this->assertStringNotContainsString('ORD-FOREIGN', $html);
        $this->assertStringNotContainsString('888.00', $html);
        $this->assertStringNotContainsString('Foreign Product', $html);

        $overviewHtml = $this->actingAs($owner)
            ->get(route('finance.overview'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>View</a>', $overviewHtml);
        $this->assertStringContainsString(route('finance.batches.show', $batch), $overviewHtml);

        try {
            $service->loadOwnedBatchDetail($owner, $foreignBatch);
            $this->fail('Another supplier batch must stay hidden.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        $this->actingAs($owner)
            ->get(route('finance.batches.show', $foreignBatch))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->get(route('finance.batches.show', $batch))
            ->assertNotFound();
    }

    public function test_batch_detail_paginates_lines_and_preserves_back_navigation(): void
    {
        $currency = Currency::query()->where('iso_code', 'LKR')->firstOrFail();
        $market = Market::query()->where('code', 'lk')->firstOrFail();
        $owner = $this->makeSupplierWithPermission(['dashboard.view']);

        $batch = $this->makeBatch($market, $owner, 'SSB-PAGE-', '2026-10-20', 55);
        $invoice = $this->makeInvoice($batch, $owner, $currency, 'SSI-PAGE-', '26.00');

        for ($i = 1; $i <= 26; $i++) {
            $this->insertOwnedLine($invoice, $owner, $currency, [
                'order_number_snapshot' => 'ORD-PAGE-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'tracking_number_snapshot' => 'WB-PAGE-'.$i,
                'product_name_snapshot' => 'Product '.$i,
                'variant_name_snapshot' => 'V'.$i,
                'quantity' => 1,
                'unit_cost' => '1.00',
                'line_payable' => '1.00',
                'settlement_date' => '2026-10-20',
            ]);
        }

        $first = $this->actingAs($owner)
            ->get(route('finance.batches.show', $batch))
            ->assertOk()
            ->getContent();

        $second = $this->actingAs($owner)
            ->get(route('finance.batches.show', ['batch' => $batch, 'page' => 2]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Showing 1 to 25', $first);
        $this->assertStringContainsString('of 26 entries', $first);
        $this->assertStringContainsString('ORD-PAGE-01', $first);
        $this->assertStringNotContainsString('ORD-PAGE-26', $first);
        $this->assertStringContainsString('ORD-PAGE-26', $second);
        $this->assertStringContainsString('Back to financial overview', $first);
        $this->assertStringContainsString(route('finance.overview'), $first);
        $this->assertStringContainsString('26.00', $first);
    }

    private function makeBatch(Market $market, User $actor, string $prefix, string $date, int $sequence): SupplierSettlementBatch
    {
        return SupplierSettlementBatch::query()->create([
            'uuid' => UuidService::generate(),
            'batch_number' => $prefix.Str::upper(Str::random(5)),
            'market_id' => $market->id,
            'market_code_snapshot' => 'LK',
            'timezone_snapshot' => 'Asia/Colombo',
            'settlement_date' => $date,
            'kind' => SupplierSettlementBatch::KIND_DAILY,
            'sequence' => $sequence,
            'status' => SupplierSettlementBatch::STATUS_LOCKED,
            'generation_source' => SupplierSettlementBatch::GENERATION_SOURCE_MANUAL,
            'generated_by' => $actor->id,
            'generated_at' => now(),
            'locked_by' => $actor->id,
            'locked_at' => now(),
        ]);
    }

    private function makeInvoice(
        SupplierSettlementBatch $batch,
        User $supplier,
        Currency $currency,
        string $prefix,
        string $payableTotal,
    ): SupplierSettlementInvoice {
        return SupplierSettlementInvoice::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_number' => $prefix.Str::upper(Str::random(5)),
            'batch_id' => $batch->id,
            'supplier_company_id' => $supplier->company_id,
            'supplier_company_name_snapshot' => $supplier->company?->name ?: 'Supplier',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => $currency->iso_code,
            'payable_total' => $payableTotal,
            'payment_status' => SupplierSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => SupplierSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertOwnedLine(
        SupplierSettlementInvoice $invoice,
        User $supplier,
        Currency $currency,
        array $overrides,
    ): void {
        $suffix = (int) (microtime(true) * 1000000) + random_int(1, 999999);

        $payload = array_merge([
            'uuid' => UuidService::generate(),
            'invoice_id' => $invoice->id,
            'settlement_date' => '2026-10-15',
            'order_id' => 900000000 + ($suffix % 100000000),
            'order_item_id' => 910000000 + ($suffix % 100000000),
            'order_number_snapshot' => 'ORD-TMP',
            'order_status_snapshot' => 'DELIVERED',
            'supplier_user_id' => $supplier->id,
            'shipment_id' => 920000000 + ($suffix % 100000000),
            'tracking_number_snapshot' => 'WB-TMP',
            'courier_service_id' => 940000000 + ($suffix % 100000000),
            'courier_receipt_import_id' => 950000000 + ($suffix % 100000000),
            'courier_receipt_import_row_id' => 930000000 + ($suffix % 100000000),
            'product_id' => 960000000 + ($suffix % 100000000),
            'product_variant_id' => 970000000 + ($suffix % 100000000),
            'product_name_snapshot' => 'Product',
            'variant_name_snapshot' => 'Variant',
            'quantity' => 1,
            'unit_cost' => '1.00',
            'line_payable' => '1.00',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => $currency->iso_code,
            'line_payment_status' => SupplierSettlementInvoiceLine::LINE_PAYMENT_STATUS_UNPAID,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('supplier_settlement_invoice_lines')->insert($payload);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeSupplierWithPermission(array $permissionSlugs): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Supplier Portal',
                'subdomain' => 'supplier-'.Str::lower(Str::random(4)),
                'description' => 'Supplier Portal',
                'is_active' => true,
            ]
        );

        $market = Market::query()->where('code', 'lk')->firstOrFail();

        $company = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'name' => 'Batch Detail Co '.Str::lower(Str::random(4)),
            'email' => 'batch-detail-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
            'operation_market_id' => $market->id,
        ]);

        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'batch-detail-owner-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Batch Detail Owner Test',
                'description' => 'Batch Detail Owner Test',
                'is_system' => false,
            ]
        );

        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                [
                    'portal_id' => $portal->id,
                    'slug' => $slug,
                ],
                [
                    'uuid' => UuidService::generate(),
                    'module' => 'Dashboard',
                    'group' => 'Dashboard',
                    'name' => $slug,
                    'description' => null,
                    'sort_order' => 10,
                ]
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company', 'role']);
    }
}
