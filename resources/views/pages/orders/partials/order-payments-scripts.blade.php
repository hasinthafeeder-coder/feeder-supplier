<script>
(function () {
    const page = document.getElementById('supplierOrderPaymentsPage');
    if (!page) {
        return;
    }

    const courierTableBody = document.getElementById('orderPaymentsCourierTableBody');
    const tableBody = document.getElementById('orderPaymentsTableBody');
    const historyBody = document.getElementById('orderPaymentsUploadHistoryBody');
    const resultsMeta = document.getElementById('orderPaymentsResultsMeta');
    const paginationHost = document.getElementById('orderPaymentsPaginationHost');
    const filterForm = document.getElementById('orderPaymentsFilterForm');
    const searchInput = document.getElementById('orderPaymentsFilterSearch');
    const paymentStatusSelect = document.getElementById('orderPaymentsFilterPaymentStatus');
    const courierSelect = document.getElementById('orderPaymentsFilterCourier');
    const dateFromInput = document.getElementById('orderPaymentsFilterDateFrom');
    const dateToInput = document.getElementById('orderPaymentsFilterDateTo');
    const clearBtn = document.getElementById('orderPaymentsClearFilters');
    const resetBtn = document.getElementById('orderPaymentsResetFilters');
    const summaryCards = Array.from(page.querySelectorAll('.dispatch-summary-card[data-payment-filter]'));
    const alertHost = document.getElementById('orderPaymentsAlertHost');

    const uploadModalEl = document.getElementById('orderPaymentsUploadModal');
    const uploadCourierSelect = document.getElementById('orderPaymentsUploadCourier');
    const uploadFileInput = document.getElementById('orderPaymentsUploadFile');
    const uploadPreview = document.getElementById('orderPaymentsUploadPreview');
    const uploadFileNameEl = document.getElementById('orderPaymentsUploadFileName');
    const reviewFileBtn = document.getElementById('orderPaymentsReviewFileBtn');

    const reviewModalEl = document.getElementById('orderPaymentsReviewModal');
    const reviewContent = document.getElementById('orderPaymentsReviewContent');
    const reviewSuccess = document.getElementById('orderPaymentsReviewSuccess');
    const reviewSuccessCopy = document.getElementById('orderPaymentsReviewSuccessCopy');
    const reviewFooter = document.getElementById('orderPaymentsReviewFooter');
    const reviewSuccessFooter = document.getElementById('orderPaymentsReviewSuccessFooter');
    const reviewFileNameEl = document.getElementById('orderPaymentsReviewFileName');
    const reviewCourierEl = document.getElementById('orderPaymentsReviewCourier');
    const confirmUploadBtn = document.getElementById('orderPaymentsConfirmUploadBtn');
    const viewUnmatchedBtn = document.getElementById('orderPaymentsViewUnmatchedBtn');
    const viewDuplicatesBtn = document.getElementById('orderPaymentsViewDuplicatesBtn');

    const detailModalEl = document.getElementById('orderPaymentsDetailModal');
    const unmatchedModalEl = document.getElementById('orderPaymentsUnmatchedModal');
    const duplicatesModalEl = document.getElementById('orderPaymentsDuplicatesModal');
    const historyViewModalEl = document.getElementById('orderPaymentsHistoryViewModal');
    const unmatchedBody = document.getElementById('orderPaymentsUnmatchedBody');
    const duplicatesBody = document.getElementById('orderPaymentsDuplicatesBody');

    let courierUploads = [];
    let workspaceOrders = [];
    let uploadHistory = [];
    let unmatchedWaybills = [];
    let duplicateRows = [];
    let courierOptions = [];

    try { courierUploads = JSON.parse(page.dataset.courierUploads || '[]'); } catch (e) { courierUploads = []; }
    try { workspaceOrders = JSON.parse(page.dataset.orders || '[]'); } catch (e) { workspaceOrders = []; }
    try { uploadHistory = JSON.parse(page.dataset.uploadHistory || '[]'); } catch (e) { uploadHistory = []; }
    try { unmatchedWaybills = JSON.parse(page.dataset.unmatchedWaybills || '[]'); } catch (e) { unmatchedWaybills = []; }
    try { duplicateRows = JSON.parse(page.dataset.duplicateRows || '[]'); } catch (e) { duplicateRows = []; }
    try { courierOptions = JSON.parse(page.dataset.courierOptions || '[]'); } catch (e) { courierOptions = []; }

    let uploadModalInstance = null;
    let reviewModalInstance = null;
    let detailModalInstance = null;
    let unmatchedModalInstance = null;
    let duplicatesModalInstance = null;
    let historyViewModalInstance = null;

    if (typeof bootstrap !== 'undefined') {
        if (uploadModalEl) {
            uploadModalInstance = bootstrap.Modal.getOrCreateInstance(uploadModalEl);
        }
        if (reviewModalEl) {
            reviewModalInstance = bootstrap.Modal.getOrCreateInstance(reviewModalEl);
        }
        if (detailModalEl) {
            detailModalInstance = bootstrap.Modal.getOrCreateInstance(detailModalEl);
        }
        if (unmatchedModalEl) {
            unmatchedModalInstance = bootstrap.Modal.getOrCreateInstance(unmatchedModalEl);
        }
        if (duplicatesModalEl) {
            duplicatesModalInstance = bootstrap.Modal.getOrCreateInstance(duplicatesModalEl);
        }
        if (historyViewModalEl) {
            historyViewModalInstance = bootstrap.Modal.getOrCreateInstance(historyViewModalEl);
        }
    }

    const uploadState = {
        courierId: '',
        courierName: '',
        fileName: '',
        hasFile: false,
    };

    const state = {
        page: 1,
        perPage: parseInt(page.dataset.perPage || '25', 10) || 25,
        filters: {
            search: '',
            payment_status: '',
            courier_id: '',
            date_from: '',
            date_to: '',
        },
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(type, message) {
        if (!alertHost) {
            return;
        }
        alertHost.className = `alert alert-${type}`;
        alertHost.textContent = message;
        alertHost.classList.remove('d-none');
    }

    function clearAlert() {
        if (!alertHost) {
            return;
        }
        alertHost.classList.add('d-none');
        alertHost.textContent = '';
    }

    function deliveryBadge(status) {
        const value = String(status || '').toUpperCase();
        return `<span class="badge-status is-delivered">${escapeHtml(value || '—')}</span>`;
    }

    function paymentLabel(status) {
        const value = String(status || '').toUpperCase();
        if (value === 'UNPAID') {
            return 'Unpaid';
        }
        if (value === 'PAID') {
            return 'Paid';
        }
        if (value === 'NONE') {
            return 'No settlement record';
        }
        return value || '—';
    }

    function paymentBadge(status) {
        const value = String(status || '').toUpperCase();
        let classes = 'badge-status';

        if (value === 'UNPAID') {
            classes = 'badge-status is-payment-pending';
        } else if (value === 'PAID') {
            classes = 'badge-status is-payment-paid';
        }

        return `<span class="${classes}">${escapeHtml(paymentLabel(value))}</span>`;
    }

    function uploadStatusBadge(status) {
        const value = String(status || '');
        let classes = 'badge-status';

        if (value === 'Processed') {
            classes = 'badge-status is-payment-received';
        } else if (value === 'Completed with warnings') {
            classes = 'badge-status is-payment-pending';
        } else if (value === 'Failed') {
            classes = 'badge-status is-out-of-stock';
        } else if (value === 'Processing' || value === 'Ready') {
            classes = 'badge-status is-dispatched';
        }

        return `<span class="${classes}">${escapeHtml(value || '—')}</span>`;
    }

    function formatCod(order) {
        const amount = order.cod_amount;
        if (amount === null || amount === undefined || amount === '') {
            return '—';
        }
        const code = order.currency_code ? `${escapeHtml(order.currency_code)} ` : '';
        return `${code}${escapeHtml(amount)}`;
    }

    function hasActiveFilters() {
        return Boolean(
            state.filters.search
            || state.filters.payment_status
            || state.filters.courier_id
            || state.filters.date_from
            || state.filters.date_to
        );
    }

    function emptyMessage() {
        if (hasActiveFilters()) {
            return page.dataset.emptyMessageFiltered || 'No orders match your filters.';
        }

        const status = String(state.filters.payment_status || '').toUpperCase();
        if (status === 'UNPAID') {
            return page.dataset.emptyMessagePending || 'No delivered orders have an unpaid supplier settlement invoice.';
        }
        if (status === 'NONE') {
            return page.dataset.emptyMessageMissing || 'No delivered orders are missing a supplier settlement record.';
        }

        return page.dataset.emptyMessageHistory || 'No delivered orders found.';
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);
        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            payment_status: (formData.get('payment_status') || '').toString(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function syncSummaryCards() {
        summaryCards.forEach((card) => {
            const cardStatus = (card.dataset.paymentFilter || '').toUpperCase();
            const isActive = state.filters.payment_status === cardStatus;
            card.classList.toggle('is-active', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function matchesFilters(order) {
        const search = state.filters.search.toLowerCase();
        if (search) {
            const haystack = [
                order.order_number,
                order.customer_name,
                order.waybill,
                order.invoice_number,
            ].join(' ').toLowerCase();

            if (!haystack.includes(search)) {
                return false;
            }
        }

        if (
            state.filters.payment_status
            && String(order.payment_status).toUpperCase() !== state.filters.payment_status.toUpperCase()
        ) {
            return false;
        }

        if (state.filters.courier_id && order.courier_id !== state.filters.courier_id) {
            return false;
        }

        const orderDate = order.payment_date || '';
        if (state.filters.date_from && orderDate && orderDate < state.filters.date_from) {
            return false;
        }
        if (state.filters.date_to && orderDate && orderDate > state.filters.date_to) {
            return false;
        }

        return true;
    }

    function filteredOrders() {
        return workspaceOrders.filter(matchesFilters);
    }

    function renderCourierRows() {
        if (!courierTableBody) {
            return;
        }

        if (!courierUploads.length) {
            courierTableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-5">
                        Courier payment file upload is not available on this screen.
                    </td>
                </tr>
            `;
            return;
        }

        courierTableBody.innerHTML = courierUploads.map((courier) => `
            <tr>
                <td>
                    <div class="order-number">${escapeHtml(courier.name || '—')}</div>
                </td>
                <td>${escapeHtml(courier.last_upload || '—')}</td>
                <td class="text-end">${escapeHtml(String(courier.records ?? 0))}</td>
                <td class="text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary text-white order-payments-upload-btn"
                        data-courier-id="${escapeHtml(courier.id || '')}"
                        data-courier-name="${escapeHtml(courier.name || '')}"
                    >
                        Upload Excel
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderRows(orders) {
        if (!tableBody) {
            return;
        }

        if (!orders.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-muted py-5">
                        ${escapeHtml(emptyMessage())}
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = orders.map((order) => `
            <tr>
                <td>
                    <div class="order-number">${escapeHtml(order.order_number || '—')}</div>
                </td>
                <td>${escapeHtml(order.customer_name || '—')}</td>
                <td>${escapeHtml(order.courier || '—')}</td>
                <td>${escapeHtml(order.waybill || '—')}</td>
                <td>${escapeHtml(order.invoice_number || '—')}</td>
                <td>${formatCod(order)}</td>
                <td>${deliveryBadge(order.delivery_status)}</td>
                <td>${paymentBadge(order.payment_status)}</td>
                <td>${escapeHtml(order.payment_received_at || '—')}</td>
                <td>${escapeHtml(order.updated_at || '—')}</td>
                <td class="text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-light border order-payments-view-btn"
                        data-order-number="${escapeHtml(order.order_number || '')}"
                    >
                        View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderUploadHistory() {
        if (!historyBody) {
            return;
        }

        if (!uploadHistory.length) {
            historyBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        ${escapeHtml(page.dataset.emptyMessageUploadHistory || 'No courier payment files have been uploaded yet.')}
                    </td>
                </tr>
            `;
            return;
        }

        historyBody.innerHTML = uploadHistory.map((row) => `
            <tr>
                <td>
                    <div class="order-number">${escapeHtml(row.file || '—')}</div>
                </td>
                <td>${escapeHtml(row.courier || '—')}</td>
                <td>${escapeHtml(row.uploaded_at || '—')}</td>
                <td class="text-end">${escapeHtml(String(row.records ?? 0))}</td>
                <td class="text-end">${escapeHtml(String(row.matched ?? 0))}</td>
                <td>${uploadStatusBadge(row.status)}</td>
                <td class="text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-light border order-payments-history-view-btn"
                        data-upload-id="${escapeHtml(row.id || '')}"
                    >
                        View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination(total, currentPage, perPage) {
        if (!paginationHost) {
            return;
        }

        if (total === 0) {
            paginationHost.innerHTML = '';
            if (resultsMeta) {
                resultsMeta.textContent = '';
            }
            return;
        }

        const lastPage = Math.max(1, Math.ceil(total / perPage));
        const safePage = Math.min(Math.max(currentPage, 1), lastPage);
        const from = ((safePage - 1) * perPage) + 1;
        const to = Math.min(safePage * perPage, total);

        if (resultsMeta) {
            resultsMeta.textContent = `Showing ${from}–${to} of ${total} orders`;
        }

        let pagesHtml = '';
        for (let pageNum = 1; pageNum <= lastPage; pageNum += 1) {
            if (lastPage > 7 && Math.abs(pageNum - safePage) > 2 && pageNum !== 1 && pageNum !== lastPage) {
                if (pageNum === 2 || pageNum === lastPage - 1) {
                    pagesHtml += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                }
                continue;
            }

            pagesHtml += `
                <li class="page-item ${pageNum === safePage ? 'active' : ''}">
                    <button type="button" class="page-link order-payments-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Order payments pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${safePage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon order-payments-page-btn" data-page="${safePage - 1}" ${safePage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${safePage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon order-payments-page-btn" data-page="${safePage + 1}" ${safePage >= lastPage ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">east</i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                ` : ''}
            </div>
        `;
    }

    function refreshTable() {
        clearAlert();
        syncSummaryCards();

        const filtered = filteredOrders();
        const lastPage = Math.max(1, Math.ceil(filtered.length / state.perPage));
        if (state.page > lastPage) {
            state.page = lastPage;
        }

        const start = (state.page - 1) * state.perPage;
        const pageRows = filtered.slice(start, start + state.perPage);

        renderRows(pageRows);
        renderPagination(filtered.length, state.page, state.perPage);
    }

    function resetFilters({ keepPaymentStatus = false } = {}) {
        if (searchInput) {
            searchInput.value = '';
        }
        if (paymentStatusSelect && !keepPaymentStatus) {
            paymentStatusSelect.value = '';
        }
        if (courierSelect) {
            courierSelect.value = '';
        }
        if (dateFromInput) {
            dateFromInput.value = '';
        }
        if (dateToInput) {
            dateToInput.value = '';
        }
        readFiltersFromForm();
        state.page = 1;
        refreshTable();
    }

    function populateUploadCourierOptions(selectedId) {
        if (!uploadCourierSelect) {
            return;
        }

        const options = courierOptions.length
            ? courierOptions
            : courierUploads.map((c) => ({ id: c.id, name: c.name }));

        uploadCourierSelect.innerHTML = `<option value="">Select courier</option>`
            + options.map((courier) => `
                <option value="${escapeHtml(courier.id)}" ${courier.id === selectedId ? 'selected' : ''}>
                    ${escapeHtml(courier.name)}
                </option>
            `).join('');
    }

    function resetUploadModal() {
        uploadState.courierId = '';
        uploadState.courierName = '';
        uploadState.fileName = '';
        uploadState.hasFile = false;

        if (uploadFileInput) {
            uploadFileInput.value = '';
        }
        if (uploadPreview) {
            uploadPreview.classList.add('d-none');
        }
        if (reviewFileBtn) {
            reviewFileBtn.disabled = true;
        }
    }

    function syncReviewFileButton() {
        if (!reviewFileBtn) {
            return;
        }
        reviewFileBtn.disabled = !(uploadState.hasFile && uploadState.courierId);
    }

    function openUploadModal(courierId, courierName) {
        resetUploadModal();
        uploadState.courierId = courierId || '';
        uploadState.courierName = courierName || '';
        populateUploadCourierOptions(uploadState.courierId);
        syncReviewFileButton();

        if (uploadModalInstance) {
            uploadModalInstance.show();
        }
    }

    function showFilePreview() {
        uploadState.hasFile = false;
        uploadState.fileName = '';
        if (uploadFileInput) {
            uploadFileInput.value = '';
        }
        if (uploadPreview) {
            uploadPreview.classList.add('d-none');
        }
        syncReviewFileButton();
        showAlert(
            'info',
            'Courier payment file upload is not available. Supplier payments are shown from settlement invoices.'
        );
    }

    function resetReviewModal() {
        if (reviewContent) {
            reviewContent.classList.remove('d-none');
        }
        if (reviewSuccess) {
            reviewSuccess.classList.add('d-none');
        }
        if (reviewFooter) {
            reviewFooter.classList.remove('d-none');
        }
        if (reviewSuccessFooter) {
            reviewSuccessFooter.classList.add('d-none');
        }
    }

    function openReviewModal() {
        showFilePreview();
    }

    function confirmUpload() {
        showAlert(
            'info',
            'Courier payment file upload is not available. No settlement payment was recorded.'
        );
    }

    function openDetailModal(order) {
        if (!order || !detailModalInstance) {
            return;
        }

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value || '—';
            }
        };

        setText('orderPaymentsDetailOrder', order.order_number);
        setText('orderPaymentsDetailCustomer', order.customer_name);
        setText('orderPaymentsDetailPhone', order.primary_phone);
        setText('orderPaymentsDetailCourier', order.courier);
        setText('orderPaymentsDetailWaybill', order.waybill);
        setText('orderPaymentsDetailReceivedAt', order.payment_received_at || '—');
        setText('orderPaymentsDetailSource', order.source || '—');

        const codEl = document.getElementById('orderPaymentsDetailCod');
        if (codEl) {
            const code = order.currency_code ? `${order.currency_code} ` : '';
            codEl.textContent = order.cod_amount ? `${code}${order.cod_amount}` : '—';
        }

        const deliveryEl = document.getElementById('orderPaymentsDetailDelivery');
        const paymentEl = document.getElementById('orderPaymentsDetailPayment');
        if (deliveryEl) {
            deliveryEl.innerHTML = deliveryBadge(order.delivery_status);
        }
        if (paymentEl) {
            paymentEl.innerHTML = paymentBadge(order.payment_status);
        }

        detailModalInstance.show();
    }

    function openUnmatchedModal() {
        if (!unmatchedBody) {
            return;
        }

        if (!unmatchedWaybills.length) {
            unmatchedBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-4">No unmatched waybills.</td>
                </tr>
            `;
        } else {
            unmatchedBody.innerHTML = unmatchedWaybills.map((row) => `
                <tr>
                    <td>${escapeHtml(String(row.row ?? '—'))}</td>
                    <td><div class="order-number">${escapeHtml(row.waybill || '—')}</div></td>
                    <td>${escapeHtml(row.reason || '—')}</td>
                </tr>
            `).join('');
        }

        if (unmatchedModalInstance) {
            unmatchedModalInstance.show();
        }
    }

    function openDuplicatesModal() {
        if (!duplicatesBody) {
            return;
        }

        if (!duplicateRows.length) {
            duplicatesBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-4">No duplicate rows.</td>
                </tr>
            `;
        } else {
            duplicatesBody.innerHTML = duplicateRows.map((row) => `
                <tr>
                    <td>${escapeHtml(String(row.row ?? '—'))}</td>
                    <td><div class="order-number">${escapeHtml(row.waybill || '—')}</div></td>
                    <td>${escapeHtml(String(row.duplicate_of_row ?? '—'))}</td>
                </tr>
            `).join('');
        }

        if (duplicatesModalInstance) {
            duplicatesModalInstance.show();
        }
    }

    function openHistoryViewModal(row) {
        if (!row || !historyViewModalInstance) {
            return;
        }

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value ?? '—';
            }
        };

        setText('orderPaymentsHistoryViewFile', row.file);
        setText('orderPaymentsHistoryViewCourier', row.courier);
        setText('orderPaymentsHistoryViewUploadedAt', row.uploaded_at);
        setText('orderPaymentsHistoryViewMatched', `${row.matched ?? 0} / ${row.records ?? 0}`);
        setText('orderPaymentsHistoryViewRecords', String(row.records ?? 0));
        setText('orderPaymentsHistoryViewUnmatched', String(row.unmatched ?? 0));
        setText('orderPaymentsHistoryViewDuplicates', String(row.duplicates ?? 0));

        const statusEl = document.getElementById('orderPaymentsHistoryViewStatus');
        if (statusEl) {
            statusEl.innerHTML = uploadStatusBadge(row.status);
        }

        historyViewModalInstance.show();
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            readFiltersFromForm();
            state.page = 1;
            refreshTable();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => resetFilters());
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => resetFilters());
    }

    [paymentStatusSelect, courierSelect, dateFromInput, dateToInput].forEach((el) => {
        if (!el) {
            return;
        }
        el.addEventListener('change', () => {
            readFiltersFromForm();
            state.page = 1;
            refreshTable();
        });
    });

    summaryCards.forEach((card) => {
        card.addEventListener('click', () => {
            const nextStatus = (card.dataset.paymentFilter || '').toUpperCase();
            if (!paymentStatusSelect) {
                return;
            }

            if (paymentStatusSelect.value === nextStatus) {
                paymentStatusSelect.value = '';
            } else {
                paymentStatusSelect.value = nextStatus;
            }

            readFiltersFromForm();
            state.page = 1;
            refreshTable();
        });
    });

    if (paginationHost) {
        paginationHost.addEventListener('click', (event) => {
            const btn = event.target.closest('.order-payments-page-btn');
            if (!btn || btn.disabled) {
                return;
            }
            const nextPage = parseInt(btn.dataset.page || '1', 10);
            if (!Number.isFinite(nextPage) || nextPage < 1) {
                return;
            }
            state.page = nextPage;
            refreshTable();
        });
    }

    if (courierTableBody) {
        courierTableBody.addEventListener('click', (event) => {
            const btn = event.target.closest('.order-payments-upload-btn');
            if (!btn) {
                return;
            }
            openUploadModal(btn.dataset.courierId || '', btn.dataset.courierName || '');
        });
    }

    if (uploadCourierSelect) {
        uploadCourierSelect.addEventListener('change', () => {
            uploadState.courierId = uploadCourierSelect.value || '';
            const option = uploadCourierSelect.options[uploadCourierSelect.selectedIndex];
            uploadState.courierName = option ? option.textContent.trim() : '';
            syncReviewFileButton();
        });
    }

    if (uploadFileInput) {
        uploadFileInput.addEventListener('change', () => {
            const file = uploadFileInput.files && uploadFileInput.files[0];
            if (!file) {
                uploadState.hasFile = false;
                uploadState.fileName = '';
                if (uploadPreview) {
                    uploadPreview.classList.add('d-none');
                }
                syncReviewFileButton();
                return;
            }

            showFilePreview();
        });
    }

    if (reviewFileBtn) {
        reviewFileBtn.addEventListener('click', () => {
            if (!uploadState.hasFile || !uploadState.courierId) {
                return;
            }
            openReviewModal();
        });
    }

    if (confirmUploadBtn) {
        confirmUploadBtn.addEventListener('click', () => {
            confirmUpload();
        });
    }

    if (viewUnmatchedBtn) {
        viewUnmatchedBtn.addEventListener('click', () => openUnmatchedModal());
    }

    if (viewDuplicatesBtn) {
        viewDuplicatesBtn.addEventListener('click', () => openDuplicatesModal());
    }

    if (tableBody) {
        tableBody.addEventListener('click', (event) => {
            const viewBtn = event.target.closest('.order-payments-view-btn');
            if (!viewBtn) {
                return;
            }
            const orderNumber = viewBtn.dataset.orderNumber || '';
            const order = workspaceOrders.find((row) => row.order_number === orderNumber);
            if (order) {
                openDetailModal(order);
            }
        });
    }

    if (historyBody) {
        historyBody.addEventListener('click', (event) => {
            const viewBtn = event.target.closest('.order-payments-history-view-btn');
            if (!viewBtn) {
                return;
            }
            const uploadId = viewBtn.dataset.uploadId || '';
            const row = uploadHistory.find((item) => item.id === uploadId);
            if (row) {
                openHistoryViewModal(row);
            }
        });
    }

    if (uploadModalEl) {
        uploadModalEl.addEventListener('hidden.bs.modal', () => {
            // Keep selection only if review modal took over; otherwise clear.
            if (!reviewModalEl || !reviewModalEl.classList.contains('show')) {
                resetUploadModal();
            }
        });
    }

    if (reviewModalEl) {
        reviewModalEl.addEventListener('hidden.bs.modal', () => {
            resetReviewModal();
            resetUploadModal();
        });
    }

    renderCourierRows();
    renderUploadHistory();
    readFiltersFromForm();
    refreshTable();
})();
</script>
