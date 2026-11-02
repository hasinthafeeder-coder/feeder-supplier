<div class="modal fade" id="orderPaymentsHistoryViewModal" tabindex="-1" aria-labelledby="orderPaymentsHistoryViewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="orderPaymentsHistoryViewModalTitle">Upload History Detail</h5>
                    <p class="fs-13 text-muted mb-0">Courier payment file uploads are not recorded on this screen.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="fs-13 text-muted mb-1">File</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsHistoryViewFile">—</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fs-13 text-muted mb-1">Courier</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsHistoryViewCourier">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Uploaded At</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsHistoryViewUploadedAt">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Status</div>
                        <div id="orderPaymentsHistoryViewStatus">—</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-13 text-muted mb-1">Matched</div>
                        <div class="fs-15 fw-semibold" id="orderPaymentsHistoryViewMatched">—</div>
                    </div>
                </div>

                <div class="dispatch-summary-preview-strip mb-0" aria-label="Upload history summary">
                    <div>
                        <span class="preview-label">Records</span>
                        <span class="preview-value" id="orderPaymentsHistoryViewRecords">0</span>
                    </div>
                    <div>
                        <span class="preview-label">Unmatched</span>
                        <span class="preview-value" id="orderPaymentsHistoryViewUnmatched">0</span>
                    </div>
                    <div>
                        <span class="preview-label">Duplicates</span>
                        <span class="preview-value" id="orderPaymentsHistoryViewDuplicates">0</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
