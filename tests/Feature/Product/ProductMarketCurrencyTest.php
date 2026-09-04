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
use Feeder\Core\Services\ProductService;
use Feeder\Core\Support\CurrencyDisplay;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ProductMarketCurrencyTest extends TestCase
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

    public function test_sri_lankan_supplier_create_page_displays_lkr_market_context(): void
    {
        $this->allowPermissions(['products.create']);

        $supplier = $this->makeSupplierUser('lk');

        $this->actingAs($supplier)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Product Market')
            ->assertSee('Sri Lanka')
            ->assertSee('Currency: LKR (Rs)')
            ->assertSee('LKR')
            ->assertSee('generateUniqueBarcode', false)
            ->assertSee('Auto-generated', false)
            ->assertSee('readonly', false);
    }

    public function test_malaysian_supplier_create_page_displays_myr_market_context(): void
    {
        $this->allowPermissions(['products.create']);

        $supplier = $this->makeSupplierUser('my');

        $this->actingAs($supplier)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Product Market')
            ->assertSee('Malaysia')
            ->assertSee('Currency: MYR (RM)')
            ->assertSee('MYR');
    }

    public function test_supplier_without_operation_market_cannot_access_create_page(): void
    {
        $this->allowPermissions(['products.create']);

        $supplier = $this->makeSupplierUser(null);

        $this->actingAs($supplier)
            ->get(route('products.create'))
            ->assertForbidden();
    }

    public function test_product_list_displays_market_currency_for_lk_and_my_products(): void
    {
        $this->allowPermissions(['products.view']);

        $lkSupplier = $this->makeSupplierUser('lk');
        $mySupplier = $this->makeSupplierUser('my');
        $category = $this->makeCategory();

        $lkProduct = $this->makeProductForSupplier($lkSupplier, $category, 'lk', 1500);
        $myProduct = $this->makeProductForSupplier($mySupplier, $category, 'my', 25);

        $this->actingAs($lkSupplier)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('LKR 1,500.00')
            ->assertDontSee('MYR 25.00');

        $this->actingAs($mySupplier)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('MYR 25.00')
            ->assertDontSee('LKR 1,500.00');
    }

    public function test_product_details_displays_market_currency(): void
    {
        $this->allowPermissions(['products.view']);

        $supplier = $this->makeSupplierUser('my');
        $category = $this->makeCategory();
        $product = $this->makeProductForSupplier($supplier, $category, 'my', 25);

        $this->actingAs($supplier)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Market: Malaysia')
            ->assertSee('MYR 25.00');
    }

    public function test_legacy_product_without_market_shows_currency_unavailable_instead_of_lkr(): void
    {
        $this->allowPermissions(['products.view']);

        $supplier = $this->makeSupplierUser('lk');
        $category = $this->makeCategory();
        $product = $this->makeProductForSupplier($supplier, $category, null, 1500);

        $this->actingAs($supplier)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Market unavailable')
            ->assertSee(CurrencyDisplay::UNAVAILABLE_LABEL)
            ->assertDontSee('LKR 1,500.00');
    }

    public function test_product_service_still_assigns_market_from_supplier_operation_market(): void
    {
        $supplier = $this->makeSupplierUser('my');
        $category = $this->makeCategory();

        $product = app(ProductService::class)->createProduct([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Assigned Market Product',
            'status' => ProductStatus::DRAFT,
        ]);

        $this->assertSame($this->marketByCode('my')->id, $product->market_id);
    }

    public function test_supplier_cannot_override_market_id_on_create(): void
    {
        $supplier = $this->makeSupplierUser('lk');
        $category = $this->makeCategory();

        $product = app(ProductService::class)->createProduct([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Locked Market Product',
            'status' => ProductStatus::DRAFT,
            'market_id' => $this->marketByCode('my')->id,
        ]);

        $this->assertSame($this->marketByCode('lk')->id, $product->market_id);
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

    private function makeSupplierUser(?string $marketCode): User
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

        if ($marketCode !== null) {
            $this->configureSupplierCompany($company, $marketCode);
        }

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

    private function makeProductForSupplier(
        User $supplier,
        ProductCategory $category,
        ?string $marketCode,
        float $sellingPrice
    ): Product {
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $marketCode ? $this->marketByCode($marketCode)->id : null,
            'name' => 'Market Product '.Str::lower(Str::random(4)),
            'slug' => 'market-product-'.Str::lower(Str::random(6)),
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
            'description' => 'Market product description',
        ]);

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => 'BC-'.strtoupper(Str::random(8)),
            'cost' => $sellingPrice - 5,
            'selling_price' => $sellingPrice,
            'suggested_price' => $sellingPrice + 5,
            'weight' => 0.500,
            'company_commission' => 150.00,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['market.country', 'market.currency', 'variants']);
    }
}
