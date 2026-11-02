<?php

namespace Tests\Feature\Stock;

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
use Feeder\Core\Services\GoodsReceivedNoteService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierStockViewTest extends TestCase
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

    public function test_guest_cannot_access_stock_page(): void
    {
        $this->get(route('stock.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_supplier_without_permission_cannot_view_stock(): void
    {
        $this->allowPermissions([]);

        $this->actingAs($this->makeSupplierUser())
            ->get(route('stock.index'))
            ->assertForbidden();
    }

    public function test_supplier_with_permission_can_view_stock_page(): void
    {
        $this->allowPermissions(['stock.view']);

        $this->actingAs($this->makeSupplierUser())
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Stock')
            ->assertSee('Remaining Stock');
    }

    public function test_supplier_sees_only_own_variants_with_grn_based_stock(): void
    {
        $this->allowPermissions(['stock.view']);

        $supplier = $this->makeSupplierUser();
        $otherSupplier = $this->makeSupplierUser('Other Supplier Co');

        $variant = $this->makeVariantForSupplier($supplier, 'Own Product', 'Standard');
        $this->makeVariantForSupplier($otherSupplier, 'Other Product', 'Standard');

        app(GoodsReceivedNoteService::class)->createGrn(
            $supplier->id,
            [
                'received_date' => now()->toDateString(),
                'created_by' => $supplier->id,
                'updated_by' => $supplier->id,
            ],
            [[
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'received_quantity' => 125,
                'damaged_quantity' => 25,
                'unit_cost' => '100.00',
            ]]
        );

        $response = $this->actingAs($supplier)
            ->get(route('stock.index'));

        $response->assertOk()
            ->assertSee('Own Product')
            ->assertSee('Standard')
            ->assertSee('100 in stock')
            ->assertDontSee('Other Product');
    }

    public function test_zero_stock_variants_remain_visible(): void
    {
        $this->allowPermissions(['stock.view']);

        $supplier = $this->makeSupplierUser();
        $this->makeVariantForSupplier($supplier, 'Empty Product', 'Default');

        $this->actingAs($supplier)
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Empty Product')
            ->assertSee('Out of Stock');
    }

    public function test_supplier_stock_summary_counts_match_owned_variants(): void
    {
        $this->allowPermissions(['stock.view']);

        $supplier = $this->makeSupplierUser();
        $otherSupplier = $this->makeSupplierUser('Other Count Supplier Co');

        $inVariant = $this->makeVariantForSupplier($supplier, 'Counted In Stock', 'Default');
        $this->makeVariantForSupplier($supplier, 'Counted Out Of Stock', 'Default');
        $this->makeVariantForSupplier($otherSupplier, 'Foreign Variant', 'Default');

        app(GoodsReceivedNoteService::class)->createGrn(
            $supplier->id,
            [
                'received_date' => now()->toDateString(),
                'created_by' => $supplier->id,
                'updated_by' => $supplier->id,
            ],
            [[
                'product_id' => $inVariant->product_id,
                'product_variant_id' => $inVariant->id,
                'received_quantity' => 15,
                'damaged_quantity' => 0,
                'unit_cost' => '100.00',
            ]]
        );

        $this->actingAs($supplier)
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('All Variants')
            ->assertSee('(2)', false)
            ->assertSee('In Stock')
            ->assertSee('(1)', false)
            ->assertSee('Out of Stock')
            ->assertDontSee('Foreign Variant');
    }

    public function test_supplier_can_filter_stock_list_by_stock_status(): void
    {
        $this->allowPermissions(['stock.view']);

        $supplier = $this->makeSupplierUser();
        $inVariant = $this->makeVariantForSupplier($supplier, 'Filter In Stock Item', 'Default');
        $this->makeVariantForSupplier($supplier, 'Filter Out Of Stock Item', 'Default');

        app(GoodsReceivedNoteService::class)->createGrn(
            $supplier->id,
            [
                'received_date' => now()->toDateString(),
                'created_by' => $supplier->id,
                'updated_by' => $supplier->id,
            ],
            [[
                'product_id' => $inVariant->product_id,
                'product_variant_id' => $inVariant->id,
                'received_quantity' => 20,
                'damaged_quantity' => 0,
                'unit_cost' => '100.00',
            ]]
        );

        $this->actingAs($supplier)
            ->get(route('stock.index', ['stock_status' => 'in_stock']))
            ->assertOk()
            ->assertSee('Filter In Stock Item')
            ->assertDontSee('Filter Out Of Stock Item')
            ->assertSee('stock_status=in_stock', false);

        $this->actingAs($supplier)
            ->get(route('stock.index', ['stock_status' => 'out_of_stock']))
            ->assertOk()
            ->assertSee('Filter Out Of Stock Item')
            ->assertDontSee('Filter In Stock Item');

        $this->actingAs($supplier)
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Filter In Stock Item')
            ->assertSee('Filter Out Of Stock Item');
    }

    public function test_supplier_stock_status_filter_works_with_search(): void
    {
        $this->allowPermissions(['stock.view']);

        $supplier = $this->makeSupplierUser();
        $matchingInStock = $this->makeVariantForSupplier($supplier, 'Alpha In Stock', 'Default');
        $this->makeVariantForSupplier($supplier, 'Alpha Out Of Stock', 'Default');
        $this->makeVariantForSupplier($supplier, 'Beta In Stock', 'Default');

        app(GoodsReceivedNoteService::class)->createGrn(
            $supplier->id,
            [
                'received_date' => now()->toDateString(),
                'created_by' => $supplier->id,
                'updated_by' => $supplier->id,
            ],
            [[
                'product_id' => $matchingInStock->product_id,
                'product_variant_id' => $matchingInStock->id,
                'received_quantity' => 8,
                'damaged_quantity' => 0,
                'unit_cost' => '100.00',
            ]]
        );

        $this->actingAs($supplier)
            ->get(route('stock.index', [
                'search' => 'Alpha',
                'stock_status' => 'in_stock',
            ]))
            ->assertOk()
            ->assertSee('Alpha In Stock')
            ->assertDontSee('Alpha Out Of Stock')
            ->assertDontSee('Beta In Stock');
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

    private function makeSupplierUser(string $companyName = 'Supplier Stock Co'): User
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
            'name' => $companyName,
            'email' => Str::lower(Str::slug($companyName)).'-'.Str::lower(Str::random(4)).'@feeder.local',
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

    private function makeVariantForSupplier(User $supplier, string $productName, string $variantName): ProductVariant
    {
        $category = ProductCategory::query()->firstOrCreate(
            ['slug' => 'general-stock-test'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'General',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => $productName,
            'slug' => Str::slug($productName).'-'.Str::lower(Str::random(4)),
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

        return ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => $variantName,
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
    }
}
