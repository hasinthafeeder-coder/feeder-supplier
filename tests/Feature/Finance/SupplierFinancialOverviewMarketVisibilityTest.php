<?php

namespace Tests\Feature\Finance;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * Supplier financial overview cards follow the company operation market.
 */
class SupplierFinancialOverviewMarketVisibilityTest extends TestCase
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

    public function test_overview_cards_follow_operation_market_only(): void
    {
        $lk = Market::query()->where('code', 'lk')->firstOrFail();
        $my = Market::query()->where('code', 'my')->firstOrFail();

        $lkSupplier = $this->makeSupplierForMarket($lk);
        $mySupplier = $this->makeSupplierForMarket($my);

        $lkHtml = $this->actingAs($lkSupplier)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('id="financial-overview-market-lk"', $lkHtml);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $lkHtml);
        $this->assertStringNotContainsString('Summary cards total your line amounts', $lkHtml);
        $this->assertStringNotContainsString('There is no supplier batch or invoice page', $lkHtml);

        $myHtml = $this->actingAs($mySupplier)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('id="financial-overview-market-my"', $myHtml);
        $this->assertStringNotContainsString('id="financial-overview-market-lk"', $myHtml);
    }

    private function makeSupplierForMarket(Market $market): User
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

        $company = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'name' => 'Supplier Vis Co '.Str::lower(Str::random(4)),
            'email' => 'supplier-vis-'.Str::uuid().'@feeder.local',
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
                'slug' => 'supplier-vis-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Supplier Visibility Owner',
                'description' => 'Supplier Visibility Owner',
                'is_system' => false,
            ]
        );

        $permission = Permission::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'dashboard.view',
            ],
            [
                'uuid' => UuidService::generate(),
                'module' => 'Dashboard',
                'group' => 'Dashboard',
                'name' => 'dashboard.view',
                'description' => null,
                'sort_order' => 10,
            ]
        );

        $role->permissions()->sync([$permission->id]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company.operationMarket', 'role']);
    }
}
