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
use Feeder\Core\Models\ProductDescription;
use Feeder\Core\Models\ProductVariant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ProductPermissionTest extends TestCase
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

    public function test_guest_cannot_access_product_routes(): void
    {
        $this->get(route('products.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_supplier_without_permission_cannot_view_products(): void
    {
        $this->allowPermissions([]);

        $this->actingAs($this->makeSupplierUser())
            ->get(route('products.index'))
            ->assertForbidden();
    }

    public function test_authenticated_supplier_with_view_permission_can_access_product_list(): void
    {
        $this->allowPermissions(['products.view']);

        $this->actingAs($this->makeSupplierUser())
            ->get(route('products.index'))
            ->assertOk();
    }

    public function test_supplier_cannot_access_create_route_without_create_permission(): void
    {
        $this->allowPermissions(['products.view']);

        $this->actingAs($this->makeSupplierUser())
            ->get(route('products.create'))
            ->assertForbidden();
    }

    public function test_supplier_can_access_create_route_with_create_permission(): void
    {
        $this->allowPermissions(['products.create']);

        $this->actingAs($this->makeSupplierUser())
            ->get(route('products.create'))
            ->assertOk();
    }

    public function test_supplier_cannot_update_product_without_update_permission(): void
    {
        $this->allowPermissions(['products.view']);

        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier);

        $this->actingAs($supplier)
            ->get(route('products.edit', $product))
            ->assertForbidden();
    }

    public function test_supplier_cannot_delete_product_without_delete_permission(): void
    {
        $this->allowPermissions(['products.view', 'products.update']);

        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier);

        $this->actingAs($supplier)
            ->delete(route('products.destroy', $product))
            ->assertForbidden();
    }

    public function test_login_route_remains_accessible_without_product_permissions(): void
    {
        $this->allowPermissions([]);

        $this->get(route('login'))
            ->assertOk();
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
            'name' => 'Supplier Permission Co',
            'email' => 'supplier-permission-'.Str::lower(Str::random(6)).'@feeder.local',
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

    private function makeProductForSupplier(User $supplier): Product
    {
        $category = ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Sample Product',
            'slug' => 'sample-product-'.Str::lower(Str::random(6)),
            'status' => ProductStatus::ACTIVE,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => false,
            'published_at' => now(),
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        ProductDescription::query()->create([
            'product_id' => $product->id,
            'language_code' => 'en',
            'description' => 'Sample description',
        ]);

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => 'BC-'.strtoupper(Str::random(8)),
            'cost' => 1000,
            'selling_price' => 1500,
            'suggested_price' => 1800,
            'weight' => 0.500,
            'company_commission' => 150.00,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product;
    }
}
