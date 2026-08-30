<?php

namespace App\Http\Requests\Registration;

use Feeder\Core\Http\Requests\Concerns\ResolvesCountryRegistrationRules;
use Feeder\Core\Validation\Rules\ValidCountryPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    use ResolvesCountryRegistrationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'otp' => preg_replace('/\D+/', '', (string) $this->input('otp')),
        ]);

        if (! filled($this->input('operation_country_id'))) {
            return;
        }

        try {
            $rules = $this->resolveRulesFromOperationCountryId((string) $this->input('operation_country_id'));
            $normalizedPhone = $this->normalizePhoneForRules($rules, $this->input('phone'));

            if ($normalizedPhone !== null) {
                $this->merge(['phone' => $normalizedPhone]);
            }
        } catch (\Throwable) {
            // Validation will report invalid operation country separately.
        }
    }

    public function rules(): array
    {
        $rules = $this->resolveRulesFromOperationCountryId((string) $this->input('operation_country_id'));

        return [
            'operation_country_id' => $this->operationCountryIdRules(),
            'phone' => [
                'required',
                'string',
                new ValidCountryPhone($rules),
            ],
            'otp' => ['required', 'digits_between:4,6'],
        ];
    }

    public function messages(): array
    {
        return [
            'operation_country_id.required' => 'Operation country is required.',
            'operation_country_id.exists' => 'The selected operation country is invalid.',
            'phone.required' => 'Contact number is required.',
            'otp.required' => 'OTP is required.',
        ];
    }
}
