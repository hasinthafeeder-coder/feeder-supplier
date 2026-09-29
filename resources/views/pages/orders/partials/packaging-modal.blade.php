@php

    $completePackageBarcode = $completePackageBarcode ?? \Feeder\Core\Support\PackagingBarcode::COMPLETE_PACKAGE;

@endphp



<div class="modal fade" id="packagingWorkspaceModal" tabindex="-1" aria-labelledby="packagingWorkspaceModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">

    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">

        <div class="modal-content border-0 rounded-10">

            <div class="modal-header border-0 pb-0">

                <div>

                    <h5 class="modal-title fs-18 mb-1" id="packagingWorkspaceModalTitle">Package Order</h5>

                    <div class="fs-14 text-muted" id="packagingWorkspaceInvoice">Invoice —</div>

                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="packagingWorkspaceCloseBtn"></button>

            </div>



            <div class="modal-body pt-3">

                <div id="packagingWorkspaceAlert" class="d-none mb-3"></div>



                <div class="row g-3 mb-3">

                    <div class="col-md-6 col-xl-3">

                        <div class="fs-13 text-muted mb-1">Customer</div>

                        <div class="fs-15 fw-medium" id="packagingWorkspaceCustomer">—</div>

                    </div>

                    <div class="col-md-6 col-xl-3">

                        <div class="fs-13 text-muted mb-1">Contact</div>

                        <div class="fs-15 fw-medium" id="packagingWorkspaceContact">—</div>

                    </div>

                    <div class="col-md-6 col-xl-3">

                        <div class="fs-13 text-muted mb-1">Courier</div>

                        <div class="fs-15 fw-medium" id="packagingWorkspaceCourier">—</div>

                    </div>

                    <div class="col-md-6 col-xl-3">

                        <div class="fs-13 text-muted mb-1">Waybill</div>

                        <div class="fs-15 fw-medium" id="packagingWorkspaceWaybill">—</div>

                    </div>

                    <div class="col-12">

                        <div class="fs-13 text-muted mb-1">Address</div>

                        <div class="fs-15" id="packagingWorkspaceAddress">—</div>

                    </div>

                </div>



                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 p-3 bg-light rounded-10">

                    <div>

                        <div class="fs-13 text-muted mb-1">Packing progress</div>

                        <div class="fs-20 fw-semibold" id="packagingWorkspaceProgressText">0 / 0 packed</div>

                    </div>

                    <div class="text-end">

                        <div class="fs-13 text-muted mb-1">Progress</div>

                        <div class="fs-20 fw-semibold" id="packagingWorkspaceProgressPct">0%</div>

                    </div>

                    <div class="w-100">

                        <div class="progress" style="height: 10px;">

                            <div class="progress-bar bg-success" id="packagingWorkspaceProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>

                        </div>

                    </div>

                </div>



                <div class="mb-4">

                    <label for="packagingProductScanInput" class="label fs-15 fw-medium mb-2">Scan Product Barcode</label>

                    <div class="position-relative">

                        <input

                            type="text"

                            class="form-control form-control-lg fs-18"

                            id="packagingProductScanInput"

                            autocomplete="off"

                            autocorrect="off"

                            autocapitalize="off"

                            spellcheck="false"

                            placeholder="Scan product barcode (or Complete Package barcode)"

                            style="height: 56px; padding-right: 3rem;"

                        >

                        <span class="material-symbols-outlined position-absolute top-50 end-0 translate-middle-y me-3 text-body-secondary" aria-hidden="true">barcode_scanner</span>

                    </div>

                    <div class="d-flex align-items-center gap-2 mt-2">

                        <div id="packagingProductScanBusy" class="d-none">

                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>

                        </div>

                        <p class="fs-14 text-muted mb-0">One scan = one physical item. Keep scanning until progress is complete.</p>

                    </div>

                </div>



                <div class="table-responsive mb-4">

                    <table class="table align-middle mb-0" id="packagingWorkspaceItemsTable">

                        <thead>

                            <tr>

                                <th class="fw-medium">Product</th>

                                <th class="fw-medium">Variant</th>

                                <th class="fw-medium">Barcode</th>

                                <th class="fw-medium text-center">Ordered</th>

                                <th class="fw-medium text-center">Packed</th>

                                <th class="fw-medium text-center">Remaining</th>

                                <th class="fw-medium">Status</th>

                            </tr>

                        </thead>

                        <tbody id="packagingWorkspaceItemsBody">

                            <tr>

                                <td colspan="7" class="text-muted text-center py-4">No items</td>

                            </tr>

                        </tbody>

                    </table>

                </div>



                <div class="border rounded-10 p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="fs-15 fw-medium mb-1">Complete Package barcode</div>
                            <div class="fs-13 text-muted mb-2">
                                Scan keyword <strong>{{ $completePackageBarcode }}</strong> when every item is packed.
                            </div>
                            <code class="fs-18 fw-semibold" id="packagingCompleteBarcodeValue">{{ $completePackageBarcode }}</code>
                        </div>
                        <a
                            href="#"
                            class="btn btn-sm btn-outline-primary align-self-start"
                            id="packagingCompleteBarcodeDownload"
                            download="complete-package-barcode-{{ $completePackageBarcode }}.svg"
                        >
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;" aria-hidden="true">download</span>
                            Download barcode
                        </a>
                    </div>

                    <div class="text-center bg-white border rounded-10 p-3 overflow-auto" id="packagingCompleteBarcodeSvgHost"></div>

                    <input
                        type="text"
                        class="visually-hidden"
                        id="packagingCompleteScanInput"
                        tabindex="-1"
                        autocomplete="off"
                        aria-label="Complete Package barcode scanner"
                    >
                </div>

            </div>



            <div class="modal-footer border-0 pt-0">

                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>

                <button type="button" class="btn btn-success text-white" id="packagingCompleteBtn" disabled>

                    Complete Package

                </button>

            </div>

        </div>

    </div>

</div>

