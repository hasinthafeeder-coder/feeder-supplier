<?php

namespace Tests\Feature\Registration;

use App\Services\Auth\PasswordResetService;
use App\Services\Registration\RegistrationOtpService;
use App\Services\Registration\RegistrationService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class SupplierAuthenticationRegressionTest extends TestCase
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

    public function test_existing_sri_lankan_phone_login_behavior_remains_working(): void
    {
        $this->createActiveSupplierUser('0771234567');

        $response = $this->post('/login', [
            'identifier' => '0771234567',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_otp_registration_behavior_remains_working_for_sri_lanka(): void
    {
        $phone = '0781234567';
        $otpService = app(RegistrationOtpService::class);
        $result = $otpService->sendOtp($phone);

        $this->assertArrayHasKey('expires_in', $result);
        $this->assertFalse($otpService->isPhoneVerified($phone));

        $cachedOtp = Cache::get('reseller_registration_otp:'.$phone);
        $this->assertIsArray($cachedOtp);
    }

    public function test_password_reset_phone_lookup_remains_working(): void
    {
        $user = $this->createActiveSupplierUser('0761234567');

        $result = app(PasswordResetService::class)->sendOtp('0761234567');

        $this->assertArrayHasKey('expires_in', $result);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '0761234567']);
    }

    public function test_duplicate_phone_detection_remains_reliable(): void
    {
        $phone = '0751234567';

        app(RegistrationService::class)->createOrResumeRegistration(
            phone: $phone,
            password: 'Password123!',
            operationCountryUuid: $this->countryByIso('LK')->uuid,
        );

        $activeUser = User::query()->where('phone', $phone)->firstOrFail();
        $activeUser->status = UserStatus::ACTIVE->value;
        $activeUser->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(RegistrationService::class)->createOrResumeRegistration(
            phone: $phone,
            password: 'AnotherPassword123!',
            operationCountryUuid: $this->countryByIso('LK')->uuid,
        );
    }

    private function createActiveSupplierUser(string $phone): User
    {
        $portal = Portal::query()->where('code', PortalCode::SUPPLIER->value)->firstOrFail();

        $company = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'name' => 'Active Supplier',
            'phone' => $phone,
            'status' => CompanyStatus::ACTIVE->value,
            'operation_market_id' => $this->marketByCode('lk')->id,
        ]);

        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'company_id' => $company->id,
            'email' => sprintf('%s@supplier.local', $phone),
            'phone' => $phone,
            'password' => Hash::make('Password123!'),
            'user_type' => 'OWNER',
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->owner_user_id = $user->id;
        $company->save();

        return $user;
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
