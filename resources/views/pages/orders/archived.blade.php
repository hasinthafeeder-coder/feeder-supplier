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
        id="supplierArchivedOrdersPage"
        data-empty-message-all="No archived orders found."
        data-empty-message-delivered="No archived delivered orders found."
        data-empty-message-returned="No archived returned orders found."
        data-empty-message-search="No orders match your search or filters."
        data-per-page="25"
        data-orders='@json($orders)'
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Archived Orders',
            'pageCopy' => 'Historical delivered and returned orders from the completed-order archive.',
            'crumbs' => [
                ['label' => 'Archived Orders'],
            ],
            'headerActions' => view('pages.orders.partials.archived-header-actions')->render(),
        ])

        <div id="archivedOrdersAlertHost" class="alert d-none" role="status"></div>

        <div class="alert alert-light border mb-3 dispatch-note-callout" role="note">
            <div class="d-flex align-items-start gap-2">
                <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
                <div>
                    <div class="fw-semibold fs-14 mb-1">Completed-order archive</div>
                    <p class="fs-13 text-body mb-2">
                        Archived Orders contains older completed orders. Recent delivered and returned orders remain in Completed Orders.
                    </p>
                    <p class="fs-13 text-body mb-0">
                        Archived Orders keeps older completed records separate from the active completed-order workspace.
                    </p>
                </div>
            </div>
        </div>

        <div class="dispatch-summary-cards completed-orders-summary-cards" role="group" aria-label="Archived orders summary">
            <button type="button" class="dispatch-summary-card" data-outcome-filter="DELIVERED" aria-pressed="false">
                <div class="summary-label">Delivered</div>
                <p class="summary-count" id="archivedSummaryDelivered">{{ number_format((int) $summaryCounts['delivered']) }}</p>
            </button>
            <button type="button" class="dispatch-summary-card" data-outcome-filter="RETURNED" aria-pressed="false">
                <div class="summary-label">Returned</div>
                <p class="summary-count" id="archivedSummaryReturned">{{ number_format((int) $summaryCounts['returned']) }}</p>
            </button>
            <div class="dispatch-summary-card is-static" aria-label="Supplier settlement pending">
                <div class="summary-label">Supplier Settlement Pending</div>
                <p class="summary-count" id="archivedSummarySettlementPending">{{ number_format((int) $summaryCounts['settlement_pending']) }}</p>
            </div>
            <div class="dispatch-summary-card is-static" aria-label="Supplier settled">
                <div class="summary-label">Supplier Settled</div>
                <p class="summary-count" id="archivedSummarySettled">{{ number_format((int) $summaryCounts['settled']) }}</p>
            </div>
        </div>

        @include('pages.orders.partials.archived-filters', [
            'courierOptions' => $courierOptions,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Archived Orders</h4>
                    <div class="results-meta mt-1" id="archivedOrdersResultsMeta"></div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="archivedOrdersTable">
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
                        <tbody id="archivedOrdersTableBody">
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading archived orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="archivedOrdersPaginationHost"></div>
            </div>
        </div>
    </div>

    @include('pages.orders.partials.archived-view-modal')
@endsection

@push('scripts')
    @include('pages.orders.partials.archived-scripts')
@endpush
