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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ProductReorderLevelTest extends TestCase
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
        $this->allowPermissions(['products.create', 'products.update', 'products.view']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_product_variants_table_has_reorder_level_column_with_default_zero(): void
    {
        $this->assertTrue(Schema::hasColumn('product_variants', 'reorder_level'));

        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier);
        $variant = $product->variants->first();

        $this->assertNotNull($variant);
        $this->assertSame(0, (int) $variant->reorder_level);
    }

    public function test_supplier_can_create_product_with_reorder_level_zero(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Reorder Zero '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
                'variants' => [
                    $this->variantPayload(['reorder_level' => 0]),
                ],
            ]))
            ->assertRedirect();

        $variant = Product::query()->where('name', $productName)->first()?->variants->first();

        $this->assertNotNull($variant);
        $this->assertSame(0, (int) $variant->reorder_level);
    }

    public function test_supplier_can_create_product_with_positive_reorder_level(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Reorder Fifteen '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
                'variants' => [
                    $this->variantPayload(['reorder_level' => 15]),
                ],
            ]))
            ->assertRedirect();

        $variant = Product::query()->where('name', $productName)->first()?->variants->first();

        $this->assertNotNull($variant);
        $this->assertSame(15, (int) $variant->reorder_level);
    }

    public function test_supplier_create_defaults_reorder_level_to_zero_when_omitted(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Reorder Omitted '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
                'variants' => [
                    $this->variantPayload(),
                ],
            ]))
            ->assertRedirect();

        $variant = Product::query()->where('name', $productName)->first()?->variants->first();

        $this->assertNotNull($variant);
        $this->assertSame(0, (int) $variant->reorder_level);
    }

    public function test_supplier_can_update_variant_reorder_level(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, reorderLevel: 15);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'name' => $product->name,
                'variants' => [
                    $this->variantPayload([
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'reorder_level' => 20,
                    ]),
                ],
            ]))
            ->assertRedirect();

        $variant->refresh();

        $this->assertSame(20, (int) $variant->reorder_level);
    }

    public function test_edit_page_shows_existing_reorder_level(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, reorderLevel: 15);

        $this->actingAs($supplier)
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Reorder Level', false)
            ->assertSee('"reorder_level":15', false);

        $this->actingAs($supplier)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Reorder Level')
            ->assertSee('15');
    }

    public function test_negative_reorder_level_is_rejected(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->productPayload($category, [
                'variants' => [
                    $this->variantPayload(['reorder_level' => -1]),
                ],
            ]))
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['variants.0.reorder_level']);
    }

    public function test_decimal_reorder_level_is_rejected(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->productPayload($category, [
                'variants' => [
                    $this->variantPayload(['reorder_level' => '1.5']),
                ],
            ]))
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['variants.0.reorder_level']);
    }

    public function test_non_numeric_reorder_level_is_rejected(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->productPayload($category, [
                'variants' => [
                    $this->variantPayload(['reorder_level' => 'abc']),
                ],
            ]))
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['variants.0.reorder_level']);
    }

    public function test_supplier_cannot_update_another_suppliers_variant_reorder_level(): void
    {
        $owner = $this->makeSupplierUser();
        $intruder = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($owner, reorderLevel: 15);
        $variant = $product->variants->first();

        $this->actingAs($intruder)
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'name' => $product->name,
                'variants' => [
                    $this->variantPayload([
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'reorder_level' => 99,
                    ]),
                ],
            ]))
            ->assertForbidden();

        $variant->refresh();
        $this->assertSame(15, (int) $variant->reorder_level);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(ProductCategory $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Reorder Product '.Str::lower(Str::random(4)),
            'category_id' => $category->id,
            'descriptions' => [
                'en' => 'English description',
            ],
            'save_action' => 'draft',
            'price_locked' => '0',
            'variants' => [
                $this->variantPayload(),
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function variantPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Default',
            'cost' => 100,
            'selling_price' => 0,
            'weight' => 0.5,
            'suggested_price_min' => 200,
            'suggested_price_max' => 300,
            'company_commission' => 150,
        ], $overrides);
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
            'name' => 'Supplier Reorder Co',
            'email' => 'supplier-reorder-'.Str::lower(Str::random(6)).'@feeder.local',
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

    private function makeProductForSupplier(User $supplier, int $reorderLevel = 0): Product
    {
        $category = $this->makeCategory();

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode('lk')->id,
            'name' => 'Existing Reorder Product',
            'slug' => 'existing-reorder-'.Str::lower(Str::random(6)),
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
            'description' => 'Existing description',
        ]);

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Black / M',
            'barcode' => 'BC-'.strtoupper(Str::random(8)),
            'cost' => 1000,
            'selling_price' => 0,
            'suggested_price_min' => 1500,
            'suggested_price_max' => 2000,
            'weight' => 0.500,
            'company_commission' => 150.00,
            'reorder_level' => $reorderLevel,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['category', 'variants']);
    }
}
