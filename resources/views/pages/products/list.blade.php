@extends('layout_main.app')

@php
    use Feeder\Core\Support\CurrencyDisplay;

    $counts = $counts ?? ['all' => 0, 'active' => 0, 'draft' => 0, 'inactive' => 0];
    $products = $products ?? collect();
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">Products List</h3>

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
                        <span class="text-secondary">Products List</span>
                    </li>
                </ol>
            </nav>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-20">
                <div class="d-flex flex-wrap gap-2 gap-xxl-5 align-items-center">
                    <form class="table-src-form position-relative m-0">
                        <input type="text" class="form-control w-340" placeholder="Search here...">
                        <div
                            class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                            <span class="material-symbols-outlined">search</span>
                        </div>
                    </form>
                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center flex-wrap" style="gap: 20px;">
                        <li class="fs-16">
                            All Products <span class="text-primary">({{ number_format($counts['all']) }})</span>
                        </li>
                        <li class="fs-16">
                            Published Products <span class="text-primary">({{ number_format($counts['active']) }})</span>
                        </li>
                        <li class="fs-16">
                            Drafts Products <span class="text-primary">({{ number_format($counts['draft']) }})</span>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('products.create') }}" class="text-primary fs-16 text-decoration-none"> + Add New Product </a>
            </div>

            <div class="default-table-area mx-minus-1 table-product-list">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">
                                    <div class="form-check position-relative" style="top: -3px;">
                                        <input class="form-check-input" type="checkbox" id="flexCheckDefault1">
                                    </div>
                                </th>
                                <th scope="col" class="fw-medium ps-0">Product ID</th>
                                <th scope="col" class="fw-medium">Product</th>
                                <th scope="col" class="fw-medium">Category</th>
                                <th scope="col" class="fw-medium">Price</th>
                                <th scope="col" class="fw-medium">Stock Quantity</th>
                                <th scope="col" class="fw-medium">Sales</th>
                                <th scope="col" class="fw-medium">Revenue</th>
                                <th scope="col" class="fw-medium">Rating</th>
                                <th scope="col" class="fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                @php
                                    $primaryVariant = $product->variants->first();
                                    $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
                                    $productCurrency = CurrencyDisplay::currencyFromMarket($product->market);
                                    $imageUuid = $primaryImage?->file_uuid;
                                    $statusValue = $product->status instanceof \Feeder\Core\Enums\ProductStatus
                                        ? $product->status->value
                                        : (string) $product->status;
                                    $statusBadgeClass = match ($statusValue) {
                                        'ACTIVE' => 'bg-success-subtle text-success border border-success border-opacity-10',
                                        'INACTIVE' => 'bg-danger-subtle text-danger border border-danger border-opacity-10',
                                        default => 'bg-primary-subtle text-primary border border-primary border-opacity-10',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-body" style="width: 62px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="{{ $product->uuid }}">
                                        </div>
                                    </td>
                                    <td class="text-body ps-0">#{{ $product->id }}</td>
                                    <td class="text-body">
                                        <div class="d-flex align-items-center">
                                            <a href="{{ route('products.show', $product) }}" class="flex-shrink-0">
                                                @if ($imageUuid)
                                                    <img src="{{ route('files.thumbnail', ['uuid' => $imageUuid, 'size' => 'md']) }}"
                                                        style="width: 50px; height: 50px;" class="rounded-circle"
                                                        alt="{{ $product->name }}">
                                                @else
                                                    <img src="{{ asset('assets/images/product6.png') }}"
                                                        style="width: 50px; height: 50px;" class="rounded-circle"
                                                        alt="{{ $product->name }}">
                                                @endif
                                            </a>
                                            <div class="flex-grow-1 ms-12">
                                                <a href="{{ route('products.show', $product) }}"
                                                    class="fs-16 text-secondary text-decoration-none hover-text fw-medium">{{ $product->name }}</a>
                                                <div class="mt-1">
                                                    <span class="badge {{ $statusBadgeClass }}">{{ $statusValue }}</span>
                                                </div>
                                                <div class="mt-1">
                                                    @if ($product->market?->country?->iso_code && $productCurrency)
                                                        <span class="badge bg-light text-body border">
                                                            {{ $product->market->country->iso_code }} &bull;
                                                            {{ CurrencyDisplay::inputLabel($productCurrency) }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-10">Market unavailable</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-body">{{ $product->category?->name ?? '—' }}</td>
                                    <td class="text-body">
                                        {{ $primaryVariant
                                            ? CurrencyDisplay::formatProductVariantListPrice(
                                                $productCurrency,
                                                (bool) $product->price_locked,
                                                $primaryVariant->selling_price,
                                                $primaryVariant->suggested_price,
                                                $primaryVariant->suggested_price_min,
                                                $primaryVariant->suggested_price_max,
                                            )
                                            : '—' }}
                                    </td>
                                    <td class="text-body">—</td>
                                    <td class="text-body">—</td>
                                    <td class="text-body">—</td>
                                    <td class="text-body">—</td>
                                    <td>
                                        <div class="d-flex justify-content-end" style="gap: 12px;">
                                            <a href="{{ route('products.show', $product) }}"
                                                class="bg-transparent p-0 border-0 text-decoration-none"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                data-bs-title="view Product">
                                                <i class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                            </a>
                                            <a href="{{ route('products.edit', $product) }}"
                                                class="bg-transparent p-0 border-0 text-decoration-none hover-text-success"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                data-bs-title="Edit Product">
                                                <i class="material-symbols-outlined fs-16 fw-normal text-body">edit</i>
                                            </a>
                                            <form action="{{ route('products.destroy', $product) }}" method="POST"
                                                onsubmit="return confirm('Delete this product?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="bg-transparent p-0 border-0 hover-text-danger"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    data-bs-title="Delete">
                                                    <i class="material-symbols-outlined fs-16 fw-normal text-body">delete</i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">No products found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                    <span class="fs-15">Showing {{ $products->count() }} of {{ $counts['all'] }} entries</span>

                    <nav class="custom-pagination" aria-label="Page navigation example">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item">
                                <button class="page-link icon" aria-label="Previous" disabled>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            <li class="page-item">
                                <button class="page-link active">1</button>
                            </li>
                            <li class="page-item">
                                <button class="page-link icon" aria-label="Next" disabled>
                                    <i class="material-symbols-outlined">east</i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@endsection
