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

class ProductYoutubeAndWarrantyTest extends TestCase
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

    public function test_supplier_can_create_product_with_youtube_and_warranty(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Warranty Video '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
                'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'warranty_available' => '1',
                'warranty_period' => '6 Months',
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('name', $productName)->first();

        $this->assertNotNull($product);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $product->youtube_url);
        $this->assertTrue($product->warranty_available);
        $this->assertSame('6 Months', $product->warranty_period);
        $this->assertTrue($product->hasWarranty());
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            $product->youtubeEmbedUrl()
        );
    }

    public function test_supplier_can_create_product_without_youtube_or_warranty(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();
        $productName = 'Plain Product '.Str::lower(Str::random(4));

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'name' => $productName,
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('name', $productName)->first();

        $this->assertNotNull($product);
        $this->assertNull($product->youtube_url);
        $this->assertFalse($product->warranty_available);
        $this->assertNull($product->warranty_period);
        $this->assertFalse($product->hasWarranty());
        $this->assertNull($product->youtubeEmbedUrl());
    }

    public function test_invalid_youtube_url_is_rejected(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->productPayload($category, [
                'youtube_url' => 'https://example.com/watch?v=dQw4w9WgXcQ',
            ]))
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['youtube_url']);
    }

    public function test_warranty_period_required_when_warranty_available(): void
    {
        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->productPayload($category, [
                'warranty_available' => '1',
                'warranty_period' => '',
            ]))
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['warranty_period']);
    }

    public function test_supplier_can_update_existing_product_warranty_and_video(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier);
        $variant = $product->variants->first();

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'warranty_available' => '1',
                'warranty_period' => '12 Months',
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'cost' => 1000,
                        'selling_price' => 1500,
                        'weight' => 0.5,
                        'suggested_price_min' => 1400,
                        'suggested_price_max' => 1600,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect(route('products.index'));

        $product->refresh();

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $product->youtube_url);
        $this->assertTrue($product->warranty_available);
        $this->assertSame('12 Months', $product->warranty_period);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload($product->category, [
                'youtube_url' => '',
                'warranty_available' => '0',
                'warranty_period' => '12 Months',
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'barcode' => $variant->barcode,
                        'cost' => 1000,
                        'selling_price' => 1500,
                        'weight' => 0.5,
                        'suggested_price_min' => 1400,
                        'suggested_price_max' => 1600,
                        'company_commission' => 150,
                    ],
                ],
            ]))
            ->assertRedirect(route('products.index'));

        $product->refresh();

        $this->assertNull($product->youtube_url);
        $this->assertFalse($product->warranty_available);
        $this->assertNull($product->warranty_period);
    }

    public function test_create_form_includes_youtube_and_warranty_fields(): void
    {
        $supplier = $this->makeSupplierUser();

        $this->actingAs($supplier)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('name="youtube_url"', false)
            ->assertSee('name="warranty_available"', false)
            ->assertSee('name="warranty_period"', false)
            ->assertSee('YouTube Video URL (optional)', false)
            ->assertSee('Warranty Available', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(ProductCategory $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'YouTube Warranty Product '.Str::lower(Str::random(4)),
            'category_id' => $category->id,
            'descriptions' => [
                'en' => 'English description',
            ],
            'save_action' => 'draft',
            'variants' => [
                [
                    'name' => 'Default',
                    'cost' => 100,
                    'selling_price' => 0,
                    'weight' => 0.5,
                    'suggested_price_min' => 200,
                    'suggested_price_max' => 300,
                    'company_commission' => 150,
                ],
            ],
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
            'name' => 'Supplier Warranty Co',
            'email' => 'supplier-warranty-'.Str::lower(Str::random(6)).'@feeder.local',
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

    private function makeProductForSupplier(User $supplier): Product
    {
        $category = $this->makeCategory();

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
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['category', 'variants']);
    }
}
