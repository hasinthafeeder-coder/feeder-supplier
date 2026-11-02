<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Feeder\Core\Authorization\Services\PermissionService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductApprovalStatus;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductApprovalLog;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Services\ProductEligibilityService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ProductApprovalSubmitTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_draft_creation_does_not_create_approval_request(): void
    {
        $this->allowPermissions(['products.view', 'products.create']);

        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'save_action' => 'draft',
                'name' => 'Draft Only '.Str::random(4),
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('supplier_id', $supplier->id)->latest('id')->first();
        $this->assertNotNull($product);
        $this->assertSame(ProductStatus::DRAFT, $product->status);
        $this->assertNull($product->approval_status);
        $this->assertFalse(app(ProductEligibilityService::class)->isProductSellable($product));
    }

    public function test_publish_submits_for_review_without_reseller_availability(): void
    {
        $this->allowPermissions(['products.view', 'products.create']);

        $supplier = $this->makeSupplierUser();
        $category = $this->makeCategory();

        $this->actingAs($supplier)
            ->post(route('products.store'), $this->productPayload($category, [
                'save_action' => 'publish',
                'name' => 'Review Me '.Str::random(4),
            ]))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Product submitted for admin review.');

        $product = Product::query()->where('supplier_id', $supplier->id)->latest('id')->first();
        $this->assertNotNull($product);
        $this->assertSame(ProductStatus::DRAFT, $product->status);
        $this->assertSame(ProductApprovalStatus::PENDING_REVIEW, $product->approval_status);
        $this->assertNotNull($product->submitted_for_review_at);
        $this->assertFalse(app(ProductEligibilityService::class)->isProductSellable($product));
        $this->assertDatabaseHas('product_approval_logs', [
            'product_id' => $product->id,
            'action' => ProductApprovalLog::ACTION_SUBMITTED,
            'acted_by' => $supplier->id,
        ]);
    }

    public function test_rejected_product_can_be_resubmitted_and_shows_reason(): void
    {
        $this->allowPermissions(['products.view', 'products.update']);

        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, [
            'name' => 'Rejected Item',
            'status' => ProductStatus::DRAFT,
            'approval_status' => ProductApprovalStatus::REJECTED,
            'rejection_reason' => 'Incomplete description.',
            'rejected_at' => now(),
        ]);

        $this->actingAs($supplier)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Incomplete description.');

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->productPayload(
                ProductCategory::query()->findOrFail($product->category_id),
                [
                    'save_action' => 'publish',
                    'name' => 'Rejected Item Fixed',
                    'variants' => [[
                        'id' => $product->variants->first()->id,
                        'name' => 'Default',
                        'cost' => 10,
                        'selling_price' => 0,
                        'weight' => 0.5,
                        'suggested_price_min' => 15,
                        'suggested_price_max' => 25,
                        'reorder_level' => 0,
                    ]],
                ]
            ))
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame(ProductApprovalStatus::PENDING_REVIEW, $product->approval_status);
        $this->assertNull($product->rejection_reason);
        $this->assertSame('Rejected Item Fixed', $product->name);
    }

    public function test_supplier_cannot_directly_activate_unapproved_product(): void
    {
        $this->allowPermissions(['products.view', 'products.update']);

        $supplier = $this->makeSupplierUser();
        $product = $this->makeProductForSupplier($supplier, [
            'status' => ProductStatus::DRAFT,
            'approval_status' => null,
        ]);

        $this->actingAs($supplier)
            ->post(route('products.activate', $product))
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('success', 'Product submitted for admin review.');

        $product->refresh();
        $this->assertSame(ProductApprovalStatus::PENDING_REVIEW, $product->approval_status);
        $this->assertSame(ProductStatus::DRAFT, $product->status);
        $this->assertFalse(app(ProductEligibilityService::class)->isProductSellable($product));
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
            'name' => 'Submit Co '.Str::random(4),
            'email' => 'submit-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(1000000, 9999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => 'supplier-submit-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(1000000, 9999999),
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
            'name' => 'Submit Cat',
            'slug' => 'submit-cat-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProductForSupplier(User $supplier, array $overrides = []): Product
    {
        $category = $this->makeCategory();

        $product = Product::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode('lk')->id,
            'name' => 'Supplier Product',
            'slug' => 'supplier-product-'.Str::lower(Str::random(6)),
            'status' => ProductStatus::DRAFT,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => false,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ], $overrides));

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => '',
            'cost' => 10,
            'selling_price' => 0,
            'weight' => 0.5,
            'suggested_price_min' => 15,
            'suggested_price_max' => 25,
            'company_commission' => 1,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['variants']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(ProductCategory $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Product '.Str::random(4),
            'category_id' => $category->id,
            'save_action' => 'draft',
            'system_visible' => 1,
            'web_visible' => 1,
            'price_locked' => 0,
            'descriptions' => [
                'en' => 'English description',
                'si' => 'Sinhala description',
            ],
            'variants' => [[
                'name' => 'Default',
                'cost' => 10,
                'selling_price' => 0,
                'weight' => 0.5,
                'suggested_price_min' => 15,
                'suggested_price_max' => 25,
                'reorder_level' => 0,
            ]],
        ], $overrides);
    }
}
