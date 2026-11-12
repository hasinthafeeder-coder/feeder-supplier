@extends('layout_main.app')

@php
    $pendingCouriers = $pendingCouriers ?? [];
    $dispatchHistory = $dispatchHistory ?? [];
    $courierOptions = $courierOptions ?? [];
    $supplierName = $supplierName ?? 'Supplier';
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierDispatchSummaryPage"
        data-empty-message-pending="No dispatched, delivered, or returned orders found."
        data-empty-message-history="No Dispatch Notes recorded yet."
        data-empty-message-filtered="No Dispatch Notes match your filters."
        data-per-page="25"
        data-supplier-name="{{ $supplierName }}"
        data-create-url="{{ route('orders.dispatch-summary.notes.store') }}"
        data-print-url-template="{{ route('orders.dispatch-summary.notes.print', ['uuid' => '__UUID__']) }}"
        data-pending-couriers='@json($pendingCouriers)'
        data-dispatch-history='@json($dispatchHistory)'
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Dispatch Summary',
            'pageCopy' => 'Review pending courier handover and recorded Dispatch Notes. Creating a note does not change order status.',
            'crumbs' => [
                ['label' => 'Dispatch Summary'],
            ],
        ])

        <div id="dispatchSummaryAlertHost" class="alert d-none" role="status"></div>


        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Pending Courier Handover</h4>
                    <div class="results-meta mt-1">Dispatched orders awaiting courier handover.</div>
                </div>
            </div>

            <div>
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="dispatchSummaryPendingTable">
                        <thead>
                            <tr>
                                <th scope="col">Courier Service</th>
                                <th scope="col" class="text-end">Orders Awaiting Handover</th>
                                <th scope="col">Last Dispatch</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchSummaryPendingTableBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading pending couriers...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('pages.orders.partials.dispatch-summary-filters', [
            'courierOptions' => $courierOptions,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Dispatch Notes History</h4>
                    <div class="results-meta mt-1" id="dispatchSummaryHistoryResultsMeta"></div>
                </div>
            </div>

            <div>
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="dispatchSummaryHistoryTable">
                        <thead>
                            <tr>
                                <th scope="col">Dispatch Note</th>
                                <th scope="col">Orders</th>
                                <th scope="col">Courier Service</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Operator</th>
                                <th scope="col" class="text-end">COD on Handover</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchSummaryHistoryTableBody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading Dispatch Notes...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="dispatchSummaryHistoryPaginationHost"></div>
            </div>
        </div>
    </div>

    @include('pages.orders.partials.dispatch-summary-create-modal')
    @include('pages.orders.partials.dispatch-summary-view-modal')
@endsection

@push('scripts')
    @include('pages.orders.partials.dispatch-summary-scripts')
@endpush
