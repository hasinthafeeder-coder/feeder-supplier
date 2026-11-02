<div class="modal fade" id="orderPaymentsUnmatchedModal" tabindex="-1" aria-labelledby="orderPaymentsUnmatchedModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="orderPaymentsUnmatchedModalTitle">Unmatched Waybills</h5>
                    <p class="fs-13 text-muted mb-0">
                        No unmatched waybills are stored for supplier payment uploads.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="table-responsive dispatch-summary-modal-table-wrap">
                    <table class="table align-middle orders-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Row</th>
                                <th scope="col">Waybill</th>
                                <th scope="col">Reason</th>
                            </tr>
                        </thead>
                        <tbody id="orderPaymentsUnmatchedBody">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No unmatched waybills.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
