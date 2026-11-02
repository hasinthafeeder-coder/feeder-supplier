<div class="modal fade" id="orderPaymentsUploadModal" tabindex="-1" aria-labelledby="orderPaymentsUploadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="orderPaymentsUploadModalTitle">Upload Courier Payment File</h5>
                    <p class="fs-13 text-muted mb-0">
                        Courier payment files are not uploaded from this screen.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="mb-3">
                    <label class="label" for="orderPaymentsUploadCourier">Courier Service</label>
                    <select class="form-select form-control" id="orderPaymentsUploadCourier">
                        <option value="">Select courier</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="label" for="orderPaymentsUploadFile">Excel File</label>
                    <input
                        type="file"
                        class="form-control"
                        id="orderPaymentsUploadFile"
                        accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                    >
                    <small class="text-muted">Excel files (.xlsx, .xls)</small>
                </div>

                <div class="alert alert-light border mb-3" role="note">
                    <div class="fw-semibold fs-13 mb-1">File Requirements</div>
                    <p class="fs-13 text-body mb-0">
                        Supplier settlement invoices already record payable and paid lines. This screen does not import a courier file.
                    </p>
                </div>

                <div id="orderPaymentsUploadPreview" class="d-none">
                    <div class="dispatch-summary-preview-strip mb-3" aria-label="Selected file preview">
                        <div>
                            <span class="preview-label">File</span>
                            <span class="preview-value" id="orderPaymentsUploadFileName">—</span>
                        </div>
                        <div>
                            <span class="preview-label">Rows detected</span>
                            <span class="preview-value" id="orderPaymentsUploadRows">—</span>
                        </div>
                        <div>
                            <span class="preview-label">Status</span>
                            <span class="preview-value">Ready</span>
                        </div>
                    </div>

                    <div class="alert alert-success mb-0" role="status">
                        File selected successfully.
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table align-middle orders-table mb-0">
                            <tbody>
                                <tr>
                                    <td>Delivery records</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsUploadDeliveryCount">—</td>
                                </tr>
                                <tr>
                                    <td>Payment records</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsUploadPaymentCount">—</td>
                                </tr>
                                <tr>
                                    <td>Unmatched waybills</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsUploadUnmatchedCount">—</td>
                                </tr>
                                <tr>
                                    <td>Duplicate rows</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsUploadDuplicateCount">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="orderPaymentsReviewFileBtn" disabled>
                    Review File
                </button>
            </div>
        </div>
    </div>
</div>
