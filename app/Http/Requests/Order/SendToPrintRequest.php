<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendToPrintRequest extends FormRequest
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
            'mode' => ['required', 'string', Rule::in(['manual', 'filter', 'count'])],
            'order_ids' => ['required_if:mode,manual', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'distinct'],
            'count' => ['required_if:mode,count', 'integer', 'min:1'],
            'filters' => ['required_if:mode,filter', 'array'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.product_id' => ['nullable', 'integer'],
            'filters.courier_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array{
     *     mode: 'manual'|'filter'|'count',
     *     order_ids?: list<int>,
     *     filters?: array{search?: string, product_id?: int|null, courier_id?: int|null},
     *     count?: int
     * }
     */
    public function selectionPayload(): array
    {
        $mode = $this->string('mode')->toString();

        return match ($mode) {
            'manual' => [
                'mode' => 'manual',
                'order_ids' => array_map('intval', $this->input('order_ids', [])),
            ],
            'filter' => [
                'mode' => 'filter',
                'filters' => [
                    'search' => trim((string) data_get($this->input('filters'), 'search', '')),
                    'product_id' => data_get($this->input('filters'), 'product_id'),
                    'courier_id' => data_get($this->input('filters'), 'courier_id'),
                ],
            ],
            default => [
                'mode' => 'count',
                'count' => (int) $this->input('count'),
            ],
        };
    }
}
