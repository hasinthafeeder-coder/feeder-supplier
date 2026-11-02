@php
    $courierOptions = $courierOptions ?? [];
@endphp

<div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="dispatchSummaryFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="dispatchSummaryFilterSearch" name="search"
                        placeholder="Search order ID, customer, waybill or invoice"
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="dispatchSummaryClearFilters">Clear</button>
            </div>

            <div class="advanced-filters dispatch-summary-history-filters" aria-label="Dispatch Note history filters">
                <div>
                    <label class="label" for="dispatchSummaryFilterCourier">Courier</label>
                    <select class="form-select form-control" id="dispatchSummaryFilterCourier" name="courier_id">
                        <option value="">All Couriers</option>
                        @foreach ($courierOptions as $courier)
                            <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="dispatchSummaryFilterDateFrom">Date From</label>
                    <input type="date" class="form-control" id="dispatchSummaryFilterDateFrom" name="date_from">
                </div>
                <div>
                    <label class="label" for="dispatchSummaryFilterDateTo">Date To</label>
                    <input type="date" class="form-control" id="dispatchSummaryFilterDateTo" name="date_to">
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="dispatchSummaryResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>
