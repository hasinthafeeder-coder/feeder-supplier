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
use Feeder\Core\Models\ProductVariant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ProductCostAndCommissionProtectionTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    /** @var list<string> */
    private array $allowedPermissions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->allowPermissions(['products.create', 'products.update', 'products.view']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_supplier_cannot_change_existing_variant_cost_via_crafted_update(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $product = $this->createProductViaHttp($supplier, $category, 'Cost Lock '.Str::lower(Str::random(4)), 125.00);

        $variant = $product->variants->first();
        $this->assertSame('125.00', (string) $variant->cost);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($category, [
                'name' => $product->name,
                'save_action' => 'draft',
                'variants' => [[
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'cost' => 999.00,
                    'selling_price' => 250,
                    'weight' => 0.5,
                    'company_commission' => 150,
                ]],
            ]))
            ->assertRedirect();

        $this->assertSame('125.00', (string) $variant->fresh()->cost);
    }

    public function test_supplier_cannot_change_company_commission_via_crafted_update(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $product = $this->createProductViaHttp($supplier, $category, 'Commission Lock '.Str::lower(Str::random(4)), 100.00);

        $variant = $product->variants->first();
        $originalCommission = (string) $variant->company_commission;
        $this->assertSame('150.00', $originalCommission);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($category, [
                'name' => $product->name,
                'save_action' => 'draft',
                'variants' => [[
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'cost' => 100,
                    'selling_price' => 250,
                    'weight' => 0.5,
                    'company_commission' => 999.00,
                ]],
            ]))
            ->assertRedirect();

        $this->assertSame('150.00', (string) $variant->fresh()->company_commission);
    }

    public function test_supplier_create_applies_market_default_commission_ignoring_crafted_value(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $name = 'Commission Create '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $name,
                'variants' => [[
                    'name' => 'Default',
                    'cost' => 80,
                    'selling_price' => 200,
                    'weight' => 0.5,
                    'company_commission' => 777.00,
                ]],
            ]))
            ->assertRedirect();

        $product = Product::query()->where('name', $name)->first();
        $this->assertNotNull($product);
        $this->assertSame('150.00', (string) $product->variants->first()->company_commission);
        $this->assertSame('80.00', (string) $product->variants->first()->cost);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(ProductCategory $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => 'Product '.Str::lower(Str::random(4)),
            'save_action' => 'draft',
            'system_visible' => '1',
            'web_visible' => '1',
            'price_locked' => '1',
            'descriptions' => [
                'en' => 'English description',
            ],
            'variants' => [[
                'name' => 'Default',
                'cost' => 100,
                'selling_price' => 250,
                'weight' => 0.5,
                'company_commission' => 150,
            ]],
        ], $overrides);
    }

    private function createProductViaHttp(
        User $supplier,
        ProductCategory $category,
        string $name,
        float $cost,
    ): Product {
        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $name,
                'variants' => [[
                    'name' => 'Default',
                    'cost' => $cost,
                    'selling_price' => 250,
                    'weight' => 0.5,
                ]],
            ]))
            ->assertRedirect();

        return Product::query()->where('name', $name)->with('variants')->firstOrFail();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function allowPermissions(array $permissions): void
    {
        $this->allowedPermissions = $permissions;

        $permissionService = Mockery::mock(PermissionService::class);
        $permissionService->shouldReceive('hasPermission')
            ->andReturnUsing(fn ($user, string $permission): bool => in_array($permission, $this->allowedPermissions, true));
        $permissionService->shouldReceive('hasAnyPermission')
            ->andReturnUsing(fn ($user, array $permissions): bool => collect($permissions)->intersect($this->allowedPermissions)->isNotEmpty());
        $permissionService->shouldReceive('hasAllPermissions')
            ->andReturnUsing(fn ($user, array $permissions): bool => collect($permissions)->diff($this->allowedPermissions)->isEmpty());

        $this->app->instance(PermissionService::class, $permissionService);
    }

    private function makeSupplierUser(): User
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
        $this->configureSupplierCompany($company, 'lk');

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
