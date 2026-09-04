@extends('layout_main.app')

@php
    $filters = $filters ?? ['search' => ''];
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">GRN List</h3>

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
                        <span class="text-secondary">GRN List</span>
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
                    <form method="GET" action="{{ route('grns.index') }}" class="table-src-form position-relative m-0">
                        <input type="text" class="form-control w-340" name="search"
                            value="{{ $filters['search'] ?? '' }}" placeholder="Search GRN or invoice number...">
                        <button type="submit"
                            class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                            <span class="material-symbols-outlined">search</span>
                        </button>
                    </form>
                    <span class="fs-16">
                        Total GRNs <span class="text-primary">({{ number_format($grns->total()) }})</span>
                    </span>
                </div>

                <a href="{{ route('grns.create') }}" class="text-primary fs-16 text-decoration-none">+ Create GRN</a>
            </div>

            <div class="default-table-area mx-minus-1">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">GRN Number</th>
                                <th scope="col" class="fw-medium">Received Date</th>
                                <th scope="col" class="fw-medium">Invoice Number</th>
                                <th scope="col" class="fw-medium">Items</th>
                                <th scope="col" class="fw-medium">Created Date</th>
                                <th scope="col" class="fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($grns as $grn)
                                <tr>
                                    <td class="fw-medium">{{ $grn->grn_number }}</td>
                                    <td>{{ $grn->received_date?->format('Y-m-d') }}</td>
                                    <td>{{ $grn->invoice_number ?: '—' }}</td>
                                    <td>{{ number_format($grn->items_count) }}</td>
                                    <td>{{ $grn->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="d-flex justify-content-end" style="gap: 12px;">
                                            <a href="{{ route('grns.show', $grn) }}"
                                                class="bg-transparent p-0 border-0 text-decoration-none"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                data-bs-title="View GRN">
                                                <i class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                            </a>
                                            <a href="{{ route('grns.edit', $grn) }}"
                                                class="bg-transparent p-0 border-0 text-decoration-none hover-text-success"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                data-bs-title="Edit GRN">
                                                <i class="material-symbols-outlined fs-16 fw-normal text-body">edit</i>
                                            </a>
                                            <form action="{{ route('grns.destroy', $grn) }}" method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this GRN?');">
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
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No GRNs found. <a href="{{ route('grns.create') }}">Create your first GRN</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', [
                    'paginator' => $grns,
                    'ariaLabel' => 'GRN list pagination',
                ])
            </div>
        </div>
    </div>
@endsection
