<?php



namespace App\Http\Requests\Product;



use Feeder\Core\Http\Requests\Concerns\ValidatesProductDescriptions;

use Feeder\Core\Models\Market;

use Feeder\Core\Models\Product;

use Feeder\Core\Models\ProductVariant;

use Feeder\Core\Models\User;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;



class StoreProductRequest extends FormRequest

{

    use ValidatesProductDescriptions;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $originalVariants = [];



    public function authorize(): bool

    {

        return auth()->check();

    }



    protected function prepareForValidation(): void

    {

        $priceLocked = $this->boolean('price_locked');

        $variants = $this->input('variants', []);

        $this->originalVariants = is_array($variants) ? $variants : [];



        $this->prepareProductDescriptionsForValidation($this->resolvedProductMarket());



        if (is_array($variants)) {

            foreach ($variants as $index => $variant) {

                if (! is_array($variant)) {

                    continue;

                }



                $existing = ! empty($variant['id'])

                    ? ProductVariant::query()->find($variant['id'])

                    : null;



                if ($priceLocked) {

                    if ($existing) {

                        $variants[$index]['suggested_price'] = $existing->suggested_price;

                    }

                } else {

                    if ($existing) {

                        $variants[$index]['selling_price'] = $existing->selling_price;

                    } else {

                        $variants[$index]['selling_price'] = 0;

                    }



                    unset($variants[$index]['suggested_price']);

                }

            }

        }



        $this->merge([

            'system_visible' => $this->boolean('system_visible'),

            'web_visible' => $this->boolean('web_visible'),

            'price_locked' => $priceLocked,

            'variants' => is_array($variants) ? $variants : [],

        ]);

    }



    public function rules(): array

    {

        $priceLocked = $this->boolean('price_locked');



        return array_merge([

            'id' => ['prohibited'],

            'name' => ['required', 'string', 'max:255'],

            'category_id' => ['required', 'string', 'exists:product_categories,id'],

            'status' => ['nullable', 'string', Rule::in(['DRAFT', 'ACTIVE', 'INACTIVE'])],

            'save_action' => ['nullable', 'string', Rule::in(['draft', 'publish', 'deactivate', 'activate'])],

            'supplier_id' => ['prohibited'],

            'market_id' => ['prohibited'],

            'system_visible' => ['nullable', 'boolean'],

            'web_visible' => ['nullable', 'boolean'],

            'price_locked' => ['nullable', 'boolean'],

            'variants' => ['required', 'array', 'min:1'],

            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],

            'variants.*.name' => ['required', 'string', 'max:255'],

            'variants.*.barcode' => ['nullable', 'string', 'max:255', 'distinct'],

            'variants.*.cost' => ['required', 'numeric', 'min:0'],

            'variants.*.selling_price' => [

                $priceLocked ? 'required' : 'nullable',

                'numeric',

                'min:0',

            ],

            'variants.*.weight' => ['required', 'numeric', 'gt:0'],

            'variants.*.suggested_price' => [

                $priceLocked ? 'nullable' : 'prohibited',

                'numeric',

                'min:0',

            ],

            'variants.*.suggested_price_min' => [

                $priceLocked ? 'prohibited' : 'required',

                'numeric',

                'min:0',

            ],

            'variants.*.suggested_price_max' => [

                $priceLocked ? 'prohibited' : 'required',

                'numeric',

                'min:0',

            ],

            'variants.*.company_commission' => ['nullable', 'numeric', 'min:0'],

            'images' => ['nullable', 'array', 'max:4'],

            'images.*' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'guideline' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],

        ], $this->productDescriptionRules($this->resolvedProductMarket()));

    }



    public function withValidator($validator): void

    {

        $validator->after(function ($validator) {

            $this->assertSubmittedDescriptionsMatchMarket($this->resolvedProductMarket());



            $priceLocked = $this->boolean('price_locked');



            foreach ($this->originalVariants as $index => $variant) {

                if (! is_array($variant)) {

                    continue;

                }



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

                    if (! empty($variant['id'])) {

                        $existing = ProductVariant::query()->find($variant['id']);



                        if ($existing && $this->submittedPriceWasTampered(

                            $variant['suggested_price'] ?? null,

                            $existing->suggested_price

                        )) {

                            $validator->errors()->add(

                                "variants.{$index}.suggested_price",

                                'Suggested price cannot be modified when Price Lock is enabled.'

                            );

                        }

                    }

                } else {

                    $minPrice = $variant['suggested_price_min'] ?? null;

                    $maxPrice = $variant['suggested_price_max'] ?? null;



                    if (

                        $minPrice !== null

                        && $maxPrice !== null

                        && (float) $minPrice > (float) $maxPrice

                    ) {

                        $validator->errors()->add(

                            "variants.{$index}.suggested_price_max",

                            'Suggested maximum price must be greater than or equal to the minimum price.'

                        );

                    }



                    if (! empty($variant['id'])) {

                        $existing = ProductVariant::query()->find($variant['id']);



                        if ($existing && $this->submittedPriceWasTampered(

                            $variant['selling_price'] ?? null,

                            $existing->selling_price

                        )) {

                            $validator->errors()->add(

                                "variants.{$index}.selling_price",

                                'Selling price cannot be modified when Price Lock is disabled.'

                            );

                        }

                    }

                }

            }

        });

    }



    private function submittedPriceWasTampered(mixed $submitted, mixed $stored): bool

    {

        if ($submitted === null && $stored === null) {

            return false;

        }



        if ($submitted === null || $stored === null) {

            return true;

        }



        return (float) $submitted !== (float) $stored;

    }



    public function resolvedProductMarket(): ?Market

    {

        $product = $this->route('product');



        if ($product instanceof Product) {

            $product->loadMissing('market');



            return $product->market;

        }



        /** @var User|null $user */

        $user = $this->user();

        $user?->loadMissing('company.operationMarket');



        return $user?->company?->operationMarket;

    }

}

