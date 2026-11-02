@php
    $canReturn = $canReturn ?? false;
@endphp

<div class="card bg-white rounded-10 border border-white mb-3" id="dispatchReturnScanCard">
    <div class="p-20 border-bottom">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="fs-18 mb-0">Scan Returned Order</h4>
            @if ($canReturn)
                <label
                    class="dispatch-return-damaged-check mb-0"
                    id="dispatchReturnDamagedCheckWrap"
                    for="dispatchReturnDamagedCheckbox"
                >
                    <input
                        class="form-check-input"
                        type="checkbox"
                        value="1"
                        id="dispatchReturnDamagedCheckbox"
                    >
                    <span class="fw-semibold fs-13">Damaged Order — Do Not Restock</span>
                </label>
            @endif
        </div>
        <p class="fs-13 text-body mb-0 mt-2">
            Scanning a dispatched order's waybill marks it as
            <span class="badge-status is-returned">RETURNED</span>
            and restores its consumed stock unless
            <strong>Damaged Order — Do Not Restock</strong> is checked.
            Return classification and reseller fines are determined automatically from the order's confirmation flags.
        </p>
    </div>
    <div class="p-20">
        @if ($canReturn)
            <div class="order-ui-scan-zone" id="dispatchReturnScanZone">
                <span class="material-symbols-outlined d-block" aria-hidden="true">barcode_scanner</span>
                <h4 class="fs-18 mb-2">Ready for barcode scan</h4>
                <p class="fs-14 text-body mb-3">
                    Focus stays on this field for continuous scanning. Scanning submits automatically.
                </p>
                <div class="order-ui-scan-input-wrap">
                    <label for="dispatchReturnScanInput" class="visually-hidden">Scan waybill</label>
                    <input
                        type="text"
                        class="form-control"
                        id="dispatchReturnScanInput"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        placeholder="Scan waybill..."
                    >
                </div>
                <div class="order-ui-scan-status mt-3">
                    <div id="dispatchReturnScanBusy" class="d-none mb-2">
                        <div class="spinner-border spinner-border-sm text-primary" role="status" aria-label="Processing"></div>
                        <span class="fs-13 text-body ms-2">Processing return…</span>
                    </div>
                    <p class="fs-13 text-body mb-0" id="dispatchReturnScanHint">
                        Scan a courier waybill to mark the order as returned.
                    </p>
                </div>
            </div>
            <div class="mt-3">
                <div class="alert alert-light border mb-0 d-none" id="dispatchReturnScanResult" role="status"></div>
                <p class="fs-13 text-muted mb-0" id="dispatchReturnScanResultPlaceholder">
                    Success or error feedback appears here after each scan.
                </p>
            </div>
        @else
            <p class="fs-13 text-muted mb-0">
                You do not have permission to scan returned orders.
            </p>
        @endif
    </div>
</div>
