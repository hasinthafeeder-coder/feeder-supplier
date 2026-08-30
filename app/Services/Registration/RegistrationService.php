<?php

namespace App\Services\Registration;

use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\SupplierOperationMarketService;
use Feeder\Core\Services\UuidService;
use Feeder\Core\Support\IdentityDocumentStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(
        private readonly SupplierOperationMarketService $operationMarketService,
    ) {}
    public function resolveStepOneStatus(string $phone): array
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('phone', $phone)
            ->first();

        if ($user === null) {
            return [
                'phone_exists' => false,
                'can_proceed' => true,
                'password_is_set' => false,
                'user_uuid' => null,
                'current_step' => 1,
                'registration_submitted' => false,
                'alert_message' => null,
            ];
        }

        $passwordIsSet = $this->hasPasswordSet($user);
        $isRegistering = $user->status === UserStatus::REGISTERING->value;
        $isPending = $user->status === UserStatus::PENDING->value;

        if ($isPending) {
            return [
                'phone_exists' => true,
                'can_proceed' => true,
                'password_is_set' => true,
                'user_uuid' => $user->uuid,
                'current_step' => null,
                'registration_submitted' => true,
                'alert_message' => 'Your registration has been submitted and is pending approval.',
            ];
        }

        if (! $isRegistering) {
            return [
                'phone_exists' => true,
                'can_proceed' => false,
                'password_is_set' => $passwordIsSet,
                'user_uuid' => $user->uuid,
                'current_step' => null,
                'registration_submitted' => false,
                'alert_message' => 'This phone number is already registered.',
            ];
        }

        return [
            'phone_exists' => true,
            'can_proceed' => true,
            'password_is_set' => $passwordIsSet,
            'user_uuid' => $user->uuid,
            'current_step' => $this->resolveCurrentStep($user),
            'registration_submitted' => false,
            'alert_message' => $passwordIsSet
                ? 'A saved registration was found. You can continue to the next step.'
                : 'A saved registration was found. Please complete your password setup to continue.',
        ];
    }

    public function createOrResumeRegistration(
        string $phone,
        string $password,
        ?string $operationCountryUuid = null,
    ): User {
        /** @var User|null $user */
        $user = User::query()
            ->where('phone', $phone)
            ->first();

        /*
    |--------------------------------------------------------------------------
    | Existing ACTIVE/PENDING user
    |--------------------------------------------------------------------------
    */

        if (
            $user !== null &&
            $user->status !== UserStatus::REGISTERING->value
        ) {

            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Resume existing registration
    |--------------------------------------------------------------------------
    */

        if ($user !== null) {

            $user->password = Hash::make($password);
            $user->phone_verified_at = now();
            $user->save();

            if ($operationCountryUuid !== null) {
                $this->ensureSupplierOperationCountry($user, $operationCountryUuid);
            }

            return $user->fresh(['company.operationMarket.country']);
        }

        /*
    |--------------------------------------------------------------------------
    | Create new registration
    |--------------------------------------------------------------------------
    */

        return DB::transaction(function () use ($phone, $password, $operationCountryUuid): User {

            /** @var User $user */
            $user = User::query()->create([

                'uuid' => UuidService::generate(),

                'phone' => $phone,

                'email' => sprintf('%s@supplier.local', $phone),

                'password' => Hash::make($password),

                'user_type' => UserType::OWNER->value,

                'status' => UserStatus::REGISTERING->value,

                'phone_verified_at' => now(),

            ]);

            if ($operationCountryUuid !== null) {
                $this->ensureSupplierOperationCountry($user, $operationCountryUuid);
            }

            return $user->fresh(['company.operationMarket.country']);
        });
    }

    public function savePassword(string $userUuid, string $password): User
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('uuid', $userUuid)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Invalid registration session.',
            ]);
        }

        if ($user->status !== UserStatus::REGISTERING->value) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Invalid registration session.',
            ]);
        }

        $user->password = Hash::make($password);
        $user->save();

        return $user;
    }

    public function getRegisteringUser(string $uuid): User
    {

        /** @var User|null $user */
        $user = User::query()
            ->where('uuid', $uuid)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Invalid registration session.',
            ]);
        }

        if ($user->status !== UserStatus::REGISTERING->value) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Invalid registration session.',
            ]);
        }

        return $user;
    }

    public function findRegistrationDraft(string $phone): ?User
    {
        return User::query()
            ->where('phone', $phone)
            ->where('status', UserStatus::REGISTERING->value)
            ->first();
    }

    private function hasPasswordSet(User $user): bool
    {
        return is_string($user->password) && trim($user->password) !== '';
    }

    public function resolveCurrentStep(User $user): int
    {
        $user->loadMissing(['profile', 'company.address', 'company.bankAccounts', 'company.operationMarket']);

        if (! $this->hasPasswordSet($user)) {
            return 1;
        }

        if (! $this->isPersonalStepComplete($user->profile)) {
            return 2;
        }

        if (! $this->isCompanyStepComplete($user->company)) {
            return 3;
        }

        return 4;
    }

    private function isPersonalStepComplete(?object $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return filled($profile->first_name)
            && filled($profile->last_name)
            && IdentityDocumentStorage::isIdentityComplete($profile)
            && filled($profile->address)
            && (
                filled($profile->profile_photo)
                || filled($profile->profile_photo_uuid)
            );
    }

    private function isCompanyStepComplete(?object $company): bool
    {
        if ($company === null) {
            return false;
        }

        return filled($company->name)
            && filled($company->customer_care_phone)
            && filled($company->address?->address)
            && filled($company->logo_uuid)
            && filled($company->operation_market_id);
    }

    private function isBankStepComplete(?object $bankAccount): bool
    {
        if ($bankAccount === null) {
            return false;
        }

        return filled($bankAccount->account_name)
            && filled($bankAccount->bank_name)
            && filled($bankAccount->branch_name)
            && filled($bankAccount->account_number);
    }

    public function getDraft(string $userUuid): array
    {
        /** @var User|null $user */
        $user = User::query()
            ->with(['profile', 'company.address', 'company.bankAccounts', 'company.operationMarket.country'])
            ->where('uuid', $userUuid)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Invalid registration session.',
            ]);
        }

        $status = $user->status instanceof UserStatus
            ? $user->status
            : UserStatus::tryFrom((string) $user->status);

        if (! in_array($status, [UserStatus::REGISTERING, UserStatus::PENDING], true)) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Invalid registration session.',
            ]);
        }

        $profile = $user->profile;
        $company = $user->company;
        $companyAddress = $company?->address;
        $bankAccount = $company?->bankAccounts?->first();
        $registrationSubmitted = $status === UserStatus::PENDING;

        return [
            'user' => [
                'uuid' => $user->uuid,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
            'current_step' => $registrationSubmitted ? null : $this->resolveCurrentStep($user),
            'registration_submitted' => $registrationSubmitted,
            'personal' => $profile ? [
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'nic' => IdentityDocumentStorage::storedDocumentNumber($profile),
                'identity_document_type' => $profile->identity_document_type,
                'identity_document_number' => $profile->identity_document_number,
                'address' => $profile->address,
                'profile_photo' => $profile->profile_photo,
                'profile_photo_uuid' => $profile->profile_photo_uuid,
                'profile_photo_uploaded' => filled($profile->profile_photo)
                    || filled($profile->profile_photo_uuid),
            ] : null,
            'company' => $company ? [
                'name' => $company->name,
                'customer_care_phone' => $company->customer_care_phone,
                'registration_number' => $company->registration_number,
                'address' => $companyAddress?->address,
                'logo_uuid' => $company->logo_uuid,
                'logo_uploaded' => ! empty($company->logo_uuid),
                'business_reg_pdf_uuid' => $company->business_reg_pdf_uuid,
                'business_reg_pdf_uploaded' => ! empty($company->business_reg_pdf_uuid),
                'operation_country_id' => $company->operationMarket?->country?->uuid,
                'operation_country_name' => $company->operationMarket?->country?->name,
            ] : null,
            'bank' => $bankAccount ? [
                'account_name' => $bankAccount->account_name,
                'bank_name' => $bankAccount->bank_name,
                'branch_name' => $bankAccount->branch_name,
                'bank_code' => $bankAccount->bank_code,
                'branch_code' => $bankAccount->branch_code,
                'account_number' => $bankAccount->account_number,
            ] : null,
        ];
    }

    public function submitApplication(string $userUuid): User
    {
        return DB::transaction(function () use ($userUuid) {
            /** @var User|null $user */
            $user = User::query()
                ->with(['profile', 'company.address', 'company.bankAccounts'])
                ->where('uuid', $userUuid)
                ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Invalid registration session.',
                ]);
            }

            if ($user->status === UserStatus::PENDING->value || $user->status === UserStatus::ACTIVE->value) {
                return $user;
            }

            if ($user->status !== UserStatus::REGISTERING) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Invalid registration session.',
                ]);
            }

            $errors = [];

            if (! $this->isPersonalStepComplete($user->profile)) {
                $errors['personal'] = 'Personal information is incomplete.';
            }

            if (! $this->isCompanyStepComplete($user->company)) {
                $errors['company'] = 'Company information is incomplete.';
            }

            if (! $this->isBankStepComplete($user->company?->bankAccounts?->first())) {
                $errors['bank'] = 'Bank information is incomplete.';
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $user->status = UserStatus::PENDING->value;
            $user->save();

            if ($user->company) {
                $user->company->status = 'PENDING';
                $user->company->save();
            }

            return $user;
        });
    }

    private function ensureSupplierOperationCountry(User $user, string $operationCountryUuid): void
    {
        $portal = Portal::query()->where('code', 'SUPPLIER')->first();
        $portalId = $portal ? $portal->id : 1;

        $company = $user->company;

        if (! $company) {
            $company = new Company();
            $company->uuid = UuidService::generate();
            $company->portal_id = $portalId;
            $company->owner_user_id = $user->id;
            $company->status = 'PENDING';
            $company->phone = $user->phone ?? '';
            $company->name = $company->name ?: 'Pending Registration';
        }

        if ($company->operation_market_id === null) {
            $this->operationMarketService->assignOnRegistration($company, $operationCountryUuid);
        }

        $company->save();

        if ($user->company_id !== $company->id) {
            $user->company_id = $company->id;
            $user->save();
        }
    }
}
