<?php

namespace Tests\Feature\Registration;

use App\Services\Registration\RegistrationService;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierCountryAwareRegistrationTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->seedSupplierPortal();
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_lk_supplier_registration_personal_details_continue_working(): void
    {
        $user = $this->createRegisteringSupplier('0712345678', 'LK');

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/personal', [
            'user_uuid' => $user->uuid,
            'operation_country_id' => $this->countryByIso('LK')->uuid,
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'nic' => '123456789V',
            'address' => 'No. 1, Main Street',
            'profile_photo_uuid' => 'PHOTO12345',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'nic' => '123456789V',
            'identity_document_type' => 'NIC',
            'identity_document_number' => '123456789V',
        ]);
    }

    public function test_my_supplier_registration_uses_malaysian_validation(): void
    {
        $user = $this->createRegisteringSupplier('0123456789', 'MY');

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/personal', [
            'user_uuid' => $user->uuid,
            'operation_country_id' => $this->countryByIso('MY')->uuid,
            'first_name' => 'Ahmad',
            'last_name' => 'Hassan',
            'nic' => '900101011234',
            'address' => 'Kuala Lumpur',
            'profile_photo_uuid' => 'PHOTO12345',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'identity_document_type' => 'MYKAD',
            'identity_document_number' => '900101011234',
        ]);
    }

    public function test_my_supplier_registration_rejects_sri_lankan_nic_format(): void
    {
        $user = $this->createRegisteringSupplier('0123456789', 'MY');

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/personal', [
            'user_uuid' => $user->uuid,
            'operation_country_id' => $this->countryByIso('MY')->uuid,
            'first_name' => 'Ahmad',
            'last_name' => 'Hassan',
            'nic' => '123456789V',
            'address' => 'Kuala Lumpur',
            'profile_photo_uuid' => 'PHOTO12345',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['nic']);
    }

    public function test_customer_care_phone_validation_follows_operation_country(): void
    {
        $user = $this->createRegisteringSupplier('0123456789', 'MY');

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/company', [
            'user_uuid' => $user->uuid,
            'name' => 'MY Supplier Co',
            'address' => 'Kuala Lumpur',
            'customer_care_phone' => '0129876543',
            'logo_uuid' => 'LOGO123456',
        ]);

        $response->assertOk();
        $response->assertJsonPath('company.customer_care_phone', '0129876543');
    }

    public function test_operation_country_persists_through_draft_resume(): void
    {
        $user = $this->createRegisteringSupplier('0129876543', 'MY');

        $this->assertDatabaseHas('users', [
            'uuid' => $user->uuid,
            'status' => UserStatus::REGISTERING->value,
        ]);

        $draftResponse = $this->withHeader('Accept', 'application/json')
            ->getJson('/auth/register/draft/'.$user->uuid);

        $draftResponse->assertOk();
        $draftResponse->assertJsonPath('draft.company.operation_country_id', $this->countryByIso('MY')->uuid);
    }

    public function test_operation_market_remains_correctly_assigned_for_supplier(): void
    {
        $user = $this->createRegisteringSupplier('0123456789', 'MY');
        $user->load('company.operationMarket');

        $this->assertSame(
            $this->marketByCode('my')->id,
            $user->company?->operation_market_id
        );
    }

    public function test_phone_registration_normalizes_malaysian_numbers(): void
    {
        $normalizedPhone = '0123456789';

        Cache::put('reseller_registration_otp:'.$normalizedPhone, [
            'hash' => Hash::make('123456'),
            'verified' => true,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ], now()->addMinutes(5));

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/user', [
            'operation_country_id' => $this->countryByIso('MY')->uuid,
            'phone' => '60123456789',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'phone' => $normalizedPhone,
            'status' => UserStatus::REGISTERING->value,
        ]);
    }

    private function createRegisteringSupplier(string $phone, string $countryIso): User
    {
        return app(RegistrationService::class)->createOrResumeRegistration(
            phone: $phone,
            password: 'Password123!',
            operationCountryUuid: $this->countryByIso($countryIso)->uuid,
        );
    }

    private function seedSupplierPortal(): void
    {
        Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Supplier Portal',
                'is_active' => true,
            ]
        );
    }
}
