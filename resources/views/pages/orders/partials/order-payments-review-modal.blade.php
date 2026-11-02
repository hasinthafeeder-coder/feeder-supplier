<div class="modal fade" id="orderPaymentsReviewModal" tabindex="-1" aria-labelledby="orderPaymentsReviewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="orderPaymentsReviewModalTitle">Review Courier File</h5>
                    <p class="fs-13 text-muted mb-0">
                        Review matched and exception records before confirming the upload.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div id="orderPaymentsReviewSuccess" class="d-none">
                    <div class="alert alert-success mb-0" role="status">
                        <div class="fw-semibold mb-1">File uploaded successfully.</div>
                        <div class="fs-13" id="orderPaymentsReviewSuccessCopy">Courier payment file upload is not available.</div>
                    </div>
                </div>

                <div id="orderPaymentsReviewContent">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="fs-13 text-muted mb-1">File</div>
                            <div class="fs-15 fw-semibold" id="orderPaymentsReviewFileName">—</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs-13 text-muted mb-1">Courier</div>
                            <div class="fs-15 fw-semibold" id="orderPaymentsReviewCourier">—</div>
                        </div>
                    </div>

                    <div class="fs-14 fw-semibold mb-2">Summary</div>
                    <div class="table-responsive mb-3">
                        <table class="table align-middle orders-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Metric</th>
                                    <th scope="col" class="text-end">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total rows</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsReviewTotalRows">—</td>
                                </tr>
                                <tr>
                                    <td>Matched orders</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsReviewMatched">—</td>
                                </tr>
                                <tr>
                                    <td>Delivered</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsReviewDelivered">—</td>
                                </tr>
                                <tr>
                                    <td>Payment received</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsReviewPaymentReceived">—</td>
                                </tr>
                                <tr>
                                    <td>Unmatched waybills</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsReviewUnmatched">—</td>
                                </tr>
                                <tr>
                                    <td>Duplicate rows</td>
                                    <td class="text-end fw-semibold" id="orderPaymentsReviewDuplicates">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-warning mb-3" role="status" id="orderPaymentsReviewUnmatchedWarning">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold fs-13 mb-1">Unmatched Waybills</div>
                                <p class="fs-13 mb-0" id="orderPaymentsReviewUnmatchedCopy">
                                    Unmatched waybills are not stored for this screen.
                                </p>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border" id="orderPaymentsViewUnmatchedBtn">
                                View Unmatched
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-warning mb-0" role="status" id="orderPaymentsReviewDuplicateWarning">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold fs-13 mb-1">Duplicate Rows</div>
                                <p class="fs-13 mb-0" id="orderPaymentsReviewDuplicateCopy">
                                    Duplicate courier rows are not stored for this screen.
                                </p>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border" id="orderPaymentsViewDuplicatesBtn">
                                View Duplicates
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0" id="orderPaymentsReviewFooter">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="orderPaymentsConfirmUploadBtn">
                    Confirm Upload
                </button>
            </div>

            <div class="modal-footer border-0 pt-0 d-none" id="orderPaymentsReviewSuccessFooter">
                <button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>
