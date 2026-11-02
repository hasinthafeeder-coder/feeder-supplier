<div class="modal fade" id="completedOrdersViewModal" tabindex="-1" aria-labelledby="completedOrdersViewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="completedOrdersViewModalTitle">Completed Order</h5>
                    <p class="fs-13 text-muted mb-0">Order details and supplier settlement status</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Order</div>
                        <div class="fs-15 fw-semibold" id="completedViewOrder">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Customer</div>
                        <div class="fs-15 fw-semibold" id="completedViewCustomer">—</div>
                        <div class="order-sub" id="completedViewPhone">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Courier</div>
                        <div class="fs-15 fw-semibold" id="completedViewCourier">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Waybill</div>
                        <div class="fs-15 fw-semibold" id="completedViewWaybill">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">COD</div>
                        <div class="fs-15 fw-semibold" id="completedViewCod">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Outcome</div>
                        <div id="completedViewOutcome">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Completed At</div>
                        <div class="fs-15 fw-semibold" id="completedViewCompletedAt">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Supplier Settlement</div>
                        <div id="completedViewSettlement">—</div>
                        <div class="order-sub" id="completedViewSettlementAmount">—</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
