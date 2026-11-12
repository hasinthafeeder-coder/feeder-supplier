@extends('layout_main.app')

@php
    $summaryCounts = $summaryCounts ?? ['dispatched' => 0, 'returned_today' => 0];
    $courierOptions = $courierOptions ?? [];
    $canReturn = $canReturn ?? false;
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierDispatchOrdersPage"
        data-list-url="{{ route('orders.dispatch.index') }}"
        data-scan-return-url="{{ route('orders.dispatch.scan-return') }}"
        data-order-show-base="{{ url('/orders') }}"
        data-can-return="{{ $canReturn ? '1' : '0' }}"
        data-empty-message-all="No dispatched orders found."
        data-empty-message-dispatched="No dispatched orders found."
        data-empty-message-returned="No returned orders found."
        data-empty-message-search="No orders match your search or filters."
        data-per-page="25"
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Dispatch Orders',
            'pageCopy' => 'Manage dispatched orders and receive returned packages.',
            'crumbs' => [
                ['label' => 'Dispatch Orders'],
            ],
        ])

        <div id="dispatchOrdersAlertHost" class="alert d-none" role="status"></div>

        <div class="alert alert-light border mb-3 dispatch-note-callout" role="note">
            <div class="d-flex align-items-start gap-2">
                <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
                <div>
                    <div class="fw-semibold fs-14 mb-1">Dispatch operations vs courier handover</div>
                    <p class="fs-13 text-body mb-0">
                        This page covers dispatch and return intake. Courier handover is recorded in Dispatch Summary.
                        A Dispatch Note groups orders for handover and does not change the order status.
                    </p>
                </div>
            </div>
        </div>

        <div class="dispatch-summary-cards dispatch-orders-summary-cards" role="group" aria-label="Dispatch status summary">
            <button type="button" class="dispatch-summary-card" data-status-filter="DISPATCHED" aria-pressed="false">
                <div class="summary-label">Dispatched</div>
                <p class="summary-count" id="dispatchSummaryDispatched">{{ number_format((int) $summaryCounts['dispatched']) }}</p>
            </button>
            <div class="dispatch-summary-card is-static" aria-label="Returned today">
                <div class="summary-label">Returned Today</div>
                <p class="summary-count" id="dispatchSummaryReturnedToday">{{ number_format((int) $summaryCounts['returned_today']) }}</p>
            </div>
        </div>

        @include('pages.orders.partials.dispatch-return-scan-card', [
            'canReturn' => $canReturn,
        ])

        @include('pages.orders.partials.dispatch-filters', [
            'courierOptions' => $courierOptions,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Dispatch Orders</h4>
                    <div class="results-meta mt-1" id="dispatchOrdersResultsMeta"></div>
                </div>
            </div>

            <div>
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="dispatchOrdersTable">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Courier</th>
                                <th scope="col">Waybill</th>
                                <th scope="col">Invoice</th>
                                <th scope="col">COD Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col">Dispatched At</th>
                                <th scope="col">Updated</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchOrdersTableBody">
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading dispatch orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="dispatchOrdersPaginationHost"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('pages.orders.partials.dispatch-scripts')
@endpush
