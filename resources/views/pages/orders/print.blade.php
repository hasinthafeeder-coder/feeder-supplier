@extends('layout_main.app')

@php
    $canPrint = $canPrint ?? false;
    $canSendToPacking = $canSendToPacking ?? false;
    $canSelect = $canPrint || $canSendToPacking;
    $maxBatchSize = (int) ($maxBatchSize ?? \Feeder\Core\Services\Order\SupplierFulfillmentBatchService::MAX_BATCH_SIZE);
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
    @include('pages.orders.partials.invoice-print-styles')
@endpush

@section('content')
    <div class="supplier-invoice-print-app">
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierPrintOrdersPage"
        data-list-url="{{ route('orders.print.index') }}"
        data-print-data-url="{{ route('orders.print-data') }}"
        data-send-to-packing-url="{{ route('orders.send-to-packing') }}"
        data-can-print="{{ $canPrint ? '1' : '0' }}"
        data-can-send-to-packing="{{ $canSendToPacking ? '1' : '0' }}"
        data-empty-message="No orders waiting for printing."
        data-max-batch="{{ $maxBatchSize }}"
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Print Orders',
            'pageCopy' => 'Print invoices and waybills, then send orders to packing.',
            'crumbs' => [
                ['label' => 'Print Orders'],
            ],
        ])

        <div id="printOrdersAlertHost" class="alert d-none" role="status"></div>

        @include('pages.orders.partials.print-filters', [
            'canPrint' => $canPrint,
            'canSendToPacking' => $canSendToPacking,
            'maxBatchSize' => $maxBatchSize,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0">Print Queue</h4>
                    <div class="results-meta mt-1" id="printOrdersResultsMeta"></div>
                </div>
            </div>

            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle orders-table" id="printOrdersTable">
                        <thead>
                            <tr>
                                @if ($canSelect)
                                    <th scope="col" class="order-ui-select-col">
                                        <input class="form-check-input" type="checkbox" id="printOrdersSelectAll"
                                            title="Select All" aria-label="Select All">
                                    </th>
                                @endif
                                <th scope="col">Feeder Invoice ID</th>
                                <th scope="col">Order Date</th>
                                <th scope="col">Reseller</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Products</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Courier</th>
                                <th scope="col">Waybill</th>
                                <th scope="col">Customer Payable</th>
                                <th scope="col">Print Status</th>
                                @if ($canPrint)
                                    <th scope="col" class="text-end">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="printOrdersTableBody">
                            <tr>
                                <td colspan="12" class="text-center text-muted py-5">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading print orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="printOrdersPaginationHost"></div>
            </div>
        </div>
    </div>

    @if ($canSendToPacking)
        @include('pages.orders.partials.send-to-packing-modal')
    @endif
    </div>

    <div id="supplierInvoicePrintSurface" aria-live="polite"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/supplier-code128.js') }}"></script>
    @include('pages.orders.partials.print-scripts')
@endpush
