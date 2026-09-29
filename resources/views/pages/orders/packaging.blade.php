@extends('layout_main.app')

@php
    $canPack = $canPack ?? false;
    $canPrint = $canPrint ?? false;
    $showActions = $canPack || $canPrint;
    $completePackageBarcode = $completePackageBarcode ?? \Feeder\Core\Support\PackagingBarcode::COMPLETE_PACKAGE;
    $completePackageBarcodeAliases = $completePackageBarcodeAliases ?? \Feeder\Core\Support\PackagingBarcode::acceptedValues();
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
    @if ($canPrint)
        @include('pages.orders.partials.invoice-print-styles')
    @endif
@endpush

@section('content')
    <div class="{{ $canPrint ? 'supplier-invoice-print-app' : '' }}">
        <div
            class="main-content-container overflow-hidden supplier-orders-ui"
            id="supplierPackagingOrdersPage"
            data-list-url="{{ route('orders.packaging.index') }}"
            data-scan-order-url="{{ route('orders.packaging.scan-order') }}"
            data-scan-product-url="{{ route('orders.packaging.scan-product') }}"
            data-complete-url="{{ route('orders.packaging.complete') }}"
            data-reprint-url="{{ route('orders.reprint-data') }}"
            data-can-pack="{{ $canPack ? '1' : '0' }}"
            data-can-print="{{ $canPrint ? '1' : '0' }}"
            data-complete-barcode="{{ $completePackageBarcode }}"
            data-complete-barcode-aliases='@json(array_values($completePackageBarcodeAliases))'
            data-empty-message="No orders waiting for packing."
        >
            @include('pages.orders.partials.ui-page-header', [
                'pageTitle' => 'Packing Orders',
                'pageCopy' => 'Scan Waybill / Order ID and product barcodes to pack and dispatch.',
                'crumbs' => [
                    ['label' => 'Packing Orders'],
                ],
            ])

            <div id="packagingOrdersAlertHost" class="alert d-none" role="status"></div>

            @if ($canPack)
                <div class="card bg-white rounded-10 border border-white mb-3">
                    <div class="p-20">
                        <label for="packagingOrderScanInput" class="label fs-15 fw-medium mb-2">Scan Waybill / Order ID</label>
                        <div class="position-relative">
                            <input
                                type="text"
                                class="form-control form-control-lg fs-18"
                                id="packagingOrderScanInput"
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                placeholder="Scan waybill or order ID and press Enter"
                                autofocus
                                style="height: 56px; padding-right: 3rem;"
                            >
                            <span class="material-symbols-outlined position-absolute top-50 end-0 translate-middle-y me-3 text-body-secondary" aria-hidden="true">barcode_scanner</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div id="packagingOrderScanBusy" class="d-none">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            </div>
                            <p class="fs-14 text-muted mb-0" id="packagingOrderScanHint">Focus stays on this input for continuous scanning.</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-warning mb-3" role="alert">
                    You can view the packing queue, but packing actions require the <strong>orders.pack</strong> permission.
                </div>
            @endif

            @include('pages.orders.partials.packaging-filters')

            <div class="card bg-white rounded-10 border border-white mb-4">
                <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="fs-18 mb-0">Packing Queue</h4>
                        <div class="results-meta mt-1" id="packagingOrdersResultsMeta"></div>
                    </div>
                </div>

                <div class="p-20">
                    <div class="table-responsive">
                        <table class="table align-middle orders-table" id="packagingOrdersTable">
                            <thead>
                                <tr>
                                    <th scope="col">Feeder Order ID</th>
                                    <th scope="col">Invoice</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Products</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col">Courier</th>
                                    <th scope="col">Waybill</th>
                                    <th scope="col">Packing Progress</th>
                                    <th scope="col">Status</th>
                                    @if ($showActions)
                                        <th scope="col" class="text-end">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="packagingOrdersTableBody">
                                <tr>
                                    <td colspan="{{ $showActions ? 10 : 9 }}" class="text-center text-muted py-5">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Loading packing orders...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="packagingOrdersPaginationHost"></div>
                </div>
            </div>
        </div>

        @if ($canPack)
            @include('pages.orders.partials.packaging-modal', [
                'completePackageBarcode' => $completePackageBarcode,
            ])
        @endif
    </div>

    @if ($canPrint)
        <div id="supplierInvoicePrintSurface" aria-live="polite"></div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/supplier-code128.js') }}"></script>
    @if ($canPrint)
        <script src="{{ asset('assets/js/supplier-invoice-print.js') }}"></script>
    @endif
    @include('pages.orders.partials.packaging-scripts')
@endpush
