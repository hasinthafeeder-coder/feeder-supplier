@extends('layout_main.app')

@php
    use Feeder\Core\Support\CurrencyDisplay;

    $grn = $grn ?? null;
    $currency = $currency ?? null;
    $invoiceUuid = $grn?->invoiceFile?->uuid;
    $invoiceName = $grn?->invoiceFile?->original_name ?? 'invoice';
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">GRN Details</h3>

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
                        <a href="{{ route('grns.index') }}" class="text-decoration-none"><span>GRNs</span></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">{{ $grn->grn_number }}</span>
                    </li>
                </ol>
            </nav>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="{{ route('grns.edit', $grn) }}" class="btn btn-primary">Edit GRN</a>
            <a href="{{ route('grns.index') }}" class="btn btn-outline-secondary">Back to List</a>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <h5 class="mb-20">GRN Information</h5>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-muted fs-14">GRN Number</div>
                    <div class="fw-medium fs-16">{{ $grn->grn_number }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Supplier</div>
                    <div class="fw-medium fs-16">{{ $grn->supplier?->company?->name ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Received Date</div>
                    <div class="fw-medium fs-16">{{ $grn->received_date?->format('Y-m-d') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Invoice Number</div>
                    <div class="fw-medium fs-16">{{ $grn->invoice_number ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Invoice File</div>
                    <div class="fw-medium fs-16">
                        @if ($invoiceUuid)
                            <a href="{{ route('files.view', ['uuid' => $invoiceUuid]) }}" target="_blank"
                                class="text-primary text-decoration-none">
                                {{ $invoiceName }}
                            </a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Created Date</div>
                    <div class="fw-medium fs-16">{{ $grn->created_at?->format('Y-m-d H:i') }}</div>
                </div>
                @if ($grn->notes)
                    <div class="col-12">
                        <div class="text-muted fs-14">Notes</div>
                        <div class="fs-16">{{ $grn->notes }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20">
                <h5 class="mb-20">GRN Items</h5>
            </div>
            <div class="default-table-area mx-minus-1">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Product</th>
                                <th scope="col" class="fw-medium">Variant</th>
                                <th scope="col" class="fw-medium">Unit Cost</th>
                                <th scope="col" class="fw-medium">Received Qty</th>
                                <th scope="col" class="fw-medium">Damaged Qty</th>
                                <th scope="col" class="fw-medium">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($grn->items as $item)
                                <tr>
                                    <td>{{ $item->product_name_snapshot }}</td>
                                    <td>{{ $item->variant_name_snapshot }}</td>
                                    <td>{{ CurrencyDisplay::formatAmount($currency, $item->unit_cost) }}</td>
                                    <td>{{ number_format($item->received_quantity) }}</td>
                                    <td>{{ number_format($item->damaged_quantity) }}</td>
                                    <td>{{ $item->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No items recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
