@php
    $courierOptions = $courierOptions ?? [];
@endphp

<div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="completedOrdersFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="completedFilterSearch" name="search"
                        placeholder="Search order, customer, phone or waybill"
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="completedOrdersClearFilters">Clear</button>
            </div>

            <div class="advanced-filters" aria-label="Completed order filters">
                <div>
                    <label class="label" for="completedFilterOutcome">Outcome</label>
                    <select class="form-select form-control" id="completedFilterOutcome" name="outcome">
                        <option value="" selected>All</option>
                        <option value="DELIVERED">Delivered</option>
                        <option value="RETURNED">Returned</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="completedFilterCourier">Courier</label>
                    <select class="form-select form-control" id="completedFilterCourier" name="courier_id">
                        <option value="">All Couriers</option>
                        @foreach ($courierOptions as $courier)
                            <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="completedFilterDateFrom">Date From</label>
                    <input type="date" class="form-control" id="completedFilterDateFrom" name="date_from">
                </div>
                <div>
                    <label class="label" for="completedFilterDateTo">Date To</label>
                    <input type="date" class="form-control" id="completedFilterDateTo" name="date_to">
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="completedOrdersResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>
