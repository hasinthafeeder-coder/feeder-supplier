<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Services\FileServerService;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const MAX_PRODUCT_IMAGES = 4;

    public function __construct(
        private readonly ProductService $productService,
        private readonly FileServerService $fileServerService,
    ) {}

    public function index(): View
    {
        $supplierId = (int) Auth::id();

        $products = Product::query()
            ->forSupplier($supplierId)
            ->with(['category', 'variants', 'images.file'])
            ->latest()
            ->get();

        $counts = [
            'all' => $products->count(),
            'active' => $products->where('status', ProductStatus::ACTIVE)->count(),
            'draft' => $products->where('status', ProductStatus::DRAFT)->count(),
            'inactive' => $products->where('status', ProductStatus::INACTIVE)->count(),
        ];

        return view('pages.products.list', [
            'products' => $products,
            'counts' => $counts,
        ]);
    }

    public function create(): View
    {
        return view('pages.products.index', $this->formViewData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->validateImageUploadLimit($request->file('images', []), 0);

        $supplierId = (int) Auth::id();
        $action = (string) $request->input('save_action', 'draft');
        $status = $this->productService->resolveStatus($action, null, true);

        $product = $this->productService->createProduct(
            [
                'supplier_id' => $supplierId,
                'category_id' => $request->input('category_id'),
                'name' => $request->input('name'),
                'status' => $status,
                'system_visible' => $request->boolean('system_visible'),
                'web_visible' => $request->boolean('web_visible'),
                'price_locked' => $request->boolean('price_locked'),
                'created_by' => $supplierId,
                'updated_by' => $supplierId,
            ],
            $this->extractDescriptions($request),
            $this->extractVariants($request),
            $this->uploadImages($request),
            $this->uploadGuideline($request),
        );

        $message = $status === ProductStatus::ACTIVE
            ? 'Product published successfully.'
            : 'Product saved as draft.';

        return redirect()
            ->route('products.index')
            ->with('success', $message);
    }

    public function show(Product $product): View
    {
        $this->authorizeProductOwner($product);

        $product->load([
            'supplier.profile',
            'category',
            'descriptions',
            'variants',
            'images.file',
            'guidelineFile',
        ]);

        return view('pages.products.details', [
            'product' => $product,
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorizeProductOwner($product);

        $product->load([
            'category',
            'descriptions',
            'variants',
            'images.file',
            'guidelineFile',
        ]);

        return view('pages.products.index', $this->formViewData($product));
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProductOwner($product);
        $this->validateImageUploadLimit($request->file('images', []), $product->images()->count());

        $supplierId = (int) Auth::id();
        $action = (string) $request->input('save_action', 'draft');
        $status = $this->productService->resolveStatus($action, $product->status, false);

        $this->productService->updateProduct(
            $product,
            [
                'category_id' => $request->input('category_id'),
                'name' => $request->input('name'),
                'status' => $status,
                'system_visible' => $request->boolean('system_visible'),
                'web_visible' => $request->boolean('web_visible'),
                'price_locked' => $request->boolean('price_locked'),
                'updated_by' => $supplierId,
            ],
            $this->extractDescriptions($request),
            $this->extractVariants($request),
            $this->uploadImages($request),
            $request->hasFile('guideline') ? $this->uploadGuideline($request) : null,
        );

        $message = match ($action) {
            'publish' => 'Product published successfully.',
            'deactivate' => 'Product deactivated successfully.',
            'activate' => 'Product activated successfully.',
            default => 'Product updated successfully.',
        };

        return redirect()
            ->route('products.index')
            ->with('success', $message);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorizeProductOwner($product);

        $this->productService->deleteProduct($product);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function deactivate(Product $product): RedirectResponse
    {
        $this->authorizeProductOwner($product);

        $this->productService->deactivateProduct($product, (int) Auth::id());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product deactivated successfully.');
    }

    public function activate(Product $product): RedirectResponse
    {
        $this->authorizeProductOwner($product);

        $this->productService->activateProduct($product, (int) Auth::id());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product activated successfully.');
    }

    private function authorizeProductOwner(Product $product): void
    {
        abort_if((int) $product->supplier_id !== (int) Auth::id(), 403);
    }

    private function formViewData(?Product $product = null): array
    {
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'product' => $product,
            'categories' => $categories,
            'rootCategories' => $categories->filter(fn ($category) => empty($category->parent_id))->values(),
        ];
    }

    private function extractDescriptions(StoreProductRequest $request): array
    {
        $descriptions = [];

        foreach (['en', 'si', 'ta'] as $locale) {
            $value = $request->input('descriptions.' . $locale);

            if ($value !== null && $value !== '') {
                $descriptions[] = [
                    'language_code' => $locale,
                    'description' => $value,
                ];
            }
        }

        return $descriptions;
    }

    private function extractVariants(StoreProductRequest $request): array
    {
        $variants = [];
        $priceLocked = $request->boolean('price_locked');

        foreach ((array) $request->input('variants', []) as $index => $variant) {
            $sellingPrice = $variant['selling_price'] ?? 0;

            $variants[] = [
                'id' => $variant['id'] ?? null,
                'name' => $variant['name'] ?? null,
                'barcode' => $variant['barcode'] ?? null,
                'cost' => $variant['cost'] ?? 0,
                'selling_price' => $sellingPrice,
                'weight' => $variant['weight'] ?? null,
                'suggested_price' => $priceLocked
                    ? $sellingPrice
                    : ($variant['suggested_price'] ?? null),
                'company_commission' => $variant['company_commission'] ?? 150.00,
                'sort_order' => $index,
                'is_active' => true,
            ];
        }

        return $variants;
    }

    private function uploadImages(StoreProductRequest $request): array
    {
        $images = [];
        $files = $request->file('images', []);

        foreach ($files as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $uploaded = $this->fileServerService->uploadProductImage($file);

            $images[] = [
                'file_id' => $uploaded['id'],
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ];
        }

        return $images;
    }

    private function uploadGuideline(StoreProductRequest $request): ?array
    {
        if (! $request->hasFile('guideline')) {
            return null;
        }

        $uploaded = $this->fileServerService->uploadGuideline($request->file('guideline'));

        return [
            'file_id' => $uploaded['id'],
            'file_uuid' => $uploaded['uuid'],
        ];
    }

    private function validateImageUploadLimit(array $files, int $existingImageCount): void
    {
        $newUploads = collect($files)
            ->filter(fn ($file) => $file && $file->isValid())
            ->count();

        if ($existingImageCount + $newUploads > self::MAX_PRODUCT_IMAGES) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'images' => ['You can upload up to 4 images for each product.'],
            ]);
        }
    }
}
