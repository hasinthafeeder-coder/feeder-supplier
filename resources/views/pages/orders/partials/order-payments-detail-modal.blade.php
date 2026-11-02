<div class="modal fade" id="orderPaymentsDetailModal" tabindex="-1" aria-labelledby="orderPaymentsDetailModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="orderPaymentsDetailModalTitle">Payment Detail</h5>
                    <p class="fs-13 text-muted mb-0">Delivered order and its supplier settlement payment.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Order</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailOrder">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Customer</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailCustomer">—</div>
                        <div class="order-sub" id="orderPaymentsDetailPhone">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Courier</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailCourier">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Waybill</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailWaybill">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Delivery</div>
                        <div id="orderPaymentsDetailDelivery">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">COD Amount</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailCod">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Payment</div>
                        <div id="orderPaymentsDetailPayment">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Payment Received</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailReceivedAt">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Source</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsDetailSource">—</div>
                    </div>
                </div>

                <div class="alert alert-light border mb-0" role="note">
                    <p class="fs-13 text-body mb-0">
                        Payment settlement to the supplier/reseller is handled separately by Admin.
                    </p>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
