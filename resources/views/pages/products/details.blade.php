@extends('layout_main.app')

@php
    use Feeder\Core\Support\CurrencyDisplay;

    $product = $product ?? null;
    $images = $product?->images ?? collect();
    $variants = $product?->variants ?? collect();
    $primaryVariant = $variants->first();
    $statusValue = $product?->status instanceof \Feeder\Core\Enums\ProductStatus
        ? $product->status->value
        : (string) ($product?->status ?? 'DRAFT');
    $statusBadgeClass = match ($statusValue) {
        'ACTIVE' => 'bg-success-subtle text-success border border-success border-opacity-10',
        'INACTIVE' => 'bg-danger-subtle text-danger border border-danger border-opacity-10',
        default => 'bg-primary-subtle text-primary border border-primary border-opacity-10',
    };
    $statusLabel = match ($statusValue) {
        'ACTIVE' => 'Published',
        'INACTIVE' => 'Inactive',
        default => 'Draft',
    };
    $productLanguages = $productLanguages ?? [];
    $primaryLanguage = $productLanguages[0] ?? ['code' => 'en', 'label' => 'English'];
    $primaryDescription = $product?->descriptionFor($primaryLanguage['code']) ?? '';
    $guidelineUuid = $product?->guidelineFile?->uuid;
    $guidelineName = $product?->guidelineFile?->original_name ?? 'product-guideline.pdf';
    $fallbackImage = asset('assets/images/product-details1.jpg');
    $productCurrency = CurrencyDisplay::currencyFromMarket($product?->market);
    $productMarketCountry = $product?->market?->country?->name;
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">Product Details</h3>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span>E-Commerce</span>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <a href="{{ route('products.index') }}" class="text-decoration-none"><span>Products</span></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Product Details</span>
                    </li>
                </ol>
            </nav>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <div class="row g-4 align-items-start">
                <div class="col-lg-6">
                    <div class="border rounded-10 p-2 bg-light-subtle">
                        <div class="tab-content mb-3" id="productGalleryTabs">
                            @forelse ($images as $index => $image)
                                @php $imageUuid = $image->file_uuid; @endphp
                                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                                    id="gallery-{{ $index + 1 }}" role="tabpanel">
                                    <img src="{{ $imageUuid ? route('files.view', ['uuid' => $imageUuid]) : $fallbackImage }}"
                                        class="rounded-10 w-100" style="height: 500px; object-fit: cover;"
                                        alt="{{ $product->name }} image {{ $index + 1 }}">
                                </div>
                            @empty
                                <div class="tab-pane fade show active" id="gallery-1" role="tabpanel">
                                    <img src="{{ $fallbackImage }}" class="rounded-10 w-100"
                                        style="height: 500px; object-fit: cover;" alt="{{ $product->name }}">
                                </div>
                            @endforelse
                        </div>

                        <ul class="nav nav-tabs border-0 m-0" id="productGalleryThumbs" role="tablist" style="gap: 12px;">
                            @forelse ($images as $index => $image)
                                @php $imageUuid = $image->file_uuid; @endphp
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link p-0 border-0 {{ $index === 0 ? 'active' : '' }}"
                                        id="gallery-thumb-{{ $index + 1 }}" data-bs-toggle="tab"
                                        data-bs-target="#gallery-{{ $index + 1 }}" type="button" role="tab"
                                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                        <img src="{{ $imageUuid ? route('files.thumbnail', ['uuid' => $imageUuid, 'size' => 'md']) : $fallbackImage }}"
                                            class="rounded-10" style="width: 90px; height: 90px; object-fit: cover;"
                                            alt="thumb {{ $index + 1 }}">
                                    </button>
                                </li>
                            @empty
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link p-0 border-0 active" id="gallery-thumb-1" data-bs-toggle="tab"
                                        data-bs-target="#gallery-1" type="button" role="tab" aria-selected="true">
                                        <img src="{{ $fallbackImage }}" class="rounded-10"
                                            style="width: 90px; height: 90px; object-fit: cover;" alt="thumb 1">
                                    </button>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <span class="badge {{ $statusBadgeClass }} mb-3">
                                {{ $statusLabel }}
                            </span>
                            <h3 class="mb-0">{{ $product->name }}</h3>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                            <a href="{{ route('products.edit', $product) }}"
                                class="btn btn-outline-primary btn-sm">Edit Product</a>
                            @if ($statusValue === 'ACTIVE')
                                <form action="{{ route('products.deactivate', $product) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Deactivate</button>
                                </form>
                            @elseif ($statusValue === 'INACTIVE')
                                <form action="{{ route('products.activate', $product) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-sm">Activate</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="badge bg-light text-body border">Product ID: #{{ $product->id }}</span>
                        <span class="badge bg-light text-body border">Category: {{ $product->category?->name ?? '—' }}</span>
                        @if ($productMarketCountry)
                            <span class="badge bg-light text-body border">Market: {{ $productMarketCountry }}</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-10">Market unavailable</span>
                        @endif
                        @if ($product->system_visible)
                            <span class="badge bg-light text-body border">System Visible</span>
                        @endif
                        @if ($primaryVariant?->barcode)
                            <span class="badge bg-light text-body border">Barcode: {{ $primaryVariant->barcode }}</span>
                        @endif
                        <span class="badge bg-light text-body border">Created: {{ optional($product->created_at)->format('M d, Y') }}</span>
                        @if ($product->supplier)
                            <span class="badge bg-light text-body border">Supplier: {{ $product->supplier->email }}</span>
                        @endif
                    </div>

                    <div class="mb-4">
                        <p class="mb-0 fs-16 lh-1-8 text-body">
                            {{ $primaryDescription !== '' ? $primaryDescription : 'No ' . $primaryLanguage['label'] . ' description provided.' }}
                        </p>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Cost</div>
                                <div class="fs-16 fw-medium">
                                    {{ $primaryVariant ? CurrencyDisplay::formatAmount($productCurrency, $primaryVariant->cost) : '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Selling Price</div>
                                <div class="fs-16 fw-medium">
                                    {{ $primaryVariant ? CurrencyDisplay::formatAmount($productCurrency, $primaryVariant->selling_price) : '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Suggested Price</div>
                                <div class="fs-16 fw-medium">
                                    {{ $primaryVariant && $primaryVariant->suggested_price !== null ? CurrencyDisplay::formatAmount($productCurrency, $primaryVariant->suggested_price) : '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Approximate Delivery Charge</div>
                                <div class="fs-16 fw-medium">—</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Items Sold</div>
                                <div class="fs-16 fw-medium">—</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Weight</div>
                                <div class="fs-16 fw-medium">
                                    {{ $primaryVariant && $primaryVariant->weight !== null ? number_format((float) $primaryVariant->weight, 3) . ' kg' : '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Category</div>
                                <div class="fs-16 fw-medium">{{ $product->category?->name ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                                <div class="text-muted fs-13 mb-1">Product Status</div>
                                <div class="fs-16 fw-medium">{{ $statusValue }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($variants->count() > 1)
                        <div class="border rounded-10 p-3 bg-light-subtle mb-4">
                            <div class="text-muted fs-13 mb-2">Variants</div>
                            <div class="d-flex flex-column gap-2">
                                @foreach ($variants as $variant)
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <span class="fw-medium">{{ $variant->name }}</span>
                                        <span class="text-body">
                                            {{ CurrencyDisplay::formatAmount($productCurrency, $variant->selling_price) }}
                                            · Cost {{ CurrencyDisplay::formatAmount($productCurrency, $variant->cost) }}
                                            @if ($variant->barcode)
                                                · {{ $variant->barcode }}
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="border rounded-10 p-3 bg-light-subtle mb-4">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="text-muted fs-13">Product Guideline (PDF)</div>
                                <div class="fw-medium">{{ $guidelineUuid ? $guidelineName : 'No guideline uploaded' }}</div>
                            </div>
                            @if ($guidelineUuid)
                                <a href="{{ route('files.view', ['uuid' => $guidelineUuid]) }}" target="_blank"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="ri-download-line me-1"></i>
                                    Download PDF
                                </a>
                            @else
                                <span class="btn btn-outline-secondary btn-sm disabled">Download PDF</span>
                            @endif
                        </div>
                    </div>

                    <div class="border rounded-10 p-3 bg-light-subtle">
                        <div class="text-muted fs-13 mb-2">Visibility & Pricing</div>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($product->system_visible)
                                <span
                                    class="badge bg-success-subtle text-success border border-success border-opacity-10">System Visible</span>
                            @endif
                            @if ($product->web_visible)
                                <span
                                    class="badge bg-info-subtle text-info border border-info border-opacity-10">Web Visible</span>
                            @endif
                            @if ($product->price_locked)
                                <span
                                    class="badge bg-warning-subtle text-warning border border-warning border-opacity-10">Price Lock</span>
                            @endif
                            @if (! $product->system_visible && ! $product->web_visible && ! $product->price_locked)
                                <span class="badge bg-light text-body border">No visibility flags set</span>
                            @endif
                        </div>
                        <div class="text-muted fs-12 mt-2">
                            Updated {{ optional($product->updated_at)->format('M d, Y H:i') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white">
            <div class="d-flex justify-content-between align-items-center mb-20 flex-wrap gap-2">
                <h4 class="mb-0">Product Information</h4>
                <span class="badge bg-light text-body border">Updated {{ optional($product->updated_at)->diffForHumans() }}</span>
            </div>

            <ul class="nav nav-tabs nav-tabs-separator" id="productInfoTabs" role="tablist">
                @foreach ($productLanguages as $index => $language)
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link {{ $index === 0 ? 'active' : '' }}"
                            id="info-{{ $language['code'] }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#info-{{ $language['code'] }}"
                            type="button"
                            role="tab"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                        >
                            {{ $language['label'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content border border-top-0 rounded-bottom-10 p-4 bg-white">
                @foreach ($productLanguages as $index => $language)
                    @php
                        $description = $product?->descriptionFor($language['code']) ?? '';
                    @endphp
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="info-{{ $language['code'] }}" role="tabpanel">
                        <p class="mb-0 fs-16 lh-1-8 text-body">
                            {{ $description !== '' ? $description : 'No ' . $language['label'] . ' description provided.' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white mt-4">
            <div class="d-flex justify-content-between align-items-center mb-20 flex-wrap gap-2">
                <h4 class="mb-0">Reviews & Ratings</h4>
                <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-10">No reviews yet</span>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="border rounded-10 p-4 bg-light-subtle text-center h-100">
                        <div class="fs-36 fw-bold text-warning mb-2">—</div>
                        <div class="mb-2">
                            <i class="ri-star-line text-warning"></i>
                            <i class="ri-star-line text-warning"></i>
                            <i class="ri-star-line text-warning"></i>
                            <i class="ri-star-line text-warning"></i>
                            <i class="ri-star-line text-warning"></i>
                        </div>
                        <div class="text-muted">Based on 0 reviews</div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="d-flex flex-column gap-3">
                        <div class="border rounded-10 p-3 bg-light-subtle">
                            <p class="mb-0 text-body fs-14 lh-1-7">
                                Reviews are not available for this product yet.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-grow-1"></div>

    <style>
        .nav-tabs-separator .nav-link {
            border: 1px solid #e5e7eb;
            border-bottom: 0;
            margin-right: 8px;
            border-radius: 8px 8px 0 0;
            color: #6b7280;
            background: #f8fafc;
        }

        .nav-tabs-separator .nav-link.active {
            background: #fff;
            color: #111827;
            border-color: #e5e7eb;
            font-weight: 600;
        }
    </style>
@endsection
