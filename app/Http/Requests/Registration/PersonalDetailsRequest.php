<?php

namespace App\Http\Requests\Registration;

use Feeder\Core\Http\Requests\Concerns\ResolvesCountryRegistrationRules;
use Feeder\Core\Validation\Rules\ValidCountryIdentityDocument;
use Illuminate\Foundation\Http\FormRequest;

class PersonalDetailsRequest extends FormRequest
{
    use ResolvesCountryRegistrationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $operationCountryId = (string) $this->input('operation_country_id');

        if (! filled($operationCountryId)) {
            $this->merge([
                'first_name' => trim((string) $this->input('first_name')),
                'last_name' => trim((string) $this->input('last_name')),
                'address' => trim((string) $this->input('address')),
                'profile_photo_uuid' => filled($this->input('profile_photo_uuid'))
                    ? strtoupper(trim((string) $this->input('profile_photo_uuid')))
                    : null,
            ]);

            return;
        }

        try {
            $rules = $this->resolveRulesFromOperationCountryId($operationCountryId);
            $normalizedIdentity = $rules->normalizeIdentityDocument((string) $this->input('nic'));

            $this->merge([
                'first_name' => trim((string) $this->input('first_name')),
                'last_name' => trim((string) $this->input('last_name')),
                'nic' => $normalizedIdentity,
                'address' => trim((string) $this->input('address')),
                'profile_photo_uuid' => filled($this->input('profile_photo_uuid'))
                    ? strtoupper(trim((string) $this->input('profile_photo_uuid')))
                    : null,
            ]);
        } catch (\Throwable) {
            $this->merge([
                'first_name' => trim((string) $this->input('first_name')),
                'last_name' => trim((string) $this->input('last_name')),
                'nic' => strtoupper(trim((string) $this->input('nic'))),
                'address' => trim((string) $this->input('address')),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = $this->resolveRulesFromOperationCountryId((string) $this->input('operation_country_id'));

        return [
            'user_uuid' => ['required', 'string', 'size:10'],
            'operation_country_id' => $this->operationCountryIdRules(),
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'nic' => [
                'required',
                'string',
                'max:50',
                new ValidCountryIdentityDocument($rules),
            ],
            'address' => ['required', 'string', 'max:500'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'profile_photo_uuid' => ['nullable', 'string', 'size:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
            'operation_country_id.required' => 'Operation country is required.',
            'operation_country_id.exists' => 'The selected operation country is invalid.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'nic.required' => 'Identity document number is required.',
            'address.required' => 'Residential address is required.',
            'profile_photo.image' => 'Profile photo must be an image.',
            'profile_photo.mimes' => 'Profile photo must be a JPG, PNG, or WebP image.',
            'profile_photo.max' => 'Maximum profile photo size is 5MB.',
            'profile_photo_uuid.size' => 'Invalid profile photo reference.',
        ];
    }
}
