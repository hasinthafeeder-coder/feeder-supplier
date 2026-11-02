<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\SupplierFinancialOverviewReadService;
use Carbon\Carbon;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\User;
use Feeder\Core\Services\PortalFinancialOverviewMarkets;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Supplier financial overview reads, isolated on in-memory SQLite.
 *
 * This test never opens the shared dropshipping database.
 */
class SupplierFinancialOverviewReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedDatabase();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_markets_keep_independent_totals_and_business_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 16:30:00', 'UTC'));
        $this->seedMarkets(true, true);
        $lkSupplier = $this->makeSupplier('LK Co', null, 'lk');
        $mySupplier = $this->makeSupplier('MY Co', null, 'my');
        $lkProduct = $this->makeProduct($lkSupplier->id);
        $myProduct = $this->makeProduct($mySupplier->id);

        $this->insertOwnedLine($lkSupplier, $lkProduct, 'TODAY-LK', 'LK', '2026-10-24', '11.00', 'PAID', '999.00', '2020-01-01');
        $this->insertOwnedLine($lkSupplier, $lkProduct, 'OLD-LK', 'LK', '2026-10-01', '13.00', 'UNPAID', '999.00', '2026-10-24');
        $this->insertOwnedLine($mySupplier, $myProduct, 'TODAY-MY', 'MY', '2026-10-25', '17.00', 'UNPAID', '999.00', '2026-10-24');
        $this->insertOwnedLine($mySupplier, $myProduct, 'OLD-MY', 'MY', '2026-10-01', '19.00', 'PAID', '999.00', '2026-10-25');

        $lkOverview = $this->overviewFor($lkSupplier);
        $myOverview = $this->overviewFor($mySupplier);
        $lk = $lkOverview['summaries']['lk'];
        $my = $myOverview['summaries']['my'];

        $this->assertSame('LKR', $lk['currency_code']);
        $this->assertSame('24.00', $lk['total_payable']);
        $this->assertSame('11.00', $lk['total_paid']);
        $this->assertSame('13.00', $lk['outstanding']);
        $this->assertSame('11.00', $lk['today_payable']);
        $this->assertSame('2026-10-24', $lk['business_date']);

        $this->assertSame('MYR', $my['currency_code']);
        $this->assertSame('36.00', $my['total_payable']);
        $this->assertSame('19.00', $my['total_paid']);
        $this->assertSame('17.00', $my['outstanding']);
        $this->assertSame('17.00', $my['today_payable']);
        $this->assertSame('2026-10-25', $my['business_date']);

        $lkHtml = $this->actingAs($lkSupplier)->get(route('finance.overview'))->assertOk()->getContent();
        $sriLanka = $this->section($lkHtml, 'financial-overview-market-lk');
        $this->assertStringContainsString('24.00', $sriLanka);
        $this->assertStringContainsString('2026-10-24', $sriLanka);
        $this->assertStringNotContainsString('MYR', $sriLanka);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $lkHtml);
        $this->assertStringNotContainsString('999.00', $lkHtml);
        $this->assertStringNotContainsString('Pending integration', $lkHtml);

        $myHtml = $this->actingAs($mySupplier)->get(route('finance.overview'))->assertOk()->getContent();
        $malaysia = $this->section($myHtml, 'financial-overview-market-my');
        $this->assertStringContainsString('36.00', $malaysia);
        $this->assertStringContainsString('2026-10-25', $malaysia);
        $this->assertStringNotContainsString('LKR', $malaysia);
        $this->assertStringNotContainsString('id="financial-overview-market-lk"', $myHtml);
    }

    public function test_unregistered_market_cards_stay_hidden_even_when_lines_exist(): void
    {
        $this->seedMarkets(true, true);
        $supplier = $this->makeSupplier('LK Only Co', null, 'lk');
        $product = $this->makeProduct($supplier->id);
        $this->insertOwnedLine($supplier, $product, 'LK-LINE', 'LK', '2026-10-02', '12.00', 'UNPAID', '12.00');
        $this->insertOwnedLine($supplier, $product, 'MY-LINE', 'MY', '2026-10-02', '44.00', 'UNPAID', '44.00');

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('id="financial-overview-market-lk"', $html);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $html);
        $this->assertStringContainsString('12.00', $html);
        $this->assertStringContainsString('>View</a>', $html);
        $this->assertStringContainsString('/finance/overview/batches/', $html);
        $this->assertStringNotContainsString('Summary cards total your line amounts', $html);
        $this->assertStringNotContainsString('There is no supplier batch or invoice page', $html);
        $this->assertStringNotContainsString('orders/order-payments', $html);
    }

    public function test_inactive_market_cards_stay_hidden(): void
    {
        $this->seedMarkets(true, false);
        $supplier = $this->makeSupplier('Same Co', null, 'lk');
        $product = $this->makeProduct($supplier->id);
        $this->insertOwnedLine($supplier, $product, 'LK-ONLY', 'LK', '2026-10-02', '12.00', 'UNPAID', '12.00');
        $this->insertOwnedLine($supplier, $product, 'MY-HIDDEN', 'MY', '2026-10-02', '44.00', 'UNPAID', '44.00');

        $overview = $this->overviewFor($supplier);

        $this->assertArrayHasKey('lk', $overview['summaries']);
        $this->assertArrayNotHasKey('my', $overview['summaries']);
        $this->assertSame('12.00', $overview['summaries']['lk']['total_payable']);

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('id="financial-overview-market-lk"', $html);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $html);
        $this->assertStringNotContainsString('id="my-supplier-total-payable-card"', $html);
        $this->assertSame(4, substr_count($html, 'h-100 finance-today-card'));
    }

    public function test_same_company_suppliers_only_see_their_own_lines(): void
    {
        $this->seedMarkets(true, false);
        $companyId = $this->insertCompany('Shared Supplier Co');
        $owner = $this->makeSupplier('Shared Supplier Co', $companyId);
        $colleague = $this->makeSupplier('Shared Supplier Co', $companyId);
        $ownerProduct = $this->makeProduct($owner->id);
        $colleagueProduct = $this->makeProduct($colleague->id);
        $sharedInvoice = $this->insertInvoice($companyId, 'LK', 'SHARED-1', '2026-10-02', 'PAID', '999.00');

        $this->insertLine($sharedInvoice, $owner->id, $ownerProduct, '10.00', '2026-10-02');
        $this->insertLine($sharedInvoice, $colleague->id, $colleagueProduct, '40.00', '2026-10-02');
        DB::table('supplier_payments')->insert([
            'invoice_id' => $sharedInvoice['invoice_id'],
            'entry_type' => 'PAYMENT',
            'amount' => '999.00',
            'bank_reference' => 'COMPANY-PAYMENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownerOverview = $this->overviewFor($owner);
        $colleagueOverview = $this->overviewFor($colleague);

        $this->assertSame('10.00', $ownerOverview['summaries']['lk']['total_payable']);
        $this->assertSame('10.00', $ownerOverview['summaries']['lk']['total_paid']);
        $this->assertSame('40.00', $colleagueOverview['summaries']['lk']['total_payable']);
        $this->assertSame('0.00', $ownerOverview['summaries']['lk']['outstanding']);

        $ownerHtml = $this->actingAs($owner)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('10.00', $ownerHtml);
        $this->assertStringNotContainsString('40.00', $ownerHtml);
        $this->assertStringNotContainsString('999.00', $ownerHtml);
        $this->assertStringNotContainsString('COMPANY-PAYMENT', $ownerHtml);

        $colleagueHtml = $this->actingAs($colleague)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('40.00', $colleagueHtml);
        $this->assertStringNotContainsString('10.00', $colleagueHtml);
    }

    public function test_product_owner_check_drops_another_suppliers_product(): void
    {
        $this->seedMarkets(true, false);
        $owner = $this->makeSupplier('Owner Co');
        $other = $this->makeSupplier('Other Co');
        $ownedProduct = $this->makeProduct($owner->id);
        $foreignProduct = $this->makeProduct($other->id);
        $unassignedProduct = $this->makeProduct(null);
        $deletedForeign = $this->makeProduct($other->id, now()->toDateTimeString());

        $this->insertOwnedLine($owner, $ownedProduct, 'OWNED', 'LK', '2026-10-02', '5.00', 'UNPAID', '5.00');
        $this->insertOwnedLine($owner, $foreignProduct, 'FOREIGN', 'LK', '2026-10-02', '77.00', 'UNPAID', '77.00');
        $this->insertOwnedLine($owner, $unassignedProduct, 'UNASSIGNED', 'LK', '2026-10-02', '3.00', 'UNPAID', '3.00');
        $this->insertOwnedLine($owner, null, 'MISSING', 'LK', '2026-10-02', '8.00', 'UNPAID', '8.00');
        $this->insertOwnedLine($owner, $deletedForeign, 'DELETED', 'LK', '2026-10-02', '2.00', 'UNPAID', '2.00');

        $overview = $this->overviewFor($owner);

        $this->assertSame('18.00', $overview['summaries']['lk']['total_payable']);
        $batchNumbers = array_column($overview['rows']->items(), 'batch_number');
        $this->assertNotContains('FOREIGN', $batchNumbers);
        $this->assertContains('DELETED', $batchNumbers);
        $this->assertContains('MISSING', $batchNumbers);
    }

    public function test_paid_unpaid_reversed_and_non_payment_records_stay_separate(): void
    {
        $this->seedMarkets(true, false);
        $supplier = $this->makeSupplier('Pay Co');
        $product = $this->makeProduct($supplier->id);
        $paid = $this->insertOwnedLine($supplier, $product, 'PAID-BATCH', 'LK', '2026-10-02', '21.00', 'PAID', '500.00');
        $this->insertOwnedLine($supplier, $product, 'OPEN-BATCH', 'LK', '2026-10-03', '22.00', 'UNPAID', '22.00');
        $reversed = $this->insertOwnedLine($supplier, $product, 'REVERSED-BATCH', 'LK', '2026-10-04', '15.50', 'UNPAID', '15.50');

        DB::table('supplier_payments')->insert([
            [
                'invoice_id' => $paid['invoice_id'],
                'entry_type' => 'PAYMENT',
                'amount' => '500.00',
                'bank_reference' => 'PAID-REF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'invoice_id' => $reversed['invoice_id'],
                'entry_type' => 'PAYMENT',
                'amount' => '800.00',
                'bank_reference' => 'REVERSED-REF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'invoice_id' => $reversed['invoice_id'],
                'entry_type' => 'REVERSAL',
                'amount' => '800.00',
                'bank_reference' => 'REVERSAL-REF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('supplier_settlement_bank_export_lines')->insert([
            'invoice_id' => $reversed['invoice_id'],
            'amount' => '5678.00',
            'reference_text' => 'BANK-EXPORT-REF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('supplier_settlement_returned_references')->insert([
            'supplier_user_id' => $supplier->id,
            'display_payable' => '1234.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lk = $this->overviewFor($supplier)['summaries']['lk'];

        $this->assertSame('58.50', $lk['total_payable']);
        $this->assertSame('21.00', $lk['total_paid']);
        $this->assertSame('37.50', $lk['outstanding']);

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringNotContainsString('500.00', $html);
        $this->assertStringNotContainsString('800.00', $html);
        $this->assertStringNotContainsString('5678.00', $html);
        $this->assertStringNotContainsString('1234.00', $html);
        $this->assertStringNotContainsString('BANK-EXPORT-REF', $html);
        $this->assertStringNotContainsString('REVERSED-REF', $html);
        $this->assertStringContainsString('REVERSED-BATCH', $html);
        $this->assertStringContainsString('UNPAID', $html);
    }

    public function test_voided_invoices_batches_and_instructions_are_excluded(): void
    {
        $this->seedMarkets(true, false);
        $supplier = $this->makeSupplier('Void Co');
        $product = $this->makeProduct($supplier->id);
        $this->insertOwnedLine($supplier, $product, 'LIVE', 'LK', '2026-10-02', '6.00', 'UNPAID', '6.00');
        $this->insertOwnedLine($supplier, $product, 'VOID-INVOICE', 'LK', '2026-10-02', '1000.00', 'UNPAID', '1000.00', null, [
            'invoice_voided_at' => now(),
        ]);
        $this->insertOwnedLine($supplier, $product, 'VOID-BATCH', 'LK', '2026-10-02', '2000.00', 'PAID', '2000.00', null, [
            'batch_voided_at' => now(),
        ]);
        $this->insertOwnedLine($supplier, $product, 'VOID-INSTRUCTION', 'LK', '2026-10-02', '3000.00', 'PAID', '3000.00', null, [
            'instruction_status' => 'VOIDED',
        ]);

        $overview = $this->overviewFor($supplier);

        $this->assertSame('6.00', $overview['summaries']['lk']['total_payable']);
        $this->assertSame('0.00', $overview['summaries']['lk']['total_paid']);
        $batchNumbers = array_column($overview['rows']->items(), 'batch_number');
        $this->assertSame(['LIVE'], $batchNumbers);
    }

    public function test_date_filter_limits_the_table_and_not_the_cards(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 16:30:00', 'UTC'));
        $this->seedMarkets(true, true);
        $supplier = $this->makeSupplier('Filter Co');
        $product = $this->makeProduct($supplier->id);
        $this->insertOwnedLine($supplier, $product, 'KEEP', 'LK', '2026-10-24', '11.00', 'UNPAID', '11.00');
        $this->insertOwnedLine($supplier, $product, 'DROP', 'LK', '2026-10-01', '13.00', 'UNPAID', '13.00');

        $filtered = $this->actingAs($supplier)->get(route('finance.overview', [
            'date_from' => '2026-10-24',
            'date_to' => '2026-10-24',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('24.00', $filtered);
        $this->assertStringContainsString('KEEP', $filtered);
        $this->assertStringNotContainsString('DROP', $filtered);
        $this->assertStringNotContainsString('13.00', $filtered);
        $this->assertStringContainsString('Instruction ACTIVE', $filtered);
        $this->assertStringContainsString('Batch LOCKED', $filtered);
    }

    public function test_table_paginates_fifteen_rows(): void
    {
        $this->seedMarkets(true, false);
        $supplier = $this->makeSupplier('Page Co');
        $product = $this->makeProduct($supplier->id);

        for ($day = 1; $day <= 16; $day++) {
            $this->insertOwnedLine(
                $supplier,
                $product,
                'BATCH-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'LK',
                sprintf('2026-10-%02d', $day),
                '1.00',
                'UNPAID',
                '1.00',
            );
        }

        $first = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();
        $second = $this->actingAs($supplier)->get(route('finance.overview', ['page' => 2]))->assertOk()->getContent();

        $this->assertStringContainsString('Showing 1 to 15', $first);
        $this->assertStringContainsString('of 16 entries', $first);
        $this->assertStringContainsString('BATCH-16', $first);
        $this->assertStringNotContainsString('BATCH-01', $first);
        $this->assertStringContainsString('BATCH-01', $second);
        $this->assertStringContainsString('16.00', $first);
    }

    public function test_empty_dataset_renders_zero_cards_and_the_empty_table(): void
    {
        $this->seedMarkets(true, true);
        $supplier = $this->makeSupplier('Empty Co', null, 'lk');

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'h-100 finance-today-card'));
        $this->assertSame(4, substr_count($html, '0.00'));
        $this->assertStringContainsString('id="financial-overview-market-lk"', $html);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $html);
        $this->assertStringNotContainsString('Pending integration', $html);
        $this->assertStringContainsString('No batch rows are loaded. Amounts are not shown.', $html);
    }

    public function test_cards_are_omitted_when_neither_market_is_active(): void
    {
        $this->seedMarkets(false, false);
        $supplier = $this->makeSupplier('No Market Co', null, 'lk');

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('No eligible Sri Lanka or Malaysia market is available for your account.', $html);
        $this->assertStringNotContainsString('h-100 finance-today-card', $html);
        $this->assertStringContainsString('No batch rows are loaded. Amounts are not shown.', $html);
    }

    public function test_unrecognized_payment_status_is_excluded_and_explained(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 16:30:00', 'UTC'));
        $this->seedMarkets(true, false);
        $supplier = $this->makeSupplier('Status Co');
        $product = $this->makeProduct($supplier->id);
        $this->insertOwnedLine($supplier, $product, 'PAID-KNOWN', 'LK', '2026-10-24', '10.00', 'PAID', '10.00');
        $this->insertOwnedLine($supplier, $product, 'UNPAID-KNOWN', 'LK', '2026-10-24', '20.00', 'UNPAID', '20.00');
        $this->insertOwnedLine($supplier, $product, 'UNKNOWN-STATUS', 'LK', '2026-10-24', '7.00', 'HOLD', '7.00');

        $lk = $this->overviewFor($supplier)['summaries']['lk'];

        $this->assertSame('30.00', $lk['total_payable']);
        $this->assertSame('10.00', $lk['total_paid']);
        $this->assertSame('20.00', $lk['outstanding']);
        $this->assertSame('30.00', $lk['today_payable']);
        $this->assertSame(
            ['An invoice payment status is not PAID or UNPAID. That amount is not included in these totals.'],
            $lk['notices'],
        );

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();
        $sriLanka = $this->section($html, 'financial-overview-market-lk');

        $this->assertStringContainsString('An invoice payment status is not PAID or UNPAID. That amount is not included in these totals.', $sriLanka);
        $this->assertStringContainsString('30.00', $sriLanka);
        $this->assertStringNotContainsString('7.00', $sriLanka);
        $this->assertStringContainsString('UNKNOWN-STATUS', $html);
        $this->assertStringContainsString('HOLD', $html);
        $this->assertStringContainsString('Payment status is not PAID or UNPAID. Not included in the summary totals.', $html);
        $this->assertStringContainsString('7.00', $html);
    }

    public function test_line_currency_mismatch_stays_out_of_market_totals_with_a_warning(): void
    {
        $this->seedMarkets(true, true);
        $supplier = $this->makeSupplier('Currency Co', null, 'lk');
        $product = $this->makeProduct($supplier->id);
        $this->insertOwnedLine($supplier, $product, 'LKR-LINE', 'LK', '2026-10-08', '10.00', 'UNPAID', '10.00');
        $mismatched = $this->insertOwnedLine($supplier, $product, 'USD-LINE', 'LK', '2026-10-08', '8.00', 'UNPAID', '8.00');
        DB::table('supplier_settlement_invoice_lines')
            ->where('invoice_id', $mismatched['invoice_id'])
            ->update(['currency_code_snapshot' => 'USD']);

        $overview = $this->overviewFor($supplier);

        $this->assertSame('10.00', $overview['summaries']['lk']['total_payable']);
        $this->assertSame('10.00', $overview['summaries']['lk']['outstanding']);
        $this->assertSame('0.00', $overview['summaries']['my']['total_payable']);
        $this->assertSame(
            ['A settlement line is stored in USD, not LKR. That amount is not included in these totals.'],
            $overview['summaries']['lk']['notices'],
        );
        $this->assertSame([], $overview['summaries']['my']['notices']);

        $html = $this->actingAs($supplier)->get(route('finance.overview'))->assertOk()->getContent();
        $sriLanka = $this->section($html, 'financial-overview-market-lk');

        $this->assertStringContainsString('A settlement line is stored in USD, not LKR. That amount is not included in these totals.', $sriLanka);
        $this->assertStringContainsString('10.00', $sriLanka);
        $this->assertStringNotContainsString('8.00', $sriLanka);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $html);
        $this->assertStringContainsString('USD-LINE', $html);
        $this->assertStringContainsString('Stored in USD. Not included in the LKR market totals.', $html);
        $this->assertStringContainsString('8.00', $html);
    }

    private function useIsolatedDatabase(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for this test.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertNotSame('dropshipping', (string) DB::connection()->getDatabaseName());
        $this->assertSame(':memory:', (string) config('database.connections.sqlite.database'));
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('portals', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedBigInteger('operation_market_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
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
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
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

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('iso_code', 3);
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('markets', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_settlement_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('batch_number')->nullable();
            $table->unsignedBigInteger('market_id')->nullable();
            $table->string('market_code_snapshot')->nullable();
            $table->date('settlement_date')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('supplier_company_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->decimal('payable_total', 15, 2)->default(0);
            $table->string('payment_status')->nullable();
            $table->string('instruction_status')->nullable();
            $table->unsignedBigInteger('effective_payment_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->date('settlement_date')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number_snapshot')->nullable();
            $table->string('tracking_number_snapshot')->nullable();
            $table->string('order_status_snapshot')->nullable();
            $table->unsignedBigInteger('supplier_user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('line_payable', 15, 2)->default(0);
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->string('line_payment_status')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('entry_type')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('bank_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_bank_export_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference_text')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlement_returned_references', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_user_id')->nullable();
            $table->decimal('display_payable', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    private function seedMarkets(bool $sriLankaActive, bool $malaysiaActive): void
    {
        $lkr = Currency::query()->create(['iso_code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'is_active' => true]);
        $myr = Currency::query()->create(['iso_code' => 'MYR', 'name' => 'Malaysian Ringgit', 'is_active' => true]);

        Market::query()->create([
            'code' => 'lk',
            'name' => 'Sri Lanka',
            'currency_id' => $lkr->id,
            'is_active' => $sriLankaActive,
        ]);
        Market::query()->create([
            'code' => 'my',
            'name' => 'Malaysia',
            'currency_id' => $myr->id,
            'is_active' => $malaysiaActive,
        ]);
    }

    private function makeSupplier(string $companyName, ?int $companyId = null, string $marketCode = 'lk'): User
    {
        $portalId = DB::table('portals')->where('code', 'SUPPLIER')->value('id');

        if ($portalId === null) {
            $portalId = DB::table('portals')->insertGetId([
                'code' => 'SUPPLIER',
                'name' => 'Supplier',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $companyId ??= $this->insertCompany($companyName, (int) $portalId, $marketCode);
        $this->assignOperationMarket($companyId, $marketCode);

        $roleId = DB::table('roles')->insertGetId([
            'portal_id' => $portalId,
            'name' => 'Supplier',
            'slug' => 'supplier-'.$companyId.'-'.DB::table('roles')->count(),
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('slug', 'dashboard.view')->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'portal_id' => $portalId,
                'name' => 'Dashboard',
                'slug' => 'dashboard.view',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'role_id' => $roleId,
            'email' => 'supplier-'.$roleId.'@feeder.test',
            'phone' => '0770000000',
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => 'ACTIVE',
            'phone_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function insertCompany(string $name, ?int $portalId = null, string $marketCode = 'lk'): int
    {
        return DB::table('companies')->insertGetId([
            'portal_id' => $portalId,
            'operation_market_id' => DB::table('markets')->where('code', $marketCode)->value('id'),
            'name' => $name,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignOperationMarket(int $companyId, string $marketCode): void
    {
        $marketId = DB::table('markets')->where('code', $marketCode)->value('id');

        if ($marketId === null) {
            return;
        }

        DB::table('companies')->where('id', $companyId)->update([
            'operation_market_id' => $marketId,
        ]);
    }

    private function makeProduct(?int $supplierId, ?string $deletedAt = null): int
    {
        return DB::table('products')->insertGetId([
            'supplier_id' => $supplierId,
            'name' => 'Product '.$supplierId,
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{invoice_id: int, batch_id: int}
     */
    private function insertOwnedLine(
        User $supplier,
        ?int $productId,
        string $batchNumber,
        string $marketCode,
        string $batchDate,
        string $linePayable,
        string $paymentStatus,
        string $payableTotal,
        ?string $lineDate = null,
        array $overrides = [],
    ): array {
        $invoice = $this->insertInvoice(
            (int) $supplier->company_id,
            $marketCode,
            $batchNumber,
            $batchDate,
            $paymentStatus,
            $payableTotal,
            $overrides,
        );
        $this->insertLine($invoice, (int) $supplier->id, $productId, $linePayable, $lineDate ?? $batchDate);

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{invoice_id: int, batch_id: int}
     */
    private function insertInvoice(
        int $companyId,
        string $marketCode,
        string $batchNumber,
        string $batchDate,
        string $paymentStatus,
        string $payableTotal,
        array $overrides = [],
    ): array {
        $currency = $marketCode === 'MY' ? 'MYR' : 'LKR';
        $batchId = DB::table('supplier_settlement_batches')->insertGetId([
            'uuid' => $overrides['batch_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
            'batch_number' => $batchNumber,
            'market_code_snapshot' => $marketCode,
            'settlement_date' => $batchDate,
            'status' => 'LOCKED',
            'voided_at' => $overrides['batch_voided_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoiceId = DB::table('supplier_settlement_invoices')->insertGetId([
            'batch_id' => $batchId,
            'supplier_company_id' => $companyId,
            'currency_code_snapshot' => $currency,
            'payable_total' => $payableTotal,
            'payment_status' => $paymentStatus,
            'instruction_status' => $overrides['instruction_status'] ?? 'ACTIVE',
            'voided_at' => $overrides['invoice_voided_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['invoice_id' => $invoiceId, 'batch_id' => $batchId];
    }

    /**
     * @param  array{invoice_id: int, batch_id: int}  $invoice
     */
    private function insertLine(array $invoice, int $supplierUserId, ?int $productId, string $linePayable, string $lineDate): void
    {
        $currency = DB::table('supplier_settlement_invoices')
            ->where('id', $invoice['invoice_id'])
            ->value('currency_code_snapshot');

        DB::table('supplier_settlement_invoice_lines')->insert([
            'invoice_id' => $invoice['invoice_id'],
            'settlement_date' => $lineDate,
            'supplier_user_id' => $supplierUserId,
            'product_id' => $productId,
            'line_payable' => $linePayable,
            'currency_code_snapshot' => $currency,
            'line_payment_status' => 'UNPAID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{summaries: array<string, array<string, mixed>>, rows: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    private function overviewFor(User $supplier): array
    {
        return app(SupplierFinancialOverviewReadService::class)->forOverview(
            $supplier,
            app(PortalFinancialOverviewMarkets::class)->active(),
            null,
            null,
            15,
        );
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start);
        $candidates = array_filter(
            [
                strpos($html, '<section', $start + 1),
                strpos($html, 'FinancialBatchReport', $start + 1),
            ],
            static fn (int|false $value): bool => $value !== false,
        );
        $next = $candidates === [] ? false : min($candidates);

        return $next === false ? substr($html, $start) : substr($html, $start, $next - $start);
    }
}
