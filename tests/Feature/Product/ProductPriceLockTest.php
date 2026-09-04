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

class ProductPriceLockTest extends TestCase
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

    public function test_supplier_can_create_product_with_price_lock_enabled_and_editable_selling_price(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Price Lock Enabled '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
                'price_locked' => '1',
                'variants' => [
                    [
                        'name' => 'Default',
                        'cost' => 100,
                        'selling_price' => 250,
                        'weight' => 0.5,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect();

        $product = Product::query()->where('name', $productName)->first();
        $variant = $product?->variants->first();

        $this->assertNotNull($variant);
        $this->assertSame('250.00', $variant->selling_price);
        $this->assertNull($variant->suggested_price);
        $this->assertNull($variant->suggested_price_min);
        $this->assertNull($variant->suggested_price_max);
        $this->assertTrue($product->price_locked);
    }

    public function test_supplier_can_create_product_with_price_lock_disabled_and_suggested_price_range(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Price Lock Disabled '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
                'variants' => [
                    [
                        'name' => 'Default',
                        'cost' => 100,
                        'selling_price' => 999,
                        'weight' => 0.5,
                        'suggested_price_min' => 200,
                        'suggested_price_max' => 300,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect();

        $product = Product::query()->where('name', $productName)->first();
        $variant = $product?->variants->first();

        $this->assertNotNull($variant);
        $this->assertSame('0.00', $variant->selling_price);
        $this->assertNull($variant->suggested_price);
        $this->assertSame('200.00', $variant->suggested_price_min);
        $this->assertSame('300.00', $variant->suggested_price_max);
        $this->assertFalse($product->price_locked);
    }

    public function test_supplier_can_update_selling_price_when_price_lock_is_enabled(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, priceLocked: true, suggestedPrice: 500);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'price_locked' => '1',
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'cost' => 100,
                        'selling_price' => 275,
                        'weight' => 0.5,
                        'suggested_price' => 500,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect();

        $variant->refresh();

        $this->assertSame('275.00', $variant->selling_price);
        $this->assertSame('500.00', $variant->suggested_price);
    }

    public function test_supplier_can_update_suggested_price_range_when_price_lock_is_disabled(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, priceLocked: false, sellingPrice: 1500);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'cost' => 100,
                        'selling_price' => 1500,
                        'weight' => 0.5,
                        'suggested_price_min' => 210,
                        'suggested_price_max' => 260,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect();

        $variant->refresh();

        $this->assertSame('1500.00', $variant->selling_price);
        $this->assertNull($variant->suggested_price);
        $this->assertSame('210.00', $variant->suggested_price_min);
        $this->assertSame('260.00', $variant->suggested_price_max);
    }

    public function test_suggested_price_range_validation_requires_max_greater_than_or_equal_to_min(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->productPayload($category, [
                'variants' => [
                    [
                        'name' => 'Default',
                        'cost' => 100,
                        'selling_price' => 0,
                        'weight' => 0.5,
                        'suggested_price_min' => 300,
                        'suggested_price_max' => 200,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['variants.0.suggested_price_max']);
    }

    public function test_supplier_cannot_modify_selling_price_when_price_lock_is_disabled(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, priceLocked: false, sellingPrice: 1500);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->from(route('products.edit', $product))
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'cost' => 100,
                        'selling_price' => 9999,
                        'weight' => 0.5,
                        'suggested_price_min' => 210,
                        'suggested_price_max' => 260,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect(route('products.edit', $product))
            ->assertSessionHasErrors(['variants.0.selling_price']);

        $variant->refresh();

        $this->assertSame('1500.00', $variant->selling_price);
    }

    public function test_supplier_cannot_modify_suggested_price_when_price_lock_is_enabled(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, priceLocked: true, suggestedPrice: 500, sellingPrice: 250);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->from(route('products.edit', $product))
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'price_locked' => '1',
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'cost' => 100,
                        'selling_price' => 275,
                        'weight' => 0.5,
                        'suggested_price' => 999,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect(route('products.edit', $product))
            ->assertSessionHasErrors(['variants.0.suggested_price']);

        $variant->refresh();

        $this->assertSame('500.00', $variant->suggested_price);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(ProductCategory $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Price Lock Product '.Str::lower(Str::random(4)),
            'category_id' => $category->id,
            'descriptions' => [
                'en' => 'English description',
            ],
            'save_action' => 'draft',
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
            'name' => 'Supplier Price Lock Co',
            'email' => 'supplier-price-lock-'.Str::lower(Str::random(6)).'@feeder.local',
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

    private function makeProductForSupplier(
        User $supplier,
        bool $priceLocked = false,
        float $sellingPrice = 1500,
        ?float $suggestedPrice = null
    ): Product {
        $category = $this->makeCategory();

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Existing Product',
            'slug' => 'existing-product-'.Str::lower(Str::random(6)),
            'status' => ProductStatus::ACTIVE,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => $priceLocked,
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
            'name' => 'Default',
            'barcode' => 'BC-'.strtoupper(Str::random(8)),
            'cost' => 1000,
            'selling_price' => $sellingPrice,
            'suggested_price' => $suggestedPrice,
            'weight' => 0.500,
            'company_commission' => 150.00,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['category', 'variants']);
    }
}
