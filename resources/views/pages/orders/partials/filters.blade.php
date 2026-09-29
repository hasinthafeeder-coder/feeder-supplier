@php
    $showBulkActions = $showBulkActions ?? false;
    $bulkActionLabel = $bulkActionLabel ?? 'Send to Print';
@endphp

<div id="ordersFiltersPanel" class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="ordersFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="filterSearch" name="search"
                        placeholder="Search orders by number, customer, phone..."
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="ordersClearFilters">Clear</button>
            </div>

            <div class="advanced-filters" aria-label="New order filters">
                <div>
                    <label class="label" for="filterCourier">Courier</label>
                    <select class="form-select form-control" id="filterCourier" name="courier_id">
                        <option value="">All couriers</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="filterProduct">Product</label>
                    <select class="form-select form-control" id="filterProduct" name="product_id">
                        <option value="">All products</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="filterDateFrom">Date from</label>
                    <input type="date" class="form-control" id="filterDateFrom" name="date_from">
                </div>
                <div>
                    <label class="label" for="filterDateTo">Date to</label>
                    <input type="date" class="form-control" id="filterDateTo" name="date_to">
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="ordersResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if ($showBulkActions)
    <div id="ordersBulkActionsBar" class="bulk-toolbar">
        <div class="bulk-count">
            <span id="ordersSelectedCount">0</span> orders selected
        </div>
        <div class="bulk-actions">
            <button type="button" class="btn btn-primary btn-sm text-white" id="ordersSendToPrintBtn" disabled>
                {{ $bulkActionLabel }}
            </button>
        </div>
    </div>
@endif
