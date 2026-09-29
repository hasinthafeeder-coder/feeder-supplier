@php
    $canPrint = $canPrint ?? false;
    $canSendToPacking = $canSendToPacking ?? false;
    $showBulkBar = $canPrint || $canSendToPacking;
    $maxBatchSize = (int) ($maxBatchSize ?? \Feeder\Core\Services\Order\SupplierFulfillmentBatchService::MAX_BATCH_SIZE);
@endphp

<div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="printOrdersFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="printFilterSearch" name="search"
                        placeholder="Search by order ID, customer, phone, invoice..."
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="printOrdersClearFilters">Clear</button>
            </div>

            <div class="advanced-filters is-compact" aria-label="Print order filters">
                <div>
                    <label class="label" for="printFilterCourier">Courier</label>
                    <select class="form-select form-control" id="printFilterCourier" name="courier_id">
                        <option value="">All couriers</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="printFilterProduct">Product</label>
                    <select class="form-select form-control" id="printFilterProduct" name="product_id">
                        <option value="">All products</option>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="printOrdersResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if ($showBulkBar)
    <div class="bulk-toolbar" id="printOrdersBulkBar">
        <div class="bulk-count">
            <span id="printOrdersSelectedCount">0</span> orders selected
            <span class="text-muted fw-normal">(max {{ $maxBatchSize }})</span>
        </div>
        <div class="bulk-actions">
            @if ($canPrint)
                <button type="button" class="btn btn-light border btn-sm" id="printItemListBtn">
                    Print Item List
                </button>
                <button type="button" class="btn btn-primary btn-sm text-white" id="printSelectedBtn" disabled>
                    Print Selected
                </button>
            @endif
            @if ($canSendToPacking)
                <button type="button" class="btn btn-primary btn-sm text-white" id="sendToPackingBtn" disabled>
                    Send to Packing
                </button>
            @endif
        </div>
    </div>
@endif
