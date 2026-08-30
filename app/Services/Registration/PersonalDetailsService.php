<?php

namespace App\Services\Registration;

use Feeder\Core\Enums\ApplicationType;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Services\FileService;
use Feeder\Core\Services\SupplierOperationMarketService;
use Feeder\Core\Services\UuidService;
use Feeder\Core\Support\IdentityDocumentStorage;
use Feeder\Core\Support\UserProfileSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Log;
use Throwable;

class PersonalDetailsService
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly CountryRegistrationRuleService $countryRegistrationRuleService,
        private readonly SupplierOperationMarketService $operationMarketService,
    ) {}

    public function save(array $data): UserProfile
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()
                ->with(['profile', 'company.operationMarket.country'])
                ->where('uuid', $data['user_uuid'])
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Invalid Registration Session.',
                ]);
            }

            // Log::info('Registration status check', [
            //     'user_uuid' => $user->uuid,
            //     'status' => $user->status,
            //     'status_value' => $user->status instanceof \BackedEnum ? $user->status->value : $user->status,
            //     'expected' => UserStatus::REGISTERING->value,
            //     'comparison' => $user->status !== UserStatus::REGISTERING->value,
            // ]);

            if ($user->status !== UserStatus::REGISTERING) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Registration cannot be updated.',
                ]);
            }

            $profile = $user->profile;

            if (! $profile) {
                $profile = new UserProfile();
                $profile->uuid = UuidService::generate();
                $profile->user_id = $user->id;
            }

            $countryRules = $this->countryRegistrationRuleService->assertSupplierOperationCountryMatches(
                $user,
                (string) $data['operation_country_id'],
            );

            $this->ensureSupplierCompanyWithOperationCountry($user, (string) $data['operation_country_id']);

            $normalizedIdentityNumber = $countryRules->normalizeIdentityDocument($data['nic']);
            $this->assertIdentityDocumentIsUnique($normalizedIdentityNumber, $profile);

            $profilePhotoUuid = $this->resolveProfilePhotoUuid(
                $user,
                $profile,
                $data['profile_photo'] ?? null,
                $data['profile_photo_uuid'] ?? null,
            );

            $profile->first_name = $data['first_name'];
            $profile->last_name = $data['last_name'];
            IdentityDocumentStorage::applyToProfile($profile, $countryRules, $normalizedIdentityNumber);
            $profile->address = $data['address'];
            $profile->profile_photo = $profilePhotoUuid;
            $profile->profile_photo_uuid = $profilePhotoUuid;
            $profile->save();

            return $profile->fresh();
        });
    }

    private function assertIdentityDocumentIsUnique(string $identityDocumentNumber, UserProfile $profile): void
    {
        $identityQuery = UserProfile::query();

        if (UserProfileSchema::hasIdentityDocumentColumns()) {
            $identityQuery->where(function ($query) use ($identityDocumentNumber): void {
                $query->where('identity_document_number', $identityDocumentNumber)
                    ->orWhere('nic', $identityDocumentNumber);
            });
        } else {
            $identityQuery->where('nic', $identityDocumentNumber);
        }

        if ($profile->exists) {
            $identityQuery->where('id', '!=', $profile->id);
        }

        if ($identityQuery->exists()) {
            throw ValidationException::withMessages([
                'nic' => 'This identity document number is already registered.',
            ]);
        }
    }

    private function ensureSupplierCompanyWithOperationCountry(User $user, string $operationCountryUuid): void
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

    private function resolveProfilePhotoUuid(
        User $user,
        UserProfile $profile,
        mixed $uploadedFile,
        ?string $existingUuid,
    ): string {
        if ($uploadedFile instanceof UploadedFile) {
            return $this->uploadProfilePhoto($uploadedFile, $user);
        }

        if (! empty($existingUuid)) {
            return $existingUuid;
        }

        if (! empty($profile->profile_photo_uuid)) {
            return $profile->profile_photo_uuid;
        }

        if (! empty($profile->profile_photo)) {
            return $profile->profile_photo;
        }

        throw ValidationException::withMessages([
            'profile_photo' => 'Profile photo is required.',
        ]);
    }

    private function uploadProfilePhoto(UploadedFile $file, User $user): string
    {
        try {
            $response = $this->fileService->upload(
                $file,
                ApplicationType::SUPPLIER->value,
                'USER',
                $user->uuid,
                'PROFILE_PHOTO',
                $user->uuid,
            );
        } catch (RequestException | ConnectionException) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Unable to upload profile photo. Please try again.',
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Unable to upload profile photo. Please try again.',
            ]);
        }

        $uuid = data_get($response, 'file.uuid');

        if (! is_string($uuid) || $uuid === '') {
            throw ValidationException::withMessages([
                'profile_photo' => 'Unable to upload profile photo. Please try again.',
            ]);
        }

        return $uuid;
    }
}
