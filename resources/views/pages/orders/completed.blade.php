@extends('layout_main.app')

@php
    $summaryCounts = $summaryCounts ?? [
        'delivered' => 0,
        'returned' => 0,
        'settlement_pending' => 0,
        'settled' => 0,
    ];
    $orders = $orders ?? [];
    $courierOptions = $courierOptions ?? [];
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierCompletedOrdersPage"
        data-empty-message-all="No completed orders found."
        data-empty-message-delivered="No delivered orders found."
        data-empty-message-returned="No returned orders found."
        data-empty-message-search="No orders match your search or filters."
        data-per-page="25"
        data-orders='@json($orders)'
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Completed Orders',
            'pageCopy' => 'View recent delivered and returned orders and their supplier settlement status.',
            'crumbs' => [
                ['label' => 'Completed Orders'],
            ],
            'headerActions' => view('pages.orders.partials.completed-header-actions')->render(),
        ])

        <div id="completedOrdersAlertHost" class="alert d-none" role="status"></div>

        <div class="alert alert-light border mb-3 dispatch-note-callout" role="note">
            <div class="d-flex align-items-start gap-2">
                <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
                <div>
                    <div class="fw-semibold fs-14 mb-1">Recent completed window</div>
                    <p class="fs-13 text-body mb-0">
                        Completed Orders shows recent delivered and returned orders.
                        Older completed orders are available in Archived Orders.
                    </p>
                </div>
            </div>
        </div>

        <div class="dispatch-summary-cards completed-orders-summary-cards" role="group" aria-label="Completed orders summary">
            <button type="button" class="dispatch-summary-card" data-outcome-filter="DELIVERED" aria-pressed="false">
                <div class="summary-label">Delivered</div>
                <p class="summary-count" id="completedSummaryDelivered">{{ number_format((int) $summaryCounts['delivered']) }}</p>
            </button>
            <button type="button" class="dispatch-summary-card" data-outcome-filter="RETURNED" aria-pressed="false">
                <div class="summary-label">Returned</div>
                <p class="summary-count" id="completedSummaryReturned">{{ number_format((int) $summaryCounts['returned']) }}</p>
            </button>
            <div class="dispatch-summary-card is-static" aria-label="Supplier settlement pending">
                <div class="summary-label">Supplier Settlement Pending</div>
                <p class="summary-count" id="completedSummarySettlementPending">{{ number_format((int) $summaryCounts['settlement_pending']) }}</p>
            </div>
            <div class="dispatch-summary-card is-static" aria-label="Supplier settled">
                <div class="summary-label">Supplier Settled</div>
                <p class="summary-count" id="completedSummarySettled">{{ number_format((int) $summaryCounts['settled']) }}</p>
            </div>
        </div>

        @include('pages.orders.partials.completed-filters', [
            'courierOptions' => $courierOptions,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Completed Orders</h4>
                    <div class="results-meta mt-1" id="completedOrdersResultsMeta"></div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="completedOrdersTable">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Courier</th>
                                <th scope="col">Waybill</th>
                                <th scope="col">COD</th>
                                <th scope="col">Outcome</th>
                                <th scope="col">Completed At</th>
                                <th scope="col">Supplier Settlement</th>
                                <th scope="col" class="text-end">View</th>
                            </tr>
                        </thead>
                        <tbody id="completedOrdersTableBody">
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading completed orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="completedOrdersPaginationHost"></div>
            </div>
        </div>
    </div>

    @include('pages.orders.partials.completed-view-modal')
@endsection

@push('scripts')
    @include('pages.orders.partials.completed-scripts')
@endpush
