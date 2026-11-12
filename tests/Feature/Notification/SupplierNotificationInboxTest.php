<?php

namespace Tests\Feature\Notification;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\NotificationAudienceType;
use Feeder\Core\Enums\NotificationDeliveryChannel;
use Feeder\Core\Enums\NotificationDeliveryStatus;
use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\NotificationDelivery;
use Feeder\Core\Models\NotificationRecipient;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierNotificationInboxTest extends TestCase
{
    use SetsUpSupplierOrderApiData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->assertNotificationSchemaReady();
        $this->allowPermissions([]);
        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_supplier_inbox_lists_admin_announcement_and_updates_engagement(): void
    {
        $this->assertTrue(Route::has('notifications.inbox.index'));
        $this->assertTrue(Route::has('notifications.inbox.show'));
        $this->assertTrue(Route::has('notifications.live'));

        $supplier = $this->makeSupplierUser('Inbox Supplier A');
        $stranger = $this->makeSupplierUser('Inbox Supplier B');
        $this->allowPermissions(['notifications.view', 'dashboard.view']);

        $notification = $this->seedAddressedNotification($supplier, [
            'title' => 'Supplier platform notice',
            'body' => 'Please review supplier portal updates',
        ]);

        $this->actingAs($supplier)
            ->get(route('notifications.inbox.index'))
            ->assertOk()
            ->assertSee('Supplier platform notice')
            ->assertSee('Unread')
            ->assertSee('headerNotificationsDropdown', false);

        $this->actingAs($stranger)
            ->get(route('notifications.inbox.index'))
            ->assertOk()
            ->assertDontSee('Supplier platform notice');

        $this->actingAs($supplier)
            ->get(route('notifications.inbox.show', $notification))
            ->assertOk()
            ->assertSee('Please review supplier portal updates');

        $recipient = NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->where('recipient_user_id', $supplier->id)
            ->firstOrFail();

        $this->assertNotNull($recipient->fresh()->read_at);
        $this->assertNotNull($recipient->fresh()->opened_at);
        $this->assertSame(0, app(NotificationInboxService::class)->unreadCountFor($supplier));

        $this->actingAs($stranger)
            ->get(route('notifications.inbox.show', $notification))
            ->assertForbidden();
    }

    public function test_live_poll_exposes_new_inbox_items_without_mutating_read_state(): void
    {
        $supplier = $this->makeSupplierUser('Live Supplier A');
        $stranger = $this->makeSupplierUser('Live Supplier B');
        $this->allowPermissions(['notifications.view', 'dashboard.view']);

        $notification = $this->seedAddressedNotification($supplier, [
            'title' => 'Live supplier notice',
            'body' => 'Poll without refresh',
        ]);

        $this->actingAs($supplier)
            ->getJson(route('notifications.live'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('poll_interval_ms', 15000)
            ->assertJsonFragment([
                'notification_uuid' => (string) $notification->uuid,
                'title' => 'Live supplier notice',
                'is_unread' => true,
            ]);

        $recipient = NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->where('recipient_user_id', $supplier->id)
            ->firstOrFail();
        $this->assertNull($recipient->fresh()->read_at);
        $this->assertNull($recipient->fresh()->opened_at);

        $this->actingAs($stranger)
            ->getJson(route('notifications.live'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonMissing(['notification_uuid' => (string) $notification->uuid]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedAddressedNotification(User $supplier, array $overrides = []): Notification
    {
        $adminPortal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::ADMIN->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Admin Portal',
                'subdomain' => 'admin',
            ]
        );
        $adminCompany = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $adminPortal->id,
            'name' => 'Admin Co '.Str::upper(Str::random(4)),
            'email' => 'admin-co-'.Str::lower(Str::random(6)).'@feeder.local',
            'phone' => '070'.random_int(1000000, 9999999),
            'status' => CompanyStatus::ACTIVE->value,
        ]);
        $admin = User::query()->create([
            'uuid' => UuidService::generate(),
            'company_id' => $adminCompany->id,
            'email' => 'admin-seed-'.Str::lower(Str::random(6)).'@feeder.local',
            'phone' => '070'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::SUPER_ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $notification = Notification::query()->create(array_merge([
            'uuid' => UuidService::generate(),
            'type' => NotificationType::ANNOUNCEMENT->value,
            'title' => 'Seeded supplier notice',
            'body' => 'Body',
            'audience_type' => NotificationAudienceType::ALL_ELIGIBLE_SUPPLIERS->value,
            'publish_at' => now()->subMinute(),
            'expires_at' => null,
            'created_by' => $admin->id,
        ], $overrides));

        $recipient = NotificationRecipient::query()->create([
            'uuid' => UuidService::generate(),
            'notification_id' => $notification->id,
            'recipient_user_id' => $supplier->id,
            'recipient_company_id' => $supplier->company_id,
            'read_at' => null,
            'opened_at' => null,
        ]);

        NotificationDelivery::query()->create([
            'uuid' => UuidService::generate(),
            'notification_recipient_id' => $recipient->id,
            'channel' => NotificationDeliveryChannel::IN_APP->value,
            'status' => NotificationDeliveryStatus::DELIVERED->value,
            'delivered_at' => now(),
        ]);

        return $notification;
    }

    private function assertNotificationSchemaReady(): void
    {
        $this->assertTrue(
            Schema::hasTable('notifications')
            && Schema::hasTable('notification_recipients')
            && Schema::hasTable('notification_deliveries')
        );

        if (! Schema::hasColumn('notification_recipients', 'opened_at')) {
            $path = dirname(base_path())
                .DIRECTORY_SEPARATOR.'dropshipping-db'
                .DIRECTORY_SEPARATOR.'database'
                .DIRECTORY_SEPARATOR.'migrations'
                .DIRECTORY_SEPARATOR.'2026_11_10_160000_add_opened_at_to_notification_recipients_table.php';
            $migration = require $path;
            $migration->up();
        }
    }
}
