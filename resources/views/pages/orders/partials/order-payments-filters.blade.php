@php
    $courierOptions = $courierOptions ?? [];
@endphp

<div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="orderPaymentsFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="orderPaymentsFilterSearch" name="search"
                        placeholder="Search order, customer, waybill or invoice"
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="orderPaymentsClearFilters">Clear</button>
            </div>

            <div class="advanced-filters" aria-label="Order payment filters">
                <div>
                    <label class="label" for="orderPaymentsFilterPaymentStatus">Payment Status</label>
                    <select class="form-select form-control" id="orderPaymentsFilterPaymentStatus" name="payment_status">
                        <option value="">All</option>
                        <option value="UNPAID">Unpaid</option>
                        <option value="PAID">Paid</option>
                        <option value="NONE">No settlement record</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="orderPaymentsFilterCourier">Courier</label>
                    <select class="form-select form-control" id="orderPaymentsFilterCourier" name="courier_id">
                        <option value="">All Couriers</option>
                        @foreach ($courierOptions as $courier)
                            <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="orderPaymentsFilterDateFrom">Date From</label>
                    <input type="date" class="form-control" id="orderPaymentsFilterDateFrom" name="date_from">
                </div>
                <div>
                    <label class="label" for="orderPaymentsFilterDateTo">Date To</label>
                    <input type="date" class="form-control" id="orderPaymentsFilterDateTo" name="date_to">
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="orderPaymentsResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>
