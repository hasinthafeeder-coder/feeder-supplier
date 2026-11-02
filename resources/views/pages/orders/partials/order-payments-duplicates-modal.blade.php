<div class="modal fade" id="orderPaymentsDuplicatesModal" tabindex="-1" aria-labelledby="orderPaymentsDuplicatesModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fs-18 mb-1" id="orderPaymentsDuplicatesModalTitle">Duplicate Rows</h5>
                    <p class="fs-13 text-muted mb-0">
                        No duplicate courier rows are stored for this screen.
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
                                <th scope="col">Duplicate Of Row</th>
                            </tr>
                        </thead>
                        <tbody id="orderPaymentsDuplicatesBody">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No duplicate rows.</td>
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
