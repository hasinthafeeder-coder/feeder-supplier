@extends('layout_main.app')

@php
    $grn = $grn ?? null;
    $existingItems = old('items', $grn?->items?->map(fn ($item) => [
        'product_id' => $item->product_id,
        'product_variant_id' => $item->product_variant_id,
        'unit_cost' => (string) $item->unit_cost,
        'received_quantity' => $item->received_quantity,
        'damaged_quantity' => $item->damaged_quantity,
        'notes' => $item->notes,
    ])->values()->all() ?? []);
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">{{ $grn ? 'Edit GRN' : 'Create GRN' }}</h3>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span>Inventory</span>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <a href="{{ route('grns.index') }}" class="text-decoration-none"><span>GRNs</span></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">{{ $grn ? 'Edit' : 'Create' }}</span>
                    </li>
                </ol>
            </nav>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <form action="{{ $grn ? route('grns.update', $grn) : route('grns.store') }}" method="POST"
                enctype="multipart/form-data" id="grnForm">
                @csrf
                @if ($grn)
                    @method('PUT')
                @endif

                <div class="row g-4">
                    @if ($grn)
                        <div class="col-md-4">
                            <label class="form-label">GRN Number</label>
                            <input type="text" class="form-control" value="{{ $grn->grn_number }}" readonly disabled>
                        </div>
                    @endif

                    <div class="col-md-4">
                        <label for="received_date" class="form-label">Received Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="received_date" name="received_date"
                            value="{{ old('received_date', $grn?->received_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                            required>
                    </div>

                    <div class="col-md-4">
                        <label for="invoice_number" class="form-label">Invoice Number</label>
                        <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                            value="{{ old('invoice_number', $grn?->invoice_number) }}" placeholder="Optional">
                    </div>

                    <div class="col-md-6">
                        <label for="invoice" class="form-label">Invoice File</label>
                        <input type="file" class="form-control" id="invoice" name="invoice"
                            accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Accepted formats: PDF, JPG, JPEG, PNG (max 10MB)</div>
                        @if ($existingInvoiceUrl)
                            <div class="mt-2 fs-14">
                                Current file:
                                <a href="{{ $existingInvoiceUrl }}" target="_blank" class="text-primary">
                                    {{ $existingInvoiceName ?? 'View invoice' }}
                                </a>
                            </div>
                        @endif
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                            placeholder="Optional notes">{{ old('notes', $grn?->notes) }}</textarea>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">GRN Items</h5>
                        <p class="mb-0 text-muted fs-14">Add product variants with received and damaged quantities.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="addGrnItemBtn">+ Add Item</button>
                </div>

                <div id="grnItemList" class="d-flex flex-column gap-3"></div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('grns.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">{{ $grn ? 'Update GRN' : 'Save GRN' }}</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .grn-item-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .grn-item-header {
            background: #f8f9fa;
            padding: 12px 16px;
        }

        .grn-item-body {
            padding: 16px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productCatalog = @json($productCatalog);
            const currencyIso = @json($currencyIso);
            const existingItems = @json($existingItems);
            const grnItemList = document.getElementById('grnItemList');
            const addGrnItemBtn = document.getElementById('addGrnItemBtn');
            let itemCount = 0;

            function getSelectedVariantIds(excludeCard = null) {
                const ids = [];

                grnItemList.querySelectorAll('.grn-item-card').forEach(function(card) {
                    if (excludeCard && card === excludeCard) {
                        return;
                    }

                    const variantSelect = card.querySelector('.grn-variant-select');
                    if (variantSelect && variantSelect.value) {
                        ids.push(variantSelect.value);
                    }
                });

                return ids;
            }

            function buildProductOptions(selectedProductId = '') {
                let options = '<option value="">Select product</option>';

                productCatalog.forEach(function(product) {
                    const selected = String(product.id) === String(selectedProductId) ? 'selected' : '';
                    options += `<option value="${product.id}" ${selected}>${product.name}</option>`;
                });

                return options;
            }

            function buildVariantOptions(productId, selectedVariantId = '', excludeIds = []) {
                const product = productCatalog.find(function(entry) {
                    return String(entry.id) === String(productId);
                });

                let options = '<option value="">Select variant</option>';

                if (!product) {
                    return options;
                }

                product.variants.forEach(function(variant) {
                    if (excludeIds.includes(String(variant.id))) {
                        return;
                    }

                    const selected = String(variant.id) === String(selectedVariantId) ? 'selected' : '';
                    options += `<option value="${variant.id}" data-cost="${variant.cost}" ${selected}>${variant.name}</option>`;
                });

                return options;
            }

            function refreshAllVariantOptions() {
                grnItemList.querySelectorAll('.grn-item-card').forEach(function(card) {
                    const productSelect = card.querySelector('.grn-product-select');
                    const variantSelect = card.querySelector('.grn-variant-select');
                    const currentProductId = productSelect.value;
                    const currentVariantId = variantSelect.value;
                    const excludeIds = getSelectedVariantIds(card);

                    variantSelect.innerHTML = buildVariantOptions(currentProductId, currentVariantId, excludeIds);

                    if (currentVariantId && !Array.from(variantSelect.options).some(function(option) {
                            return option.value === currentVariantId;
                        })) {
                        variantSelect.value = '';
                    }
                });
            }

            function createGrnItem(item = null) {
                itemCount += 1;
                const itemIndex = itemCount - 1;
                const cardId = 'grn-item-' + itemCount;
                const row = document.createElement('div');
                row.className = 'grn-item-card';
                row.id = cardId;

                const productId = item?.product_id ?? '';
                const variantId = item?.product_variant_id ?? '';
                const unitCost = item?.unit_cost ?? '';
                const receivedQty = item?.received_quantity ?? '';
                const damagedQty = item?.damaged_quantity ?? 0;
                const itemNotes = item?.notes ?? '';

                row.innerHTML = `
                    <div class="grn-item-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Item ${itemCount}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-grn-item">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                    <div class="grn-item-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select class="form-select grn-product-select" name="items[${itemIndex}][product_id]" required>
                                    ${buildProductOptions(productId)}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Variant <span class="text-danger">*</span></label>
                                <select class="form-select grn-variant-select" name="items[${itemIndex}][product_variant_id]" required>
                                    ${buildVariantOptions(productId, variantId, getSelectedVariantIds())}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unit Cost (${currencyIso}) <span class="text-danger">*</span></label>
                                <input type="number" min="0" step="0.01" class="form-control grn-unit-cost"
                                    name="items[${itemIndex}][unit_cost]" value="${unitCost}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Received Quantity <span class="text-danger">*</span></label>
                                <input type="number" min="1" step="1" class="form-control"
                                    name="items[${itemIndex}][received_quantity]" value="${receivedQty}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Damaged Quantity <span class="text-danger">*</span></label>
                                <input type="number" min="0" step="1" class="form-control"
                                    name="items[${itemIndex}][damaged_quantity]" value="${damagedQty}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Item Notes</label>
                                <input type="text" class="form-control" name="items[${itemIndex}][notes]" value="${itemNotes}">
                            </div>
                        </div>
                    </div>
                `;

                grnItemList.appendChild(row);

                const productSelect = row.querySelector('.grn-product-select');
                const variantSelect = row.querySelector('.grn-variant-select');
                const unitCostInput = row.querySelector('.grn-unit-cost');

                productSelect.addEventListener('change', function() {
                    variantSelect.innerHTML = buildVariantOptions(this.value, '', getSelectedVariantIds(row));
                    unitCostInput.value = '';
                });

                variantSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const cost = selectedOption?.dataset?.cost ?? '';

                    if (cost !== '') {
                        unitCostInput.value = cost;
                    }

                    refreshAllVariantOptions();
                });

                row.querySelector('.remove-grn-item').addEventListener('click', function() {
                    row.remove();
                    refreshAllVariantOptions();
                });
            }

            addGrnItemBtn.addEventListener('click', function() {
                createGrnItem();
            });

            document.getElementById('grnForm').addEventListener('submit', function(event) {
                if (grnItemList.querySelectorAll('.grn-item-card').length === 0) {
                    event.preventDefault();
                    alert('Please add at least one GRN item.');
                }
            });

            if (existingItems.length > 0) {
                existingItems.forEach(function(item) {
                    createGrnItem(item);
                });
            } else {
                createGrnItem();
            }
        });
    </script>
@endsection
