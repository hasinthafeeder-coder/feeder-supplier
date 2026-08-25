<?php

namespace App\Http\Requests\Product;

use Feeder\Core\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $descriptions = $this->input('descriptions', []);
        $priceLocked = $this->boolean('price_locked');
        $variants = $this->input('variants', []);

        if (is_array($descriptions)) {
            foreach (['en', 'si', 'ta'] as $locale) {
                if (! array_key_exists($locale, $descriptions)) {
                    $descriptions[$locale] = null;
                }
            }
        }

        if ($priceLocked && is_array($variants)) {
            foreach ($variants as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $variants[$index]['suggested_price'] = $variant['selling_price'] ?? null;
            }
        }

        $this->merge([
            'descriptions' => is_array($descriptions) ? $descriptions : [],
            'system_visible' => $this->boolean('system_visible'),
            'web_visible' => $this->boolean('web_visible'),
            'price_locked' => $priceLocked,
            'variants' => is_array($variants) ? $variants : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'id' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'string', 'exists:product_categories,id'],
            'status' => ['nullable', 'string', Rule::in(['DRAFT', 'ACTIVE', 'INACTIVE'])],
            'save_action' => ['nullable', 'string', Rule::in(['draft', 'publish', 'deactivate', 'activate'])],
            'supplier_id' => ['prohibited'],
            'system_visible' => ['nullable', 'boolean'],
            'web_visible' => ['nullable', 'boolean'],
            'price_locked' => ['nullable', 'boolean'],
            'descriptions' => ['required', 'array'],
            'descriptions.en' => ['nullable', 'string'],
            'descriptions.si' => ['nullable', 'string'],
            'descriptions.ta' => ['nullable', 'string'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255', 'distinct'],
            'variants.*.cost' => ['required', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.weight' => ['required', 'numeric', 'gt:0'],
            'variants.*.suggested_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.company_commission' => ['nullable', 'numeric', 'min:0'],
            'images' => ['nullable', 'array', 'max:4'],
            'images.*' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'guideline' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $priceLocked = $this->boolean('price_locked');

            foreach ((array) $this->input('variants', []) as $index => $variant) {
                $barcode = trim((string) ($variant['barcode'] ?? ''));

                if ($barcode !== '') {
                    $query = ProductVariant::query()->where('barcode', $barcode);

                    if (! empty($variant['id'])) {
                        $query->whereKeyNot($variant['id']);
                    }

                    if ($query->exists()) {
                        $validator->errors()->add(
                            "variants.{$index}.barcode",
                            'The barcode has already been taken.'
                        );
                    }
                }

                if ($priceLocked) {
                    $sellingPrice = $variant['selling_price'] ?? null;
                    $suggestedPrice = $variant['suggested_price'] ?? null;

                    if (
                        $sellingPrice !== null
                        && $suggestedPrice !== null
                        && (float) $sellingPrice !== (float) $suggestedPrice
                    ) {
                        $validator->errors()->add(
                            "variants.{$index}.suggested_price",
                            'Suggested price must match selling price when Price Lock is enabled.'
                        );
                    }
                }
            }
        });
    }
}
