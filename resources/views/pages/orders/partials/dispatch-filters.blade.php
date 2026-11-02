@php
    $courierOptions = $courierOptions ?? [];
@endphp

<div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="dispatchOrdersFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="dispatchFilterSearch" name="search"
                        placeholder="Search order, customer, phone or waybill"
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="dispatchOrdersClearFilters">Clear</button>
            </div>

            <div class="advanced-filters" aria-label="Dispatch order filters">
                <div>
                    <label class="label" for="dispatchFilterStatus">Status</label>
                    <select class="form-select form-control" id="dispatchFilterStatus" name="status">
                        <option value="">All</option>
                        <option value="DISPATCHED" selected>Dispatched</option>
                        <option value="RETURNED">Returned</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="dispatchFilterCourier">Courier Service</label>
                    <select class="form-select form-control" id="dispatchFilterCourier" name="courier_id">
                        <option value="">All Couriers</option>
                        @foreach ($courierOptions as $courier)
                            <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="dispatchFilterDateFrom">Date From</label>
                    <input type="date" class="form-control" id="dispatchFilterDateFrom" name="date_from">
                </div>
                <div>
                    <label class="label" for="dispatchFilterDateTo">Date To</label>
                    <input type="date" class="form-control" id="dispatchFilterDateTo" name="date_to">
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="dispatchOrdersResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>
