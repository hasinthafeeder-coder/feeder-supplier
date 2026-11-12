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
use Feeder\Core\Models\ProductDescription;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Services\ProductApprovalService;
use Feeder\Core\Services\ProductEligibilityService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ProductEditApprovalWorkflowTest extends TestCase
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
        $this->allowPermissions(['products.view', 'products.create', 'products.update']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_approved_product_edit_is_queued_without_changing_live_data(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeApprovedProduct($supplier, [
            'name' => 'Live Approved Name',
        ]);
        $variant = $product->variants->first();
        $originalSelling = (float) $variant->selling_price;

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'Pending New Name',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $product->refresh()->load('variants');

        $this->assertSame('Live Approved Name', $product->name);
        $this->assertNull($product->youtube_url);
        $this->assertSame(ProductApprovalStatus::PENDING_REVIEW, $product->approval_status);
        $this->assertSame(ProductStatus::ACTIVE, $product->status);
        $this->assertTrue($product->hasPendingEditChanges());
        $this->assertSame('Pending New Name', $product->pending_changes['attributes']['name'] ?? null);
        $this->assertSame($originalSelling, (float) $product->variants->first()->selling_price);
        $this->assertTrue(app(ProductEligibilityService::class)->isProductSellable($product));
        $this->assertDatabaseHas('product_approval_logs', [
            'product_id' => $product->id,
            'action' => ProductApprovalLog::ACTION_SUBMITTED,
            'submission_type' => ProductApprovalLog::SUBMISSION_TYPE_EDIT,
            'acted_by' => $supplier->id,
        ]);
    }

    public function test_duplicate_edit_submission_while_pending_is_blocked(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeApprovedProduct($supplier);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'First Pending Edit',
            ]))
            ->assertRedirect(route('products.index'));

        $this->actingAs($supplier)
            ->from(route('products.edit', $product))
            ->put(route('products.update', $product->fresh()), $this->editPayload($product, [
                'name' => 'Second Conflicting Edit',
            ]))
            ->assertRedirect(route('products.edit', $product))
            ->assertSessionHasErrors(['product']);

        $product->refresh();
        $this->assertSame('First Pending Edit', $product->pending_changes['attributes']['name'] ?? null);
    }

    public function test_admin_approval_applies_pending_edit_changes(): void
    {
        $supplier = $this->makeSupplierUser();
        $admin = $this->makeAdminUser(['products.approve', 'products.reject', 'products.review']);
        $product = $this->makeApprovedProduct($supplier, ['name' => 'Before Edit']);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'After Edit',
                'warranty_available' => '1',
                'warranty_period' => '12 Months',
            ]))
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('Before Edit', $product->name);
        $this->assertTrue(app(ProductEligibilityService::class)->isProductSellable($product));

        app(ProductApprovalService::class)->approve($product, $admin);

        $product->refresh();
        $this->assertSame('After Edit', $product->name);
        $this->assertTrue($product->warranty_available);
        $this->assertSame('12 Months', $product->warranty_period);
        $this->assertSame(ProductApprovalStatus::APPROVED, $product->approval_status);
        $this->assertFalse($product->hasPendingEditChanges());
        $this->assertTrue(app(ProductEligibilityService::class)->isProductSellable($product));
    }

    public function test_admin_edit_rejection_requires_remark_and_preserves_live_product(): void
    {
        $supplier = $this->makeSupplierUser();
        $admin = $this->makeAdminUser(['products.approve', 'products.reject', 'products.review']);
        $product = $this->makeApprovedProduct($supplier, ['name' => 'Keep Live Name']);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'Should Not Apply',
            ]))
            ->assertRedirect(route('products.index'));

        try {
            app(ProductApprovalService::class)->reject($product->fresh(), $admin, 'no');
            $this->fail('Expected ValidationException for short rejection remark.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rejection_reason', $e->errors());
        }

        $product->refresh();
        $this->assertSame('Keep Live Name', $product->name);
        $this->assertTrue($product->hasPendingEditChanges());
        $this->assertSame(ProductApprovalStatus::PENDING_REVIEW, $product->approval_status);
    }

    public function test_rejected_edit_preserves_availability_and_allows_resubmission(): void
    {
        $supplier = $this->makeSupplierUser();
        $admin = $this->makeAdminUser(['products.approve', 'products.reject', 'products.review']);
        $product = $this->makeApprovedProduct($supplier, ['name' => 'Live Catalog Name']);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'Rejected Edit Name',
            ]))
            ->assertRedirect(route('products.index'));

        app(ProductApprovalService::class)->reject(
            $product->fresh(),
            $admin,
            'Please improve the product title and images.'
        );

        $product->refresh();
        $this->assertSame('Live Catalog Name', $product->name);
        $this->assertSame(ProductApprovalStatus::APPROVED, $product->approval_status);
        $this->assertSame(ProductStatus::ACTIVE, $product->status);
        $this->assertSame('Please improve the product title and images.', $product->rejection_reason);
        $this->assertFalse($product->hasPendingEditChanges());
        $this->assertTrue(app(ProductEligibilityService::class)->isProductSellable($product));

        $this->actingAs($supplier)
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Please improve the product title and images.', false);

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'Corrected Edit Name',
            ]))
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('Live Catalog Name', $product->name);
        $this->assertSame(ProductApprovalStatus::PENDING_REVIEW, $product->approval_status);
        $this->assertSame('Corrected Edit Name', $product->pending_changes['attributes']['name'] ?? null);
        $this->assertNull($product->rejection_reason);
        $this->assertDatabaseHas('product_approval_logs', [
            'product_id' => $product->id,
            'action' => ProductApprovalLog::ACTION_REJECTED,
            'submission_type' => ProductApprovalLog::SUBMISSION_TYPE_EDIT,
            'remarks' => 'Please improve the product title and images.',
        ]);
    }

    public function test_unauthorized_supplier_cannot_edit_another_suppliers_product(): void
    {
        $owner = $this->makeSupplierUser();
        $intruder = $this->makeSupplierUser();
        $product = $this->makeApprovedProduct($owner);

        $this->actingAs($intruder)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'Hijacked',
            ]))
            ->assertForbidden();
    }

    public function test_legacy_active_null_approval_edit_applies_immediately_without_review(): void
    {
        $supplier = $this->makeSupplierUser();
        $product = $this->makeApprovedProduct($supplier, [
            'name' => 'Legacy Live',
            'approval_status' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->assertNull($product->approval_status);
        $this->assertTrue(app(ProductEligibilityService::class)->isProductSellable($product));

        $this->actingAs($supplier)
            ->put(route('products.update', $product), $this->editPayload($product, [
                'name' => 'Legacy Updated Immediately',
            ]))
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('Legacy Updated Immediately', $product->name);
        $this->assertNull($product->approval_status);
        $this->assertFalse($product->hasPendingEditChanges());
        $this->assertTrue(app(ProductEligibilityService::class)->isProductSellable($product));
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

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdminUser(array $permissions): User
    {
        $previous = $this->allowedPermissions;
        $this->allowPermissions(array_values(array_unique([...$previous, ...$permissions])));

        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'admin-edit-'.Str::uuid().'@feeder.local',
            'phone' => '070'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::SUPER_ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);
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
            'name' => 'Edit Approval Co '.Str::random(4),
            'email' => 'edit-approval-'.Str::uuid().'@feeder.local',
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
        $this->allowPermissions(['products.view', 'products.create', 'products.update']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeApprovedProduct(User $supplier, array $overrides = []): Product
    {
        $category = ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Edit Cat',
            'slug' => 'edit-cat-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode('lk')->id,
            'name' => 'Approved Product',
            'slug' => 'approved-product-'.Str::lower(Str::random(6)),
            'status' => ProductStatus::ACTIVE,
            'approval_status' => ProductApprovalStatus::APPROVED,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => false,
            'published_at' => now(),
            'approved_at' => now(),
            'approved_by' => $supplier->id,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ], $overrides));

        ProductDescription::query()->create([
            'product_id' => $product->id,
            'language_code' => 'en',
            'description' => 'Approved description',
        ]);

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => '',
            'cost' => 1000,
            'selling_price' => 1500,
            'weight' => 0.5,
            'suggested_price_min' => 1400,
            'suggested_price_max' => 1600,
            'company_commission' => 150,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return $product->fresh(['variants', 'category']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayload(Product $product, array $overrides = []): array
    {
        $variant = $product->variants->first();

        return array_merge([
            'name' => $product->name,
            'category_id' => $product->category_id,
            'descriptions' => [
                'en' => 'Approved description',
                'si' => 'Sinhala description',
            ],
            'save_action' => 'publish',
            'system_visible' => 1,
            'web_visible' => 1,
            'price_locked' => 0,
            'variants' => [[
                'id' => $variant->id,
                'name' => $variant->name,
                'barcode' => $variant->barcode,
                'cost' => $variant->cost,
                'weight' => $variant->weight,
                'suggested_price_min' => $variant->suggested_price_min,
                'suggested_price_max' => $variant->suggested_price_max,
            ]],
        ], $overrides);
    }
}
