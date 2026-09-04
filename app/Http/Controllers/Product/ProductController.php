<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Services\FileServerService;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\User;
use Feeder\Core\Services\MarketDefaultCompanyCommissionService;
use Feeder\Core\Services\ProductMarketLanguageService;
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
        private readonly MarketDefaultCompanyCommissionService $marketCommissionService,
        private readonly ProductMarketLanguageService $productLanguageService,
    ) {}

    public function index(): View
    {
        $supplierId = (int) Auth::id();

        $products = Product::query()
            ->forSupplier($supplierId)
            ->with(['category', 'variants', 'images.file', 'market.country', 'market.currency'])
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
            'market.country',
            'market.currency',
            'descriptions',
            'variants',
            'images.file',
            'guidelineFile',
        ]);

        return view('pages.products.details', [
            'product' => $product,
            'productLanguages' => $this->productLanguageService->languagesForMarket($product->market),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorizeProductOwner($product);

        $product->load([
            'category',
            'market.country',
            'market.currency',
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

        $data = [
            'product' => $product,
            'categories' => $categories,
            'rootCategories' => $categories->filter(fn ($category) => empty($category->parent_id))->values(),
        ];

        if ($product !== null) {
            $data['productMarketContext'] = $this->productMarketContext($product);
            $data['defaultCompanyCommission'] = $this->resolveDefaultCompanyCommission($product->market);
            $data['productLanguages'] = $this->productLanguageService->languagesForMarket($product->market);
        } else {
            $supplierMarketContext = $this->resolveSupplierMarketContext();
            $data['supplierMarketContext'] = $supplierMarketContext;
            $data['defaultCompanyCommission'] = $supplierMarketContext['default_company_commission'];
            $data['productLanguages'] = $this->productLanguageService->languagesForMarket($supplierMarketContext['market']);
        }

        return $data;
    }

    /**
     * @return array{country_name: string, currency: Currency, market: Market, default_company_commission: string}
     */
    private function resolveSupplierMarketContext(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('company.operationMarket.country', 'company.operationMarket.currency');

        $market = $user->company?->operationMarket;

        if (! $this->isMarketContextComplete($market)) {
            abort(403, 'Your supplier operation market is not configured. Please contact support before creating products.');
        }

        return [
            'country_name' => $market->country->name,
            'currency' => $market->currency,
            'market' => $market,
            'default_company_commission' => $this->resolveDefaultCompanyCommission($market),
        ];
    }

    private function resolveDefaultCompanyCommission(?Market $market): string
    {
        if ($market === null) {
            abort(403, 'Market context is required to resolve default company commission.');
        }

        return $this->marketCommissionService->getDefaultCompanyCommission($market);
    }

    /**
     * @return array{country_name: ?string, currency: ?Currency, is_complete: bool}
     */
    private function productMarketContext(Product $product): array
    {
        $product->loadMissing('market.country', 'market.currency');
        $market = $product->market;

        return [
            'country_name' => $market?->country?->name,
            'currency' => $market?->currency,
            'is_complete' => $this->isMarketContextComplete($market),
        ];
    }

    private function isMarketContextComplete(?Market $market): bool
    {
        return $market !== null
            && $market->country !== null
            && $market->currency !== null
            && filled($market->currency->iso_code);
    }

    private function extractDescriptions(StoreProductRequest $request): array
    {
        return $this->productLanguageService->normalizeDescriptionsForMarket(
            $request->resolvedProductMarket(),
            (array) $request->input('descriptions', [])
        );
    }

    private function extractVariants(StoreProductRequest $request): array
    {
        $variants = [];
        $priceLocked = $request->boolean('price_locked');

        foreach ((array) $request->input('variants', []) as $index => $variant) {
            $variants[] = [
                'id' => $variant['id'] ?? null,
                'name' => $variant['name'] ?? null,
                'barcode' => $variant['barcode'] ?? null,
                'cost' => $variant['cost'] ?? 0,
                'selling_price' => $variant['selling_price'] ?? 0,
                'weight' => $variant['weight'] ?? null,
                'suggested_price' => $priceLocked ? ($variant['suggested_price'] ?? null) : null,
                'suggested_price_min' => $priceLocked ? null : ($variant['suggested_price_min'] ?? null),
                'suggested_price_max' => $priceLocked ? null : ($variant['suggested_price_max'] ?? null),
                'company_commission' => $variant['company_commission'] ?? null,
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
