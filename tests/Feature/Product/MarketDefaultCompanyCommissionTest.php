<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Feeder\Core\Authorization\Services\PermissionService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Services\MarketDefaultCompanyCommissionService;
use Feeder\Core\Services\ProductService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class MarketDefaultCompanyCommissionTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    /**
     * @var list<string>
     */
    private array $allowedPermissions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_supplier_create_page_exposes_market_default_company_commission(): void
    {
        $this->allowPermissions(['products.create']);

        $supplier = $this->makeSupplierUser('my');

        $this->actingAs($supplier)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Default company commission for new variants')
            ->assertSee('MYR 15.00');
    }

    public function test_supplier_cannot_override_product_market_on_create(): void
    {
        $supplier = $this->makeSupplierUser('lk');
        $category = $this->makeCategory();

        $product = app(ProductService::class)->createProduct([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Locked Market Product',
            'status' => ProductStatus::DRAFT,
            'market_id' => $this->marketByCode('my')->id,
        ], [], [[
            'name' => 'Default',
            'cost' => 100,
            'selling_price' => 200,
            'weight' => 0.5,
        ]]);

        $this->assertSame($this->marketByCode('lk')->id, $product->market_id);
        $this->assertSame('150.00', (string) $product->variants->first()->company_commission);
    }

    public function test_product_currency_and_commission_context_match_same_market(): void
    {
        $this->allowPermissions(['products.create']);

        $supplier = $this->makeSupplierUser('my');

        $response = $this->actingAs($supplier)->get(route('products.create'));

        $response->assertOk();
        $response->assertSee('Malaysia');
        $response->assertSee('MYR');
        $response->assertSee('MYR 15.00');
    }

    public function test_changing_market_default_applies_only_to_new_variants_on_update(): void
    {
        $service = app(MarketDefaultCompanyCommissionService::class);
        $supplier = $this->makeSupplierUser('lk');
        $category = $this->makeCategory();

        $product = app(ProductService::class)->createProduct(
            [
                'supplier_id' => $supplier->id,
                'category_id' => $category->id,
                'name' => 'Variant Growth Product',
                'status' => ProductStatus::DRAFT,
            ],
            [],
            [[
                'name' => 'Default',
                'cost' => 100,
                'selling_price' => 200,
                'weight' => 0.5,
            ]]
        );

        $service->setDefaultCompanyCommission('lk', '180.00');

        app(ProductService::class)->updateProduct(
            $product,
            ['updated_by' => $supplier->id],
            [],
            [
                [
                    'id' => $product->variants->first()->id,
                    'name' => 'Default',
                    'cost' => 100,
                    'selling_price' => 200,
                    'weight' => 0.5,
                ],
                [
                    'name' => 'Bundle',
                    'cost' => 150,
                    'selling_price' => 250,
                    'weight' => 0.7,
                ],
            ]
        );

        $product->refresh()->load('variants');

        $this->assertSame('150.00', (string) $product->variants->firstWhere('name', 'Default')->company_commission);
        $this->assertSame('180.00', (string) $product->variants->firstWhere('name', 'Bundle')->company_commission);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function allowPermissions(array $permissions): void
    {
        $this->allowedPermissions = $permissions;

        $permissionService = Mockery::mock(PermissionService::class);
        $permissionService->shouldReceive('hasPermission')
            ->andReturnUsing(function ($user, string $permission): bool {
                return in_array($permission, $this->allowedPermissions, true);
            });
        $permissionService->shouldReceive('hasAnyPermission')
            ->andReturnUsing(function ($user, array $permissions): bool {
                return collect($permissions)->intersect($this->allowedPermissions)->isNotEmpty();
            });
        $permissionService->shouldReceive('hasAllPermissions')
            ->andReturnUsing(function ($user, array $permissions): bool {
                return collect($permissions)->diff($this->allowedPermissions)->isEmpty();
            });

        $this->app->instance(PermissionService::class, $permissionService);
    }

    private function makeSupplierUser(string $marketCode): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Supplier Portal',
                'subdomain' => 'supplier-'.Str::lower(Str::random(4)),
                'description' => 'Supplier Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => 'Supplier Co '.Str::lower(Str::random(4)),
            'email' => 'supplier-'.Str::lower(Str::random(6)).'@feeder.local',
            'phone' => '077'.random_int(1000000, 9999999),
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
        $this->configureSupplierCompany($company, $marketCode);

        return $user;
    }

    private function makeCategory(): ProductCategory
    {
        return ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
