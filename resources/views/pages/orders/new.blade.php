@extends('layout_main.app')

@php
    $canSendToPrint = $canSendToPrint ?? false;
    $canCancel = $canCancel ?? false;
    $initialMode = $initialMode ?? 'fresh';
    $modeCounts = $modeCounts ?? ['fresh' => 0, 'exchange' => 0, 'out_of_stock' => 0];
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierOrdersPage"
        data-mode="{{ $initialMode }}"
        data-list-url-fresh="{{ route('orders.fresh') }}"
        data-list-url-out-of-stock="{{ route('orders.out-of-stock') }}"
        data-send-to-print-url="{{ route('orders.send-to-print') }}"
        data-cancel-url-template="{{ url('/orders') }}/__ORDER_UUID__/cancel"
        data-show-url-template="{{ url('/orders') }}/__ORDER_UUID__"
        data-can-send-to-print="{{ $canSendToPrint ? '1' : '0' }}"
        data-can-cancel="{{ $canCancel ? '1' : '0' }}"
        data-empty-message-fresh="No fresh orders found."
        data-empty-message-out-of-stock="No out-of-stock orders found."
        data-empty-message-exchange="Exchange fulfillment is not available yet."
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'New Orders',
            'pageCopy' => 'Confirmed new orders ready for fulfillment. Switch between Fresh, Exchange, and Out of Stock.',
            'crumbs' => [
                ['label' => 'New Orders'],
            ],
        ])

        <div id="ordersAlertHost" class="alert alert-success prototype-toast d-none" role="status"></div>

        <div class="workspace-tabs" id="ordersModeTabs" role="tablist" aria-label="New order type filters">
            <button type="button" class="workspace-tab orders-mode-btn {{ $initialMode === 'fresh' ? 'is-active' : '' }}"
                data-mode="fresh" role="tab" aria-selected="{{ $initialMode === 'fresh' ? 'true' : 'false' }}">
                Fresh<span class="tab-count" data-mode-count="fresh">{{ number_format((int) $modeCounts['fresh']) }}</span>
            </button>
            <button type="button" class="workspace-tab orders-mode-btn {{ $initialMode === 'exchange' ? 'is-active' : '' }}"
                data-mode="exchange" role="tab" aria-selected="{{ $initialMode === 'exchange' ? 'true' : 'false' }}">
                Exchange<span class="tab-count" data-mode-count="exchange">{{ number_format((int) $modeCounts['exchange']) }}</span>
            </button>
            <button type="button" class="workspace-tab orders-mode-btn {{ $initialMode === 'out-of-stock' ? 'is-active' : '' }}"
                data-mode="out-of-stock" role="tab" aria-selected="{{ $initialMode === 'out-of-stock' ? 'true' : 'false' }}">
                Out of Stock<span class="tab-count" data-mode-count="out-of-stock">{{ number_format((int) $modeCounts['out_of_stock']) }}</span>
            </button>
        </div>

        @include('pages.orders.partials.filters', [
            'showBulkActions' => $canSendToPrint,
            'bulkActionLabel' => 'Send to Print',
        ])

        <div id="ordersSearchLocationHost" class="alert alert-info d-none mb-3" role="status"></div>

        <div class="card bg-white rounded-10 border border-white mb-4" id="ordersListPanel">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0" id="ordersWorkspaceTitle">Fresh Orders</h4>
                    <div class="results-meta mt-1" id="ordersResultsMeta"></div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="ordersTable">
                        <thead>
                            <tr>
                                @if ($canSendToPrint)
                                    <th scope="col" class="orders-select-col order-ui-select-col">
                                        <input class="form-check-input" type="checkbox" id="ordersSelectAll"
                                            title="Select All" aria-label="Select All">
                                    </th>
                                @endif
                                <th scope="col">Feeder Order ID</th>
                                <th scope="col">Order Date</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Products</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Courier</th>
                                <th scope="col">Customer Payable</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <tr id="ordersLoadingRow">
                                <td colspan="11" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="ordersPaginationHost"></div>

                @if ($canSendToPrint)
                    <div id="ordersCountSendBar" class="pt-15 border-top d-flex flex-wrap align-items-end gap-2">
                        <div style="min-width: 140px;">
                            <label class="label" for="ordersSendCountInput">Send count</label>
                            <input
                                type="number"
                                class="form-control"
                                id="ordersSendCountInput"
                                min="1"
                                step="1"
                                inputmode="numeric"
                                placeholder="e.g. 50"
                            >
                        </div>
                        <button type="button" class="btn btn-primary text-white" id="ordersSendCountBtn">
                            Send to Print
                        </button>
                        <span class="fs-13 text-muted mb-2" id="ordersSendEligibleCount">0 orders</span>
                    </div>
                @endif
            </div>
        </div>

        <div id="ordersExchangePlaceholder" class="card bg-white rounded-10 border border-white mb-4 text-center py-5 px-3 d-none">
            <span class="material-symbols-outlined text-primary mb-3" style="font-size: 42px;">schedule</span>
            <h4 class="fs-18 fw-medium mb-2">Exchange orders coming soon</h4>
            <p class="fs-15 text-body mb-0 mx-auto" style="max-width: 480px;">
                Exchange fulfillment is not available yet. This filter is reserved for a later phase.
            </p>
        </div>
    </div>

    @if ($canCancel)
        @include('pages.orders.partials.cancel-modal')
    @endif
@endsection

@push('scripts')
    @include('pages.orders.partials.list-scripts')
@endpush
