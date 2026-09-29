<div class="modal fade" id="orderCancelModal" tabindex="-1" aria-labelledby="orderCancelModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-10">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fs-16" id="orderCancelModalTitle">Cancel order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fs-14 text-body mb-3" id="orderCancelModalCopy">
                    Cancel this order from the supplier fulfillment queue?
                </p>
                <div class="mb-0">
                    <label class="label fs-14 mb-2" for="orderCancelReason">Reason (optional)</label>
                    <textarea class="form-control" id="orderCancelReason" rows="3" maxlength="500"
                        placeholder="Add a cancellation note"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Keep order</button>
                <button type="button" class="btn btn-danger text-white" id="orderCancelConfirmBtn">Cancel order</button>
            </div>
        </div>
    </div>
</div>
