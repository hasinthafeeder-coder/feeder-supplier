<div class="modal fade" id="dispatchSummaryCreateModal" tabindex="-1" aria-labelledby="dispatchSummaryCreateModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="dispatchSummaryCreateModalTitle">Create Dispatch Note</h5>
                    <p class="fs-13 text-muted mb-0">
                        Preview the orders included in this courier handover.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="alert alert-light border mb-3 dispatch-note-callout" role="note">
                    <div class="d-flex align-items-start gap-2">
                        <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
                        <div>
                            <div class="fw-semibold fs-14 mb-1">Order status is unchanged</div>
                            <p class="fs-13 text-body mb-0">
                                These orders remain
                                <span class="badge-status is-dispatched">DISPATCHED</span>.
                                Confirming records a Dispatch Note for this courier handover only.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Courier Service</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryCreateCourier">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Orders included</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryCreatePendingLabel">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">COD on this handover</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryCreateTotalCod">—</div>
                    </div>
                </div>

                <div class="dispatch-summary-preview-strip mb-3" aria-label="Dispatch Note preview">
                    <div>
                        <span class="preview-label">Orders</span>
                        <span class="preview-value" id="dispatchSummaryCreateOrdersCount">0</span>
                    </div>
                    <div>
                        <span class="preview-label">COD on this handover</span>
                        <span class="preview-value" id="dispatchSummaryCreatePreviewCod">—</span>
                    </div>
                    <div>
                        <span class="preview-label">Courier</span>
                        <span class="preview-value" id="dispatchSummaryCreatePreviewCourier">—</span>
                    </div>
                </div>

                <div class="fs-14 fw-semibold mb-2">Orders included in this handover</div>
                <div class="table-responsive dispatch-summary-modal-table-wrap">
                    <table class="table align-middle orders-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Waybill</th>
                                <th scope="col">Invoice</th>
                                <th scope="col" class="text-end">COD Amount</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchSummaryCreateOrdersBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No pending orders.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="dispatchSummaryCreateConfirmBtn">
                    Create Dispatch Note
                </button>
            </div>
        </div>
    </div>
</div>
