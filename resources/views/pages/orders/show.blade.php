@extends('layout_main.app')

@php
    $canCancel = $canCancel ?? false;
    $orderData = $orderData ?? [];
    $order = $order ?? null;
    $items = is_array($orderData['items'] ?? null) ? $orderData['items'] : [];
    $canCancelOrder = $canCancel && ! empty($orderData['can_cancel']);
    $fulfillmentStatus = $orderData['fulfillment_status'] ?? null;
    $fulfillmentLabel = match ($fulfillmentStatus) {
        'FRESH' => 'Fresh',
        'PRINT' => 'Print',
        'PACKAGING' => 'Packaging',
        'OUT_OF_STOCK' => 'Out of Stock',
        'DISPATCHED' => 'Dispatched',
        default => $orderData['order_status_label'] ?? '—',
    };
    $payable = $orderData['customer_payable_amount'] ?? null;
    $currency = $orderData['currency_code'] ?? '';
    $payableDisplay = $payable !== null && $payable !== ''
        ? trim($currency.' '.$payable)
        : '—';
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div
        class="main-content-container overflow-hidden supplier-orders-ui"
        id="supplierOrderShowPage"
        data-order-id="{{ $orderData['id'] ?? '' }}"
        data-order-uuid="{{ $orderData['uuid'] ?? ($order?->uuid ?? '') }}"
        data-order-number="{{ $orderData['order_number'] ?? '' }}"
        data-cancel-url="{{ isset($order) ? route('orders.cancel', $order) : '' }}"
        data-can-cancel="{{ $canCancelOrder ? '1' : '0' }}"
        data-back-url="{{ route('orders.ui.new') }}"
    >
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => 'Order '.$orderData['order_number'],
            'pageCopy' => 'Supplier order details for fulfillment.',
            'crumbs' => [
                ['label' => 'New Orders', 'href' => route('orders.ui.new')],
                ['label' => $orderData['order_number'] ?? 'Order'],
            ],
        ])

        <div id="orderShowAlertHost" class="alert alert-success prototype-toast d-none" role="status"></div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="{{ route('orders.ui.new') }}" class="btn btn-light border">Back to New Orders</a>
            @if ($canCancelOrder)
                <button type="button" class="btn btn-danger text-white" id="orderShowCancelBtn">
                    Cancel Order
                </button>
            @endif
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <h5 class="mb-20">Order Information</h5>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-muted fs-14">Feeder Order ID</div>
                    <div class="fw-medium fs-16 order-number">{{ $orderData['order_number'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Order Date</div>
                    <div class="fw-medium fs-16">{{ $orderData['order_date'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Fulfillment Status</div>
                    <div class="fw-medium fs-16">{{ $fulfillmentLabel }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Customer</div>
                    <div class="fw-medium fs-16">{{ $orderData['customer_name'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Primary Phone</div>
                    <div class="fw-medium fs-16">{{ $orderData['primary_phone'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Secondary Phone</div>
                    <div class="fw-medium fs-16">{{ $orderData['secondary_phone'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Courier</div>
                    <div class="fw-medium fs-16">{{ $orderData['courier'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Customer Payable</div>
                    <div class="fw-medium fs-16">{{ $payableDisplay }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-14">Order Status</div>
                    <div class="fw-medium fs-16">{{ $orderData['order_status_label'] ?? '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted fs-14">Address</div>
                    <div class="fw-medium fs-16">{{ $orderData['address'] ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="default-table-area mx-minus-1">
                <div class="table-responsive">
                    <table class="table align-middle orders-table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Variant</th>
                                <th scope="col">Barcode</th>
                                <th scope="col">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>{{ $item['product_name'] ?? '—' }}</td>
                                    <td>{{ $item['variant'] ?? '—' }}</td>
                                    <td>{{ $item['barcode'] ?? '—' }}</td>
                                    <td>{{ $item['quantity'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No items on this order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($canCancelOrder)
        @include('pages.orders.partials.cancel-modal')
    @endif
@endsection

@push('scripts')
    @include('pages.orders.partials.show-scripts')
@endpush
