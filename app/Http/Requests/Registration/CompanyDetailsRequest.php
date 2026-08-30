<?php

namespace App\Http\Requests\Registration;

use Feeder\Core\Http\Requests\Concerns\ResolvesCountryRegistrationRules;
use Feeder\Core\Models\User;
use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Validation\Rules\ValidCountryCustomerCarePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyDetailsRequest extends FormRequest
{
    use ResolvesCountryRegistrationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $operationCountryId = $this->resolvedOperationCountryId();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'address' => trim((string) $this->input('address')),
            'registration_number' => filled($this->input('registration_number'))
                ? trim((string) $this->input('registration_number'))
                : null,
            'logo_uuid' => filled($this->input('logo_uuid'))
                ? strtoupper(trim((string) $this->input('logo_uuid')))
                : null,
            'business_reg_pdf_uuid' => filled($this->input('business_reg_pdf_uuid'))
                ? strtoupper(trim((string) $this->input('business_reg_pdf_uuid')))
                : null,
        ]);

        if (! filled($operationCountryId)) {
            return;
        }

        try {
            $rules = $this->resolveRulesFromOperationCountryId($operationCountryId);
            $normalizedPhone = $rules->normalizeCustomerCarePhone((string) $this->input('customer_care_phone'));

            if ($normalizedPhone !== null) {
                $this->merge(['customer_care_phone' => $normalizedPhone]);
            }
        } catch (\Throwable) {
            $this->merge([
                'customer_care_phone' => preg_replace('/\D+/', '', (string) $this->input('customer_care_phone')) ?: null,
            ]);
        }
    }

    public function rules(): array
    {
        $operationCountryId = $this->resolvedOperationCountryId();
        $rules = $this->resolveRulesFromOperationCountryId($operationCountryId);
        $operationCountryRequired = ! $this->hasPersistedOperationCountry();

        return [
            'user_uuid' => ['required', 'string', 'size:10'],
            'name' => ['required', 'string', 'max:200'],
            'address' => ['required', 'string', 'max:500'],
            'customer_care_phone' => [
                'required',
                'string',
                new ValidCountryCustomerCarePhone($rules),
            ],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'operation_country_id' => [
                $operationCountryRequired ? 'required' : 'nullable',
                'string',
                Rule::exists('countries', 'uuid')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'logo_uuid' => ['nullable', 'string', 'size:10'],
            'business_reg_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'business_reg_pdf_uuid' => ['nullable', 'string', 'size:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
            'name.required' => 'Company name is required.',
            'address.required' => 'Company address is required.',
            'customer_care_phone.required' => 'Customer care number is required.',
            'operation_country_id.required' => 'Operation country is required.',
            'operation_country_id.exists' => 'The selected operation country is invalid.',
            'logo.image' => 'Company logo must be an image.',
            'logo.mimes' => 'Company logo must be a JPG, PNG, or WebP image.',
            'logo.max' => 'Maximum company logo size is 5MB.',
            'logo_uuid.size' => 'Invalid company logo reference.',
            'business_reg_pdf.mimes' => 'Business registration document must be a PDF.',
            'business_reg_pdf.max' => 'Maximum business registration document size is 10MB.',
            'business_reg_pdf_uuid.size' => 'Invalid business registration document reference.',
        ];
    }

    private function resolvedOperationCountryId(): string
    {
        if (filled($this->input('operation_country_id'))) {
            return (string) $this->input('operation_country_id');
        }

        $user = User::query()
            ->with('company.operationMarket.country')
            ->where('uuid', $this->input('user_uuid'))
            ->first();

        return (string) ($user?->company?->operationMarket?->country?->uuid ?? '');
    }

    private function hasPersistedOperationCountry(): bool
    {
        $user = User::query()
            ->with('company.operationMarket')
            ->where('uuid', $this->input('user_uuid'))
            ->first();

        return $user?->company?->operation_market_id !== null;
    }
}
