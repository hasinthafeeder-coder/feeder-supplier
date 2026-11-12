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

class ProductEditSellingPriceRegressionTest extends TestCase
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

    public function test_editing_unrelated_fields_without_selling_price_preserves_existing_price(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier);
        $variant = $product->variants->first();
        $originalSelling = (float) $variant->selling_price;

        $this->actingAs($supplier)
            ->from(route('products.edit', $product))
            ->put(route('products.update', $product), [
                'name' => 'Edited Name Only',
                'category_id' => $product->category_id,
                'descriptions' => ['en' => 'Updated description'],
                'save_action' => 'draft',
                'system_visible' => 1,
                'web_visible' => 1,
                'price_locked' => 0,
                'variants' => [[
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'barcode' => $variant->barcode,
                    'cost' => 1000,
                    // Omit selling_price like a disabled browser input when Price Lock is off.
                    'weight' => 0.5,
                    'suggested_price_min' => 1400,
                    'suggested_price_max' => 1600,
                ]],
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasNoErrors();

        $variant->refresh();
        $product->refresh();

        $this->assertSame($originalSelling, (float) $variant->selling_price);
        $this->assertSame('Edited Name Only', $product->name);
    }

    public function test_tampered_selling_price_is_still_rejected_when_price_lock_disabled(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->from(route('products.edit', $product))
            ->put(route('products.update', $product), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'descriptions' => ['en' => 'Existing description'],
                'save_action' => 'draft',
                'system_visible' => 1,
                'web_visible' => 1,
                'price_locked' => 0,
                'variants' => [[
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'barcode' => $variant->barcode,
                    'cost' => 1000,
                    'selling_price' => 9999,
                    'weight' => 0.5,
                    'suggested_price_min' => 1400,
                    'suggested_price_max' => 1600,
                ]],
            ])
            ->assertRedirect(route('products.edit', $product))
            ->assertSessionHasErrors(['variants.0.selling_price']);

        $variant->refresh();
        $this->assertSame(1500.0, (float) $variant->selling_price);
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
            'name' => 'Supplier Price Regression Co',
            'email' => 'supplier-price-'.Str::lower(Str::random(6)).'@feeder.local',
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
            'market_id' => $this->marketByCode('lk')->id,
            'name' => 'Existing Product',
            'slug' => 'existing-product-'.Str::lower(Str::random(6)),
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
            'name' => 'Default',
            'barcode' => '',
            'cost' => 1000,
            'selling_price' => 1500,
            'weight' => 0.500,
            'company_commission' => 150.00,
            'suggested_price_min' => 1400,
            'suggested_price_max' => 1600,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['category', 'variants']);
    }
}
