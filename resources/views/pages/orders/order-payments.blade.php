@extends('layout_main.app')

@php
    $summaryCounts = $summaryCounts ?? [
        'pending' => 0,
        'received' => 0,
        'missing' => 0,
        'pending_amount' => '0.00',
        'currency_code' => '',
        'last_upload_at' => '—',
        'last_upload_courier' => 'Not recorded',
    ];
    $courierUploads = $courierUploads ?? [];
    $orders = $orders ?? [];
    $uploadHistory = $uploadHistory ?? [];
    $unmatchedWaybills = $unmatchedWaybills ?? [];
    $duplicateRows = $duplicateRows ?? [];
    $courierOptions = $courierOptions ?? [];
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierOrderPaymentsPage"
        data-empty-message-pending="No delivered orders have an unpaid supplier settlement invoice."
        data-empty-message-history="No delivered orders found."
        data-empty-message-upload-history="Courier payment file uploads are not recorded on this screen."
        data-empty-message-filtered="No orders match your filters."
        data-empty-message-missing="No delivered orders are missing a supplier settlement record."
        data-per-page="25"
        data-courier-uploads='@json($courierUploads)'
        data-orders='@json($orders)'
        data-upload-history='@json($uploadHistory)'
        data-unmatched-waybills='@json($unmatchedWaybills)'
        data-duplicate-rows='@json($duplicateRows)'
        data-courier-options='@json($courierOptions)'
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Order Payments',
            'pageCopy' => 'Review delivered orders against supplier settlement invoices and recorded payments.',
            'crumbs' => [
                ['label' => 'Order Payments'],
            ],
        ])

        <div id="orderPaymentsAlertHost" class="alert d-none" role="status"></div>

        <div class="alert alert-light border mb-3 dispatch-note-callout" role="note">
            <div class="d-flex align-items-start gap-2">
                <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
                <div>
                    <div class="fw-semibold fs-14 mb-1">Supplier settlement payments</div>
                    <p class="fs-13 text-body mb-0">
                        Payment status comes from supplier settlement invoices and recorded payments.
                        A delivered order is not treated as paid unless a settlement line is paid.
                        Courier payment file upload is not available on this screen.
                        Returned orders are handled separately and may require financial adjustment.
                    </p>
                </div>
            </div>
        </div>

        <div class="dispatch-summary-cards order-payments-summary-cards" role="group" aria-label="Payment status summary">
            <button type="button" class="dispatch-summary-card" data-payment-filter="UNPAID" aria-pressed="false">
                <div class="summary-label">Unpaid Settlement</div>
                <p class="summary-count" id="orderPaymentsSummaryPending">{{ number_format((int) $summaryCounts['pending']) }}</p>
                <div class="summary-supporting">Delivered orders with an unpaid settlement invoice</div>
            </button>
            <button type="button" class="dispatch-summary-card" data-payment-filter="PAID" aria-pressed="false">
                <div class="summary-label">Settlement Paid</div>
                <p class="summary-count" id="orderPaymentsSummaryReceived">{{ number_format((int) $summaryCounts['received']) }}</p>
                <div class="summary-supporting">Orders with a paid supplier settlement line</div>
            </button>
            <div class="dispatch-summary-card is-static" aria-hidden="false">
                <div class="summary-label">Unpaid Payable</div>
                <p class="summary-count" id="orderPaymentsSummaryAmount">
                    @if ($summaryCounts['pending_amount'] === null)
                        —
                    @else
                        {{ trim(($summaryCounts['currency_code'] ?? '').' '.$summaryCounts['pending_amount']) }}
                    @endif
                </p>
                <div class="summary-supporting">Settlement line payable still unpaid. Orders with no settlement record are excluded.</div>
            </div>
            <div class="dispatch-summary-card is-static" aria-hidden="false">
                <div class="summary-label">Last Upload</div>
                <p class="summary-count summary-count-sm" id="orderPaymentsSummaryLastUpload">
                    {{ $summaryCounts['last_upload_at'] }}
                </p>
                <div class="summary-supporting">{{ $summaryCounts['last_upload_courier'] }}</div>
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Upload Courier Payment File</h4>
                    <div class="results-meta mt-1">Courier payment file upload is not available on this screen.</div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="orderPaymentsCourierTable">
                        <thead>
                            <tr>
                                <th scope="col">Courier Service</th>
                                <th scope="col">Last Upload</th>
                                <th scope="col" class="text-end">Records</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="orderPaymentsCourierTableBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading courier services...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('pages.orders.partials.order-payments-filters', [
            'courierOptions' => $courierOptions,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Order Payments</h4>
                    <div class="results-meta mt-1">Delivered orders and supplier settlement payment state.</div>
                    <div class="results-meta mt-1" id="orderPaymentsResultsMeta"></div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="orderPaymentsTable">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Courier</th>
                                <th scope="col">Waybill</th>
                                <th scope="col">Invoice</th>
                                <th scope="col">COD Amount</th>
                                <th scope="col">Delivery Status</th>
                                <th scope="col">Payment Status</th>
                                <th scope="col">Payment Received At</th>
                                <th scope="col">Updated</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="orderPaymentsTableBody">
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading order payments...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="orderPaymentsPaginationHost"></div>
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Upload History</h4>
                    <div class="results-meta mt-1">Courier payment file uploads are not recorded for suppliers</div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="orderPaymentsUploadHistoryTable">
                        <thead>
                            <tr>
                                <th scope="col">File</th>
                                <th scope="col">Courier</th>
                                <th scope="col">Uploaded At</th>
                                <th scope="col" class="text-end">Records</th>
                                <th scope="col" class="text-end">Matched</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="orderPaymentsUploadHistoryBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading upload history...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('pages.orders.partials.order-payments-upload-modal')
    @include('pages.orders.partials.order-payments-review-modal')
    @include('pages.orders.partials.order-payments-detail-modal')
    @include('pages.orders.partials.order-payments-unmatched-modal')
    @include('pages.orders.partials.order-payments-duplicates-modal')
    @include('pages.orders.partials.order-payments-history-view-modal')
@endsection

@push('scripts')
    @include('pages.orders.partials.order-payments-scripts')
@endpush
