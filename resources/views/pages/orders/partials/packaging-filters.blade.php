<div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
    <div class="p-20">
        <form id="packagingOrdersFilterForm">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" class="form-control" id="packagingFilterSearch" name="search"
                        placeholder="Search by order ID, customer, phone..."
                        autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary text-white">Search</button>
                <button type="button" class="btn btn-light border" id="packagingOrdersClearFilters">Clear</button>
            </div>

            <div class="advanced-filters is-compact" aria-label="Packing order filters">
                <div>
                    <label class="label" for="packagingFilterCourier">Courier</label>
                    <select class="form-select form-control" id="packagingFilterCourier" name="courier_id">
                        <option value="">All couriers</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="packagingFilterProduct">Product</label>
                    <select class="form-select form-control" id="packagingFilterProduct" name="product_id">
                        <option value="">All products</option>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-light border w-100" id="packagingOrdersResetFilters">Reset filters</button>
                </div>
            </div>
        </form>
    </div>
</div>
