@extends('layout_main.app')

@php
    $summary = $summary ?? [];
    $currency = ($summary['currency_code'] ?? '') !== '' ? $summary['currency_code'] : '—';
    $lines = $lines ?? collect();
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierSettlementBatchDetailPage">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Batch {{ $batch->batch_number ?: '—' }}</h3>
                <p class="fs-15 text-body mb-0">
                    {{ optional($batch->settlement_date)->format('Y-m-d') ?? '—' }}
                    · {{ $currency }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.overview') }}" class="btn btn-light border">Back to financial overview</a>
            </div>
        </div>

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb align-items-center mb-0 lh-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                        <span class="text-body fs-14 hover">Dashboard</span>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('finance.overview') }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Financial Overview</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">{{ $batch->batch_number ?: 'Batch' }}</span>
                </li>
            </ol>
        </nav>

        <div class="alert alert-light border mb-3" role="note">
            <div class="fw-semibold fs-14 mb-1">Your settlement lines only</div>
            <p class="fs-13 text-body mb-0">
                Amounts below come from your persisted supplier settlement line snapshots for this batch.
                Company invoice totals, bank-export files, and Admin payment ledgers are not shown here.
            </p>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-0">Summary</h4>
            </div>
            <div class="p-20">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Currency</div>
                        <div class="fw-semibold">{{ $currency }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Payment status</div>
                        <div class="fw-semibold">{{ ($summary['payment_status'] ?? '') !== '' ? $summary['payment_status'] : '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Instruction status</div>
                        <div class="fw-semibold">{{ ($summary['instruction_status'] ?? '') !== '' ? $summary['instruction_status'] : '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Batch status</div>
                        <div class="fw-semibold">
                            @include('partials.status-badge', ['status' => $summary['batch_status'] ?? null])
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Your line count</div>
                        <div class="fw-semibold">{{ (int) ($summary['line_count'] ?? 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Net payable</div>
                        <div class="fw-semibold">
                            @include('pages.finance.partials.stored-amount', [
                                'amount' => $summary['net_payable'] ?? null,
                                'currency' => $summary['currency_code'] ?? '',
                            ])
                        </div>
                        <div class="fs-12 text-muted mt-1">Sum of your line payable amounts</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4" id="supplierSettlementOrderBreakdown">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-1">Order-wise breakdown</h4>
                <p class="fs-13 text-body mb-0">
                    Settlement lines included for your account on this batch.
                    Unit cost and line payable are historical snapshots, not current catalogue prices.
                </p>
            </div>
            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Order / waybill</th>
                                <th scope="col" class="fw-medium">Settlement date</th>
                                <th scope="col" class="fw-medium">Order date</th>
                                <th scope="col" class="fw-medium">Status</th>
                                <th scope="col" class="fw-medium">Product / variant</th>
                                <th scope="col" class="fw-medium">Qty</th>
                                <th scope="col" class="fw-medium">Unit cost</th>
                                <th scope="col" class="fw-medium">Line payable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $row)
                                @php
                                    $rowCurrency = $row['currency_code'] ?? ($summary['currency_code'] ?? null);
                                    $productLabel = trim(implode(' / ', array_filter([
                                        $row['product_name'] ?? null,
                                        $row['variant_name'] ?? null,
                                    ])));
                                @endphp
                                <tr>
                                    <td class="text-body">
                                        <div class="fw-medium">{{ $row['order_number'] ?: '—' }}</div>
                                        @if (! empty($row['waybill']))
                                            <div class="fs-12">{{ $row['waybill'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-body">{{ $row['settlement_date'] ?: '—' }}</td>
                                    <td class="text-body">{{ $row['order_date'] ?: '—' }}</td>
                                    <td class="text-body">
                                        @include('partials.status-badge', ['status' => $row['order_status'] ?? null])
                                        @if (! empty($row['line_payment_status']))
                                            <div class="fs-12">Line {{ $row['line_payment_status'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-body">{{ $productLabel !== '' ? $productLabel : '—' }}</td>
                                    <td class="text-body">
                                        @if ($row['quantity'] !== null)
                                            {{ number_format((int) $row['quantity']) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @include('pages.finance.partials.stored-amount', [
                                            'amount' => $row['unit_cost'] ?? null,
                                            'currency' => $rowCurrency,
                                        ])
                                    </td>
                                    <td class="text-body">
                                        @include('pages.finance.partials.stored-amount', [
                                            'amount' => $row['line_payable'] ?? null,
                                            'currency' => $rowCurrency,
                                        ])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No settlement lines on this batch for your account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($lines instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    @include('partials.pagination', [
                        'paginator' => $lines,
                        'ariaLabel' => 'Supplier settlement batch line pagination',
                    ])
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.badge-status-styles')
@endpush
