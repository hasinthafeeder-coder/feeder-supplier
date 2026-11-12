<script>
(function () {
    const page = document.getElementById('supplierDispatchSummaryPage');
    if (!page) {
        return;
    }

    const pendingTableBody = document.getElementById('dispatchSummaryPendingTableBody');
    const historyTableBody = document.getElementById('dispatchSummaryHistoryTableBody');
    const resultsMeta = document.getElementById('dispatchSummaryHistoryResultsMeta');
    const paginationHost = document.getElementById('dispatchSummaryHistoryPaginationHost');
    const filterForm = document.getElementById('dispatchSummaryFilterForm');
    const searchInput = document.getElementById('dispatchSummaryFilterSearch');
    const courierSelect = document.getElementById('dispatchSummaryFilterCourier');
    const dateFromInput = document.getElementById('dispatchSummaryFilterDateFrom');
    const dateToInput = document.getElementById('dispatchSummaryFilterDateTo');
    const clearBtn = document.getElementById('dispatchSummaryClearFilters');
    const resetBtn = document.getElementById('dispatchSummaryResetFilters');
    const alertHost = document.getElementById('dispatchSummaryAlertHost');

    const createModalEl = document.getElementById('dispatchSummaryCreateModal');
    const createCourierEl = document.getElementById('dispatchSummaryCreateCourier');
    const createPendingLabelEl = document.getElementById('dispatchSummaryCreatePendingLabel');
    const createTotalCodEl = document.getElementById('dispatchSummaryCreateTotalCod');
    const createOrdersCountEl = document.getElementById('dispatchSummaryCreateOrdersCount');
    const createPreviewCodEl = document.getElementById('dispatchSummaryCreatePreviewCod');
    const createPreviewCourierEl = document.getElementById('dispatchSummaryCreatePreviewCourier');
    const createOrdersBody = document.getElementById('dispatchSummaryCreateOrdersBody');
    const createConfirmBtn = document.getElementById('dispatchSummaryCreateConfirmBtn');

    const viewModalEl = document.getElementById('dispatchSummaryViewModal');
    const viewSubtitleEl = document.getElementById('dispatchSummaryViewSubtitle');
    const viewDispatchNoEl = document.getElementById('dispatchSummaryViewDispatchNo');
    const viewSupplierEl = document.getElementById('dispatchSummaryViewSupplier');
    const viewCourierEl = document.getElementById('dispatchSummaryViewCourier');
    const viewDateEl = document.getElementById('dispatchSummaryViewDate');
    const viewTimeEl = document.getElementById('dispatchSummaryViewTime');
    const viewOperatorEl = document.getElementById('dispatchSummaryViewOperator');
    const viewOrdersCountEl = document.getElementById('dispatchSummaryViewOrdersCount');
    const viewTotalCodEl = document.getElementById('dispatchSummaryViewTotalCod');
    const viewPreviewCourierEl = document.getElementById('dispatchSummaryViewPreviewCourier');
    const viewOrdersBody = document.getElementById('dispatchSummaryViewOrdersBody');
    const viewPrintNotice = document.getElementById('dispatchSummaryViewPrintNotice');
    const printBtn = document.getElementById('dispatchSummaryPrintBtn');

    let pendingCouriers = [];
    let dispatchHistory = [];
    try {
        pendingCouriers = JSON.parse(page.dataset.pendingCouriers || '[]');
    } catch (error) {
        pendingCouriers = [];
    }
    try {
        dispatchHistory = JSON.parse(page.dataset.dispatchHistory || '[]');
    } catch (error) {
        dispatchHistory = [];
    }

    const supplierName = page.dataset.supplierName || 'Supplier';
    const createUrl = page.dataset.createUrl || '';
    const printUrlTemplate = page.dataset.printUrlTemplate || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let activeCreateCourier = null;
    let activeViewNote = null;
    let createModalInstance = null;
    let viewModalInstance = null;
    let createSubmitting = false;

    if (createModalEl && typeof bootstrap !== 'undefined') {
        createModalInstance = bootstrap.Modal.getOrCreateInstance(createModalEl);
    }
    if (viewModalEl && typeof bootstrap !== 'undefined') {
        viewModalInstance = bootstrap.Modal.getOrCreateInstance(viewModalEl);
    }

    const state = {
        page: 1,
        perPage: parseInt(page.dataset.perPage || '25', 10) || 25,
        filters: {
            search: '',
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

    function formatCod(amount, currencyCode) {
        if (amount === null || amount === undefined || amount === '') {
            return '—';
        }
        const code = currencyCode ? `${escapeHtml(currencyCode)} ` : '';
        return `${code}${escapeHtml(amount)}`;
    }

    function showAlert(type, message) {
        if (!alertHost) {
            return;
        }
        alertHost.className = `alert alert-${type}`;
        alertHost.innerHTML = message;
        alertHost.classList.remove('d-none');
    }

    function clearAlert() {
        if (!alertHost) {
            return;
        }
        alertHost.classList.add('d-none');
        alertHost.textContent = '';
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);
        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function hasActiveFilters() {
        return Boolean(
            state.filters.search
            || state.filters.courier_id
            || state.filters.date_from
            || state.filters.date_to
        );
    }

    function emptyHistoryMessage() {
        if (hasActiveFilters()) {
            return page.dataset.emptyMessageFiltered || 'No orders match your filters.';
        }
        return page.dataset.emptyMessageHistory || 'No Dispatch Notes recorded yet.';
    }

    function printUrlForNote(note) {
        if (!note || !note.uuid || !printUrlTemplate) {
            return '';
        }

        return printUrlTemplate.replace('__UUID__', encodeURIComponent(note.uuid));
    }

    function openPrintWindow(note) {
        const url = printUrlForNote(note);
        if (!url) {
            showAlert('danger', 'Print URL is not available for this Dispatch Note.');
            return;
        }

        window.open(url, '_blank', 'noopener');
    }

    function noteMatchesSearch(note, search) {
        if (!search) {
            return true;
        }

        const direct = [
            note.dispatch_no,
            note.courier,
            note.operator,
        ].join(' ').toLowerCase();

        if (direct.includes(search)) {
            return true;
        }

        const orders = Array.isArray(note.orders) ? note.orders : [];
        return orders.some((order) => {
            const haystack = [
                order.order_number,
                order.waybill,
                order.invoice_number,
                order.customer_name,
            ].join(' ').toLowerCase();
            return haystack.includes(search);
        });
    }

    function matchesFilters(note) {
        const search = state.filters.search.toLowerCase();
        if (!noteMatchesSearch(note, search)) {
            return false;
        }

        if (state.filters.courier_id && note.courier_id !== state.filters.courier_id) {
            return false;
        }

        const noteDate = note.dispatch_date || '';
        if (state.filters.date_from && noteDate && noteDate < state.filters.date_from) {
            return false;
        }
        if (state.filters.date_to && noteDate && noteDate > state.filters.date_to) {
            return false;
        }

        return true;
    }

    function filteredHistory() {
        return dispatchHistory.filter(matchesFilters);
    }

    function renderOrderRows(orders, bodyEl) {
        if (!bodyEl) {
            return;
        }

        if (!orders.length) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No orders available.</td>
                </tr>
            `;
            return;
        }

        bodyEl.innerHTML = orders.map((order) => `
            <tr>
                <td><div class="order-number">${escapeHtml(order.order_number || '—')}</div></td>
                <td>${escapeHtml(order.customer_name || '—')}</td>
                <td>${escapeHtml(order.primary_phone || '—')}</td>
                <td>${escapeHtml(order.waybill || '—')}</td>
                <td>${escapeHtml(order.invoice_number || '—')}</td>
                <td class="text-end">${formatCod(order.cod_amount, order.currency_code)}</td>
            </tr>
        `).join('');
    }

    function renderPendingCouriers() {
        if (!pendingTableBody) {
            return;
        }

        if (!pendingCouriers.length) {
            pendingTableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-5">
                        ${escapeHtml(page.dataset.emptyMessagePending || 'No dispatched, delivered, or returned orders found.')}
                    </td>
                </tr>
            `;
            return;
        }

        pendingTableBody.innerHTML = pendingCouriers.map((courier) => {
            const pendingCount = Number(courier.pending_count || 0);
            const canCreate = pendingCount > 0;
            const title = canCreate
                ? 'Preview dispatched orders awaiting handover and create a Dispatch Note.'
                : 'No orders awaiting handover for this courier';

            return `
                <tr>
                    <td>
                        <div class="order-number">${escapeHtml(courier.name || '—')}</div>
                        <div class="order-sub">From stored shipments</div>
                    </td>
                    <td class="text-end">
                        <span class="badge-status is-dispatched">${escapeHtml(String(pendingCount))}</span>
                    </td>
                    <td>${escapeHtml(courier.last_dispatch || '—')}</td>
                    <td class="text-end">
                        <a href="javascript:void(0)"
                            class="print-action-link dispatch-summary-create-btn${canCreate ? '' : ' is-disabled'}"
                            data-courier-id="${escapeHtml(courier.id || '')}"
                            role="button"
                            ${canCreate ? '' : 'aria-disabled="true" tabindex="-1"'}
                            title="${escapeHtml(title)}"
                        >
                            Create Dispatch Note
                        </a>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function disposeHistoryActionTooltips() {
        if (!window.bootstrap || typeof bootstrap.Tooltip !== 'function' || !historyTableBody) {
            return;
        }

        historyTableBody.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            const instance = bootstrap.Tooltip.getInstance(el);
            if (instance) {
                instance.dispose();
            }
        });
    }

    function initHistoryActionTooltips() {
        if (!window.bootstrap || typeof bootstrap.Tooltip !== 'function' || !historyTableBody) {
            return;
        }

        historyTableBody.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function renderHistoryRows(notes) {
        if (!historyTableBody) {
            return;
        }

        disposeHistoryActionTooltips();

        if (!notes.length) {
            historyTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        ${escapeHtml(emptyHistoryMessage())}
                    </td>
                </tr>
            `;
            return;
        }

        historyTableBody.innerHTML = notes.map((note) => {
            const ordersCount = Number(note.orders_count ?? (Array.isArray(note.orders) ? note.orders.length : 0));

            return `
                <tr>
                    <td>
                        <div class="order-number">${escapeHtml(note.dispatch_no || '—')}</div>
                        <div class="order-sub">Recorded handover</div>
                    </td>
                    <td class="text-end">${escapeHtml(String(ordersCount))}</td>
                    <td>${escapeHtml(note.courier || '—')}</td>
                    <td>${escapeHtml(note.dispatch_date_display || note.dispatch_date || '—')}</td>
                    <td>${escapeHtml(note.dispatch_time_display || note.dispatch_time || '—')}</td>
                    <td>${escapeHtml(note.operator || '—')}</td>
                    <td class="text-end">${formatCod(note.total_cod, note.currency_code)}</td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end" style="gap: 12px;">
                            <button
                                type="button"
                                class="bg-transparent p-0 border-0 dispatch-summary-view-btn"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="View Dispatch Note"
                                data-dispatch-no="${escapeHtml(note.dispatch_no || '')}"
                            >
                                <i class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                            </button>
                            <a href="javascript:void(0)"
                                class="print-action-link dispatch-summary-print-row-btn"
                                role="button"
                                data-dispatch-no="${escapeHtml(note.dispatch_no || '')}"
                                title="Print Dispatch Note"
                            >
                                Print
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        initHistoryActionTooltips();
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
            resultsMeta.textContent = `Showing ${from}–${to} of ${total} Dispatch Notes`;
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
                    <button type="button" class="page-link dispatch-summary-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Dispatch Note history pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${safePage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon dispatch-summary-page-btn" data-page="${safePage - 1}" ${safePage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${safePage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon dispatch-summary-page-btn" data-page="${safePage + 1}" ${safePage >= lastPage ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">east</i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                ` : ''}
            </div>
        `;
    }

    function refreshHistory() {
        const filtered = filteredHistory();
        const lastPage = Math.max(1, Math.ceil(filtered.length / state.perPage));
        if (state.page > lastPage) {
            state.page = lastPage;
        }

        const start = (state.page - 1) * state.perPage;
        const pageRows = filtered.slice(start, start + state.perPage);

        renderHistoryRows(pageRows);
        renderPagination(filtered.length, state.page, state.perPage);
    }

    function resetFilters() {
        if (searchInput) {
            searchInput.value = '';
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
        refreshHistory();
    }

    function findCourier(courierId) {
        return pendingCouriers.find((courier) => courier.id === courierId) || null;
    }

    function findHistoryNote(dispatchNo) {
        return dispatchHistory.find((note) => note.dispatch_no === dispatchNo) || null;
    }

    function openCreateModal(courier) {
        activeCreateCourier = courier;
        const pendingCount = Number(courier.pending_count || 0);
        const orders = Array.isArray(courier.orders) ? courier.orders : [];
        const codLabel = formatCod(courier.total_cod, courier.currency_code);

        if (createCourierEl) {
            createCourierEl.textContent = courier.name || '—';
        }
        if (createPendingLabelEl) {
            createPendingLabelEl.textContent = `${pendingCount} order${pendingCount === 1 ? '' : 's'} awaiting handover`;
        }
        if (createTotalCodEl) {
            createTotalCodEl.textContent = codLabel;
        }
        if (createOrdersCountEl) {
            createOrdersCountEl.textContent = String(pendingCount);
        }
        if (createPreviewCodEl) {
            createPreviewCodEl.textContent = codLabel;
        }
        if (createPreviewCourierEl) {
            createPreviewCourierEl.textContent = courier.name || '—';
        }

        renderOrderRows(orders, createOrdersBody);

        if (createConfirmBtn) {
            createConfirmBtn.disabled = pendingCount === 0;
        }

        if (createModalInstance) {
            createModalInstance.show();
        }
    }

    function openViewModal(note) {
        activeViewNote = note;
        const orders = Array.isArray(note.orders) ? note.orders : [];
        const codLabel = formatCod(note.total_cod, note.currency_code);

        if (viewSubtitleEl) {
            viewSubtitleEl.textContent = note.dispatch_no || 'Dispatch Note detail';
        }
        if (viewDispatchNoEl) {
            viewDispatchNoEl.textContent = note.dispatch_no || '—';
        }
        if (viewSupplierEl) {
            viewSupplierEl.textContent = note.supplier || supplierName;
        }
        if (viewCourierEl) {
            viewCourierEl.textContent = note.courier || '—';
        }
        if (viewDateEl) {
            viewDateEl.textContent = note.dispatch_date_display || note.dispatch_date || '—';
        }
        if (viewTimeEl) {
            viewTimeEl.textContent = note.dispatch_time_display || note.dispatch_time || '—';
        }
        if (viewOperatorEl) {
            viewOperatorEl.textContent = note.operator || '—';
        }
        if (viewOrdersCountEl) {
            viewOrdersCountEl.textContent = String(note.orders_count ?? orders.length);
        }
        if (viewTotalCodEl) {
            viewTotalCodEl.textContent = codLabel;
        }
        if (viewPreviewCourierEl) {
            viewPreviewCourierEl.textContent = note.courier || '—';
        }
        if (viewPrintNotice) {
            viewPrintNotice.classList.add('d-none');
            viewPrintNotice.textContent = '';
        }

        renderOrderRows(orders, viewOrdersBody);

        if (viewModalInstance) {
            viewModalInstance.show();
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            readFiltersFromForm();
            state.page = 1;
            refreshHistory();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => resetFilters());
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => resetFilters());
    }

    [courierSelect, dateFromInput, dateToInput].forEach((el) => {
        if (!el) {
            return;
        }
        el.addEventListener('change', () => {
            readFiltersFromForm();
            state.page = 1;
            refreshHistory();
        });
    });

    if (paginationHost) {
        paginationHost.addEventListener('click', (event) => {
            const btn = event.target.closest('.dispatch-summary-page-btn');
            if (!btn || btn.disabled) {
                return;
            }
            const nextPage = parseInt(btn.dataset.page || '1', 10);
            if (!Number.isFinite(nextPage) || nextPage < 1) {
                return;
            }
            state.page = nextPage;
            refreshHistory();
        });
    }

    if (pendingTableBody) {
        pendingTableBody.addEventListener('click', (event) => {
            const createBtn = event.target.closest('.dispatch-summary-create-btn');
            if (!createBtn) {
                return;
            }

            event.preventDefault();
            if (createBtn.getAttribute('aria-disabled') === 'true' || createBtn.classList.contains('is-disabled')) {
                return;
            }

            const courier = findCourier(createBtn.dataset.courierId || '');
            if (!courier) {
                return;
            }
            openCreateModal(courier);
        });
    }

    if (historyTableBody) {
        historyTableBody.addEventListener('click', (event) => {
            const viewBtn = event.target.closest('.dispatch-summary-view-btn');
            if (viewBtn) {
                const note = findHistoryNote(viewBtn.dataset.dispatchNo || '');
                if (note) {
                    openViewModal(note);
                }
                return;
            }

            const printRowBtn = event.target.closest('.dispatch-summary-print-row-btn');
            if (printRowBtn) {
                event.preventDefault();
                const note = findHistoryNote(printRowBtn.dataset.dispatchNo || '');
                if (note) {
                    openPrintWindow(note);
                }
            }
        });
    }

    if (createConfirmBtn) {
        createConfirmBtn.addEventListener('click', async () => {
            if (!activeCreateCourier || createSubmitting) {
                return;
            }

            if (!createUrl) {
                showAlert('danger', 'Dispatch Note create URL is not configured.');
                return;
            }

            const courierId = activeCreateCourier.id || '';
            const courierName = activeCreateCourier.name || 'courier';
            createSubmitting = true;
            createConfirmBtn.disabled = true;
            clearAlert();

            try {
                const response = await fetch(createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        courier_id: courierId === '' ? null : Number(courierId),
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message = payload.message || 'Unable to create Dispatch Note.';
                    showAlert('danger', escapeHtml(message));
                    return;
                }

                const note = payload.data || {};
                const dispatchNo = note.dispatch_no || 'Dispatch Note';
                const count = Number(note.orders_count || activeCreateCourier.pending_count || 0);

                if (createModalInstance) {
                    createModalInstance.hide();
                }

                showAlert(
                    'success',
                    `<strong>${escapeHtml(dispatchNo)} created.</strong><div class="fs-13 mt-1">${escapeHtml(String(count))} ${escapeHtml(courierName)} orders remain DISPATCHED. Reloading summary…</div>`
                );

                window.setTimeout(() => {
                    window.location.reload();
                }, 700);
            } catch (error) {
                showAlert('danger', 'Unable to create Dispatch Note. Please try again.');
            } finally {
                createSubmitting = false;
                if (createConfirmBtn && activeCreateCourier) {
                    createConfirmBtn.disabled = Number(activeCreateCourier.pending_count || 0) === 0;
                }
            }
        });
    }

    if (printBtn) {
        printBtn.addEventListener('click', () => {
            if (!activeViewNote) {
                return;
            }
            openPrintWindow(activeViewNote);
        });
    }

    clearAlert();
    renderPendingCouriers();
    readFiltersFromForm();
    refreshHistory();
})();
</script>
