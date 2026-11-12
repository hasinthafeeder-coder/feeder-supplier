<?php

namespace Tests\Feature\Notification;

use Feeder\Core\Authorization\Menu\Menu;
use Feeder\Core\Authorization\Menu\SupplierMenu;
use Feeder\Core\Authorization\Services\MenuService;
use Feeder\Core\Enums\FileCategory;
use Feeder\Core\Enums\NotificationAudienceType;
use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Enums\ProductApprovalStatus;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Models\File;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\NotificationRecipient;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\User;
use Feeder\Core\Services\FileService;
use Feeder\Core\Services\Notification\NotificationPublicationService;
use Feeder\Core\Services\Notification\PublishNotificationData;
use Feeder\Core\Services\UuidService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierNotificationManagementTest extends TestCase
{
    use SetsUpSupplierOrderApiData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->allowPermissions([]);
        Carbon::setTestNow('2026-11-10 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_routes_and_menu_expose_supplier_notification_management(): void
    {
        $this->assertTrue(Route::has('notifications.index'));
        $this->assertTrue(Route::has('notifications.create'));
        $this->assertTrue(Route::has('notifications.store'));
        $this->assertTrue(Route::has('notifications.show'));
        $this->assertTrue(Route::has('notifications.edit'));
        $this->assertTrue(Route::has('notifications.update'));
        $this->assertTrue(Route::has('notifications.inbox.index'));
        $this->assertTrue(Route::has('notifications.inbox.show'));
        $this->assertTrue(Route::has('notifications.inbox.read'));

        $productItem = null;
        $inboxItem = null;
        foreach (SupplierMenu::build()->getSections() as $section) {
            foreach ($section->getItems() as $candidate) {
                if ($candidate->getTitle() === 'Product Notifications') {
                    $productItem = $candidate;
                }
                if ($candidate->getTitle() === 'Notification Inbox') {
                    $inboxItem = $candidate;
                }
            }
        }

        $this->assertNotNull($productItem);
        $this->assertSame('notifications.index', $productItem->getRoute());
        $this->assertSame('notifications.view', $productItem->getPermission());
        $this->assertNotNull($inboxItem);
        $this->assertSame('notifications.inbox.index', $inboxItem->getRoute());
        $this->assertSame('notifications.view', $inboxItem->getPermission());

        $supplier = $this->makeSupplierUser();
        $this->allowPermissions(['dashboard.view']);
        $titles = $this->menuTitles(app(MenuService::class)->getForUser($supplier));
        $this->assertNotContains('Product Notifications', $titles);
        $this->assertNotContains('Notification Inbox', $titles);

        $this->allowPermissions(['notifications.view']);
        $titles = $this->menuTitles(app(MenuService::class)->getForUser($supplier));
        $this->assertContains('Product Notifications', $titles);
        $this->assertContains('Notification Inbox', $titles);
    }

    public function test_authorization_blocks_guests_and_missing_permissions(): void
    {
        $supplier = $this->makeSupplierUser();
        $reseller = $this->makeResellerUser();
        $this->assignSupplierToReseller($reseller, $supplier);
        $product = $this->makeSellableProduct($supplier);
        $notification = $this->publishProductOffer($supplier, $product, [$reseller->id]);

        $this->get(route('notifications.index'))->assertRedirect(route('login', absolute: false));
        $this->post(route('notifications.store'), $this->payload($product))
            ->assertRedirect(route('login', absolute: false));

        $this->allowPermissions([]);
        $this->actingAs($supplier)->get(route('notifications.index'))->assertForbidden();
        $this->actingAs($supplier)->get(route('notifications.create'))->assertForbidden();
        $this->actingAs($supplier)->get(route('notifications.show', $notification))->assertForbidden();
        $this->actingAs($supplier)->get(route('notifications.edit', $notification))->assertForbidden();
        $this->actingAs($supplier)
            ->post(route('notifications.store'), $this->payload($product, ['title' => 'Denied']))
            ->assertForbidden();

        $this->allowPermissions(['notifications.view']);
        $this->actingAs($supplier)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertDontSee('Create notification');
        $this->actingAs($supplier)
            ->post(route('notifications.store'), $this->payload($product, [$reseller->id], ['title' => 'Should not save']))
            ->assertForbidden();
        $this->actingAs($supplier)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertDontSee(route('notifications.edit', $notification), false);
    }

    public function test_supplier_cannot_create_admin_announcement(): void
    {
        $supplier = $this->makeSupplierUser();
        $assigned = $this->makeResellerUser();
        $this->assignSupplierToReseller($assigned, $supplier);
        $product = $this->makeSellableProduct($supplier);

        $this->allowPermissions(['notifications.view', 'notifications.create']);

        $this->actingAs($supplier)
            ->from(route('notifications.create'))
            ->post(route('notifications.store'), $this->payload($product, [
                'type' => NotificationType::ANNOUNCEMENT->value,
            ]))
            ->assertRedirect(route('notifications.create'))
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Notification::query()->where('created_by', $supplier->id)->count());
    }

    public function test_product_ownership_and_assigned_reseller_recipient_resolution(): void
    {
        $supplier = $this->makeSupplierUser();
        $otherSupplier = $this->makeSupplierUser('Other Supplier Co');
        $assigned = $this->makeResellerUser();
        $unassigned = $this->makeResellerUser();
        $this->assignSupplierToReseller($assigned, $supplier);
        $ownProduct = $this->makeSellableProduct($supplier);
        $foreignProduct = $this->makeSellableProduct($otherSupplier);

        $this->allowPermissions(['notifications.view', 'notifications.create']);

        $this->actingAs($supplier)
            ->from(route('notifications.create'))
            ->post(route('notifications.store'), $this->payload($foreignProduct))
            ->assertRedirect(route('notifications.create'))
            ->assertSessionHasErrors('product_id');

        $this->actingAs($supplier)
            ->post(route('notifications.store'), $this->payload($ownProduct, [
                'title' => 'Offer for assigned reseller',
            ]))
            ->assertRedirect();

        $notification = Notification::query()->where('title', 'Offer for assigned reseller')->firstOrFail();
        $recipientIds = NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->pluck('recipient_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame((int) $ownProduct->id, (int) $notification->product_id);
        $this->assertSame((int) $supplier->company_id, (int) $notification->publisher_company_id);
        $this->assertSame(
            NotificationAudienceType::ALL_ASSIGNED_RESELLERS_FOR_SUPPLIER,
            $notification->audience_type
        );
        $this->assertContains((int) $assigned->id, $recipientIds);
        $this->assertNotContains((int) $unassigned->id, $recipientIds);

        // Colombo wall-clock publish time must be stored as UTC so the inbox is visible now.
        $this->actingAs($supplier)
            ->post(route('notifications.store'), $this->payload($ownProduct, [
                'title' => 'Immediate assigned notice',
                'publish_at' => '2026-11-10T15:30',
            ]))
            ->assertRedirect();

        $immediate = Notification::query()->where('title', 'Immediate assigned notice')->firstOrFail();
        $this->assertSame('2026-11-10 10:00:00', $immediate->publish_at?->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertTrue($immediate->isPublishedAt(Carbon::parse('2026-11-10 10:00:00', 'UTC')));
        $this->assertTrue(
            app(\Feeder\Core\Services\Notification\NotificationInboxService::class)
                ->visibleRecipientsFor($assigned)
                ->contains(fn ($row) => (int) $row->notification_id === (int) $immediate->id)
        );
        $this->assertFalse(
            app(\Feeder\Core\Services\Notification\NotificationInboxService::class)
                ->visibleRecipientsFor($unassigned)
                ->contains(fn ($row) => (int) $row->notification_id === (int) $immediate->id)
        );
    }

    public function test_cross_company_isolation_and_edit_preserves_audience(): void
    {
        $supplierA = $this->makeSupplierUser('Supplier A Co');
        $supplierB = $this->makeSupplierUser('Supplier B Co');
        $reseller = $this->makeResellerUser();
        $this->assignSupplierToReseller($reseller, $supplierA);
        $product = $this->makeSellableProduct($supplierA);
        $notification = $this->publishProductOffer($supplierA, $product, [$reseller->id]);

        $this->allowPermissions(['notifications.view', 'notifications.create']);

        $this->actingAs($supplierB)
            ->get(route('notifications.show', $notification))
            ->assertForbidden();
        $this->actingAs($supplierB)
            ->get(route('notifications.edit', $notification))
            ->assertForbidden();
        $this->actingAs($supplierB)
            ->put(route('notifications.update', $notification), [
                'title' => 'Hijacked',
                'body' => 'Nope',
                'publish_at' => '2026-11-10T11:00',
            ])
            ->assertForbidden();

        $this->actingAs($supplierA)
            ->put(route('notifications.update', $notification), [
                'title' => 'Updated offer',
                'body' => 'Still the same audience',
                'publish_at' => '2026-11-10T12:00',
                'expires_at' => '2026-11-10T18:00',
            ])
            ->assertRedirect(route('notifications.show', $notification));

        $notification->refresh();
        $this->assertSame('Updated offer', $notification->title);
        $this->assertSame(NotificationType::PRODUCT_OFFER, $notification->type);
        $this->assertTrue($notification->audience_type->isSupplierAudience());
        $this->assertSame((int) $product->id, (int) $notification->product_id);
        $this->assertSame(
            1,
            NotificationRecipient::query()->where('notification_id', $notification->id)->count()
        );
    }

    public function test_invalid_product_and_schedule_validation(): void
    {
        $supplier = $this->makeSupplierUser();
        $reseller = $this->makeResellerUser();
        $this->assignSupplierToReseller($reseller, $supplier);
        $product = $this->makeSellableProduct($supplier);
        $unsellable = $this->makeSellableProduct($supplier);
        $unsellable->forceFill([
            'status' => ProductStatus::DRAFT->value,
            'approval_status' => ProductApprovalStatus::PENDING_REVIEW->value,
            'system_visible' => false,
        ])->save();

        $this->allowPermissions(['notifications.view', 'notifications.create']);

        $this->actingAs($supplier)
            ->from(route('notifications.create'))
            ->post(route('notifications.store'), $this->payload($product, [
                'publish_at' => '2026-11-10T12:00',
                'expires_at' => '2026-11-10T11:00',
            ]))
            ->assertRedirect(route('notifications.create'))
            ->assertSessionHasErrors('expires_at');

        $this->actingAs($supplier)
            ->from(route('notifications.create'))
            ->post(route('notifications.store'), $this->payload($unsellable))
            ->assertRedirect(route('notifications.create'))
            ->assertSessionHasErrors('product_id');
    }

    public function test_optional_banner_upload_uses_notification_banner_category(): void
    {
        $supplier = $this->makeSupplierUser();
        $reseller = $this->makeResellerUser();
        $this->assignSupplierToReseller($reseller, $supplier);
        $product = $this->makeSellableProduct($supplier);
        $this->allowPermissions(['notifications.view', 'notifications.create']);

        $files = Mockery::mock(FileService::class);
        $files->shouldReceive('upload')->once()->andReturnUsing(function () {
            File::query()->create([
                'uuid' => 'SUPBANNER1',
                'application' => 'SUPPLIER',
                'entity_type' => 'NOTIFICATION',
                'entity_uuid' => UuidService::generate(),
                'category' => FileCategory::NOTIFICATION_BANNER->value,
                'disk' => 'feeder',
                'path' => 'notification-banners/banner.jpg',
                'original_name' => 'banner.jpg',
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'size' => 20,
                'visibility' => 'PRIVATE',
                'status' => 'ACTIVE',
            ]);

            return [
                'file' => [
                    'uuid' => 'SUPBANNER1',
                    'original_name' => 'banner.jpg',
                ],
            ];
        });
        $this->app->instance(FileService::class, $files);

        $this->actingAs($supplier)
            ->post(route('notifications.store'), $this->payload($product, [
                'title' => 'Bannered restock',
                'type' => NotificationType::RESTOCK->value,
                'banner' => UploadedFile::fake()->image('banner.jpg', 120, 40),
            ]))
            ->assertRedirect();

        $notification = Notification::query()->where('title', 'Bannered restock')->firstOrFail();
        $this->assertSame('SUPBANNER1', $notification->banner_file_uuid);
        $this->assertSame(FileCategory::NOTIFICATION_BANNER->value, File::query()->where('uuid', 'SUPBANNER1')->value('category'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'type' => NotificationType::PRODUCT_OFFER->value,
            'product_id' => $product->id,
            'title' => 'Product offer',
            'body' => 'Limited time offer',
            'publish_at' => null,
            'expires_at' => null,
        ], $overrides);
    }

    private function makeSellableProduct(User $supplier): Product
    {
        return $this->makeSupplierProductVariant($supplier)['product'];
    }

    /**
     * @param  list<int>  $recipientIds
     */
    private function publishProductOffer(User $supplier, Product $product, array $recipientIds): Notification
    {
        return app(NotificationPublicationService::class)->publishForSupplierProduct(
            $supplier,
            $product,
            new PublishNotificationData(
                type: NotificationType::PRODUCT_OFFER,
                title: 'Seeded offer',
                body: 'Body',
                audienceType: NotificationAudienceType::SELECTED_ELIGIBLE_RESELLERS_FOR_PRODUCT,
                recipientUserIds: $recipientIds,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function menuTitles(Menu $menu): array
    {
        $titles = [];

        foreach ($menu->getSections() as $section) {
            foreach ($section->getItems() as $item) {
                $titles[] = $item->getTitle();
                foreach ($item->getChildren() as $child) {
                    $titles[] = $child->getTitle();
                }
            }
        }

        return $titles;
    }
}
