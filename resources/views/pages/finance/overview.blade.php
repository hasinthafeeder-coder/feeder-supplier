@extends('layout_main.app')

@php
    $filters = $filters ?? ['date_from' => '', 'date_to' => ''];
    $rows = $rows ?? null;
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierFinancialOverviewPage" data-party-financial-overview>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Supplier Financial Overview</h3>
                <p class="fs-15 text-body mb-0">Your settlement position. LKR and MYR stay separate. Generated batches, invoices, and bank-export files are not payments.</p>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Financial Overview</span>
                    </li>
                </ol>
            </nav>
        </div>

        @include('pages.finance.partials.market-summary', [
            'overviewMarkets' => $overviewMarkets ?? collect(),
            'overviewMarketsUnavailable' => $overviewMarketsUnavailable ?? false,
            'marketSummaries' => $marketSummaries ?? [],
            'cards' => [
                [
                    'id' => 'supplier-total-payable-card',
                    'amount_key' => 'total_payable',
                    'title' => 'Total payable',
                    'icon' => 'account_balance_wallet',
                    'caption' => 'Sum of your line payable amounts for this market.',
                    'foot' => 'Your account',
                ],
                [
                    'id' => 'supplier-total-settled-card',
                    'amount_key' => 'total_paid',
                    'title' => 'Total settled/paid',
                    'icon' => 'payments',
                    'caption' => 'Your line amounts on invoices whose payment status is PAID. A reversed payment is unpaid.',
                    'foot' => 'Cash payments only',
                ],
                [
                    'id' => 'supplier-outstanding-card',
                    'amount_key' => 'outstanding',
                    'title' => 'Outstanding balance',
                    'icon' => 'pending',
                    'caption' => 'Your line amounts on invoices whose payment status is UNPAID.',
                    'foot' => 'By currency',
                ],
                [
                    'id' => 'supplier-today-payable-card',
                    'amount_key' => 'today_payable',
                    'title' => "Today's payable",
                    'icon' => 'today',
                    'caption' => "Your line amounts for batches whose settlement date is this market's current business date.",
                    'foot' => "Today's settlement date",
                ],
            ],
        ])

        <div class="card bg-white rounded-10 border border-white mb-4" id="supplierFinancialBatchReport">
            <div class="p-20 border-bottom">
                <h4 class="fs-16 mb-1">Batch-wise report</h4>
                <p class="fs-14 text-body mb-0">One row per invoice in your batches. Currencies are not combined.</p>
            </div>
            <div class="p-20 border-bottom">
                <form method="GET" action="{{ route('finance.overview') }}" id="supplierFinancialOverviewFilters" class="row g-3 align-items-end">
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="supplierOverviewDateFrom" class="label fs-14 mb-2">From</label>
                        <input type="date" class="form-control" id="supplierOverviewDateFrom" name="date_from" value="{{ $filters['date_from'] }}">
                        @error('date_from')
                            <div class="text-danger fs-14 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="supplierOverviewDateTo" class="label fs-14 mb-2">To</label>
                        <input type="date" class="form-control" id="supplierOverviewDateTo" name="date_to" value="{{ $filters['date_to'] }}">
                        @error('date_to')
                            <div class="text-danger fs-14 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-lg-6 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary text-white">Apply</button>
                        <a href="{{ route('finance.overview') }}" class="btn btn-light border">Reset</a>
                    </div>
                </form>
                @error('page')
                    <div class="text-danger fs-14 mt-3">{{ $message }}</div>
                @enderror
            </div>
            <div class="default-table-area mx-minus-1" id="supplierFinancialOverviewTable">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Batch</th>
                                <th scope="col" class="fw-medium">Business Date</th>
                                <th scope="col" class="fw-medium">Order Count</th>
                                <th scope="col" class="fw-medium" title="Sum of your supplier_settlement_invoice_lines.line_payable values. Not the company payable_total.">Net Payable</th>
                                <th scope="col" class="fw-medium">Status</th>
                                <th scope="col" class="fw-medium text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                @php
                                    $batchUuid = trim((string) ($row['batch_uuid'] ?? ''));
                                    $batchNumber = ($row['batch_number'] ?? '') !== '' ? $row['batch_number'] : '—';
                                    $status = ($row['payment_status'] ?? '') !== ''
                                        ? $row['payment_status']
                                        : (($row['batch_status'] ?? '') !== '' ? $row['batch_status'] : null);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium text-body">
                                            @if ($batchUuid !== '')
                                                <a href="{{ route('finance.batches.show', $batchUuid) }}">{{ $batchNumber }}</a>
                                            @else
                                                {{ $batchNumber }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-body">{{ ($row['settlement_date'] ?? '') !== '' ? $row['settlement_date'] : '—' }}</td>
                                    <td class="text-body">{{ $row['order_count'] ?? '—' }}</td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $row['net_payable'] ?? null, 'currency' => $row['currency_code'] ?? ''])</td>
                                    <td class="text-body">
                                        @include('partials.status-badge', ['status' => $status])
                                        @if (($row['payment_notice'] ?? '') !== '')
                                            <div class="fs-12">{{ $row['payment_notice'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($batchUuid !== '')
                                            <a href="{{ route('finance.batches.show', $batchUuid) }}" class="btn btn-sm btn-light border">View</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No batch rows are loaded. Amounts are not shown.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    @include('partials.pagination', [
                        'paginator' => $rows,
                        'ariaLabel' => 'Supplier financial overview pagination',
                    ])
                @endif
                <p class="fs-12 text-body mb-0 px-20 pb-20">Dates on this table do not change the summary cards.</p>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.badge-status-styles')
    <style>
        #supplierFinancialOverviewPage .finance-today-card h3 { font-size: 16px; line-height: 1.35; }
        #supplierFinancialOverviewPage .finance-today-foot { margin-top: 21px; }
        #supplierFinancialOverviewPage .finance-today-chip { padding: 3px 5px; }
    </style>
@endpush
