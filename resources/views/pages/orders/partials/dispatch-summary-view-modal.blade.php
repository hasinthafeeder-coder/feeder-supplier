<div class="modal fade" id="dispatchSummaryViewModal" tabindex="-1" aria-labelledby="dispatchSummaryViewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="dispatchSummaryViewModalTitle">View Dispatch Note</h5>
                    <p class="fs-13 text-muted mb-0" id="dispatchSummaryViewSubtitle">Dispatch Note detail</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div id="dispatchSummaryViewPrintNotice" class="alert alert-info d-none mb-3" role="status"></div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4 col-xl-2">
                        <div class="fs-13 text-muted mb-1">Dispatch Note No.</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryViewDispatchNo">—</div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="fs-13 text-muted mb-1">Supplier</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryViewSupplier">—</div>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <div class="fs-13 text-muted mb-1">Courier Service</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryViewCourier">—</div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="fs-13 text-muted mb-1">Date</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryViewDate">—</div>
                    </div>
                    <div class="col-md-4 col-xl-1">
                        <div class="fs-13 text-muted mb-1">Time</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryViewTime">—</div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="fs-13 text-muted mb-1">Operator</div>
                        <div class="fs-15 fw-semibold" id="dispatchSummaryViewOperator">—</div>
                    </div>
                </div>

                <div class="dispatch-summary-preview-strip mb-3" aria-label="Dispatch Note summary">
                    <div>
                        <span class="preview-label">Orders</span>
                        <span class="preview-value" id="dispatchSummaryViewOrdersCount">0</span>
                    </div>
                    <div>
                        <span class="preview-label">COD on Handover</span>
                        <span class="preview-value" id="dispatchSummaryViewTotalCod">—</span>
                    </div>
                    <div>
                        <span class="preview-label">Courier</span>
                        <span class="preview-value" id="dispatchSummaryViewPreviewCourier">—</span>
                    </div>
                </div>

                <div class="fs-14 fw-semibold mb-2">Orders on this Dispatch Note</div>
                <div class="table-responsive dispatch-summary-modal-table-wrap">
                    <table class="table align-middle orders-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Customer Name</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Waybill ID</th>
                                <th scope="col">Invoice Number</th>
                                <th scope="col" class="text-end">COD Amount</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchSummaryViewOrdersBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No orders on this Dispatch Note.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary text-white" id="dispatchSummaryPrintBtn">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;" aria-hidden="true">print</span>
                    Print Dispatch Note
                </button>
            </div>
        </div>
    </div>
</div>
