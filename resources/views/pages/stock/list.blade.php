@extends('layout_main.app')

@php
    $variants = $variants ?? null;
    $counts = $counts ?? ['all' => 0, 'in_stock' => 0, 'out_of_stock' => 0];
    $filters = $filters ?? ['search' => ''];
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">Stock</h3>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span>Inventory</span>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Stock</span>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-20">
                <div class="d-flex flex-wrap gap-2 gap-xxl-5 align-items-center">
                    <form method="GET" action="{{ route('stock.index') }}" class="table-src-form position-relative m-0">
                        <input type="text" class="form-control w-340" name="search"
                            value="{{ $filters['search'] }}" placeholder="Search product, variant, barcode...">
                        <button type="submit"
                            class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                            <span class="material-symbols-outlined">search</span>
                        </button>
                    </form>
                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center flex-wrap" style="gap: 20px;">
                        <li class="fs-16">
                            All Variants <span class="text-primary">({{ number_format($counts['all']) }})</span>
                        </li>
                        <li class="fs-16">
                            In Stock <span class="text-primary">({{ number_format($counts['in_stock']) }})</span>
                        </li>
                        <li class="fs-16">
                            Out of Stock <span class="text-primary">({{ number_format($counts['out_of_stock']) }})</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="default-table-area mx-minus-1">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Product</th>
                                <th scope="col" class="fw-medium">Variant</th>
                                <th scope="col" class="fw-medium">Category</th>
                                <th scope="col" class="fw-medium">Barcode</th>
                                <th scope="col" class="fw-medium">Remaining Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($variants as $variant)
                                @php
                                    $product = $variant->product;
                                    $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
                                    $imageUuid = $primaryImage?->file_uuid;
                                    $remainingStock = (int) ($variant->remaining_stock ?? 0);
                                @endphp
                                <tr>
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
                                                    class="fs-16 text-secondary text-decoration-none hover-text fw-medium">
                                                    {{ $product->name }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-body">{{ $variant->name }}</td>
                                    <td class="text-body">{{ $product->category?->name ?? '—' }}</td>
                                    <td class="text-body">{{ $variant->barcode ?: '—' }}</td>
                                    <td class="text-body">
                                        @if ($remainingStock > 0)
                                            <span class="badge bg-success-subtle text-success border border-success border-opacity-10">
                                                {{ number_format($remainingStock) }} in stock
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-10">
                                                Out of Stock
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No product variants found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', [
                    'paginator' => $variants,
                    'ariaLabel' => 'Stock list pagination',
                ])
            </div>
        </div>
    </div>
@endsection
