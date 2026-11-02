<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ScanReturnedOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Matches shipments.tracking_number column width (string → VARCHAR(255)).
            'tracking_number' => ['required', 'string', 'max:255'],
            // Optional; omitted → false (normal restock). Accepts true/false/0/1/"0"/"1".
            'is_damaged' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('is_damaged')) {
            $this->merge(['is_damaged' => false]);
        }
    }
}
