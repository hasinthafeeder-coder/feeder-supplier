<?php

namespace App\Http\Requests\Grn;

use Feeder\Core\Services\GoodsReceivedNoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StoreGoodsReceivedNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'received_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.received_quantity' => ['required', 'integer', 'min:1'],
            'items.*.damaged_quantity' => ['required', 'integer', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = (array) $this->input('items', []);

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $received = (int) ($item['received_quantity'] ?? 0);
                $damaged = (int) ($item['damaged_quantity'] ?? 0);

                if ($damaged > $received) {
                    $validator->errors()->add(
                        "items.{$index}.damaged_quantity",
                        'Damaged quantity cannot exceed received quantity.'
                    );
                }
            }

            $variantIds = collect($items)
                ->pluck('product_variant_id')
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($variantIds->count() !== $variantIds->unique()->count()) {
                $validator->errors()->add('items', 'Each product variant can only appear once in a GRN.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(GoodsReceivedNoteService::class)->validateSupplierProductOwnership(
                    (int) Auth::id(),
                    $this->normalizedItems()
                );
            } catch (\Illuminate\Validation\ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    /**
     * @return list<array{
     *     product_id: int,
     *     product_variant_id: int,
     *     received_quantity: int,
     *     damaged_quantity: int,
     *     unit_cost: float|string,
     *     notes?: string|null
     * }>
     */
    public function normalizedItems(): array
    {
        $items = [];

        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $item['product_id'],
                'product_variant_id' => (int) $item['product_variant_id'],
                'received_quantity' => (int) $item['received_quantity'],
                'damaged_quantity' => (int) ($item['damaged_quantity'] ?? 0),
                'unit_cost' => $item['unit_cost'],
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * @return array{invoice_number: ?string, received_date: string, notes: ?string, created_by?: int, updated_by?: int}
     */
    public function headerData(): array
    {
        $supplierId = (int) Auth::id();

        return [
            'invoice_number' => $this->input('invoice_number'),
            'received_date' => (string) $this->input('received_date'),
            'notes' => $this->input('notes'),
            'created_by' => $supplierId,
            'updated_by' => $supplierId,
        ];
    }
}
