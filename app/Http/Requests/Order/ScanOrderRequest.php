<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ScanOrderRequest extends FormRequest
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
            'order_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
