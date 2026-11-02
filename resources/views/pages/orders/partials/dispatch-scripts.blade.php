<script>
(function () {
    const page = document.getElementById('supplierDispatchOrdersPage');
    if (!page) {
        return;
    }

    const tableBody = document.getElementById('dispatchOrdersTableBody');
    const resultsMeta = document.getElementById('dispatchOrdersResultsMeta');
    const paginationHost = document.getElementById('dispatchOrdersPaginationHost');
    const filterForm = document.getElementById('dispatchOrdersFilterForm');
    const searchInput = document.getElementById('dispatchFilterSearch');
    const statusSelect = document.getElementById('dispatchFilterStatus');
    const courierSelect = document.getElementById('dispatchFilterCourier');
    const dateFromInput = document.getElementById('dispatchFilterDateFrom');
    const dateToInput = document.getElementById('dispatchFilterDateTo');
    const clearBtn = document.getElementById('dispatchOrdersClearFilters');
    const resetBtn = document.getElementById('dispatchOrdersResetFilters');
    const summaryCards = Array.from(page.querySelectorAll('.dispatch-summary-card[data-status-filter]'));
    const alertHost = document.getElementById('dispatchOrdersAlertHost');
    const summaryDispatched = document.getElementById('dispatchSummaryDispatched');
    const summaryReturnedToday = document.getElementById('dispatchSummaryReturnedToday');

    const scanInput = document.getElementById('dispatchReturnScanInput');
    const scanHint = document.getElementById('dispatchReturnScanHint');
    const scanBusy = document.getElementById('dispatchReturnScanBusy');
    const scanResult = document.getElementById('dispatchReturnScanResult');
    const scanResultPlaceholder = document.getElementById('dispatchReturnScanResultPlaceholder');
    const damagedCheckbox = document.getElementById('dispatchReturnDamagedCheckbox');
    const damagedCheckWrap = document.getElementById('dispatchReturnDamagedCheckWrap');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const listUrl = page.dataset.listUrl || '';
    const scanReturnUrl = page.dataset.scanReturnUrl || '';
    const orderShowBase = (page.dataset.orderShowBase || '/orders').replace(/\/$/, '');
    const canReturn = page.dataset.canReturn === '1';

    const state = {
        page: 1,
        perPage: parseInt(page.dataset.perPage || '25', 10) || 25,
        filters: {
            search: '',
            status: 'DISPATCHED',
            courier_id: '',
            date_from: '',
            date_to: '',
        },
        meta: null,
        loading: false,
        scanningReturn: false,
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

    function statusBadge(status) {
        const value = String(status || '').toUpperCase();
        let classes = 'badge-status';

        if (value === 'DISPATCHED') {
            classes = 'badge-status is-dispatched';
        } else if (value === 'RETURNED') {
            classes = 'badge-status is-returned';
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

    function emptyMessage() {
        const hasSearch = Boolean(state.filters.search);
        const hasCourier = Boolean(state.filters.courier_id);
        const hasDates = Boolean(state.filters.date_from || state.filters.date_to);

        if (hasSearch || hasCourier || hasDates) {
            return page.dataset.emptyMessageSearch || 'No orders match your search or filters.';
        }

        const status = String(state.filters.status || '').toUpperCase();
        if (status === 'DISPATCHED') {
            return page.dataset.emptyMessageDispatched || 'No dispatched orders found.';
        }
        if (status === 'RETURNED') {
            return page.dataset.emptyMessageReturned || 'No returned orders found.';
        }

        return page.dataset.emptyMessageAll || 'No dispatched orders found.';
    }

    function orderShowUrl(order) {
        const key = order.uuid || order.id;
        if (!key) {
            return '';
        }
        return `${orderShowBase}/${encodeURIComponent(key)}`;
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);
        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            status: (formData.get('status') || '').toString(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function syncSummaryCards() {
        summaryCards.forEach((card) => {
            const cardStatus = (card.dataset.statusFilter || '').toUpperCase();
            const isActive = state.filters.status === cardStatus;
            card.classList.toggle('is-active', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function updateSummaryCounts(counts) {
        if (!counts) {
            return;
        }
        if (summaryDispatched && counts.dispatched !== undefined) {
            summaryDispatched.textContent = Number(counts.dispatched).toLocaleString();
        }
        if (summaryReturnedToday && counts.returned_today !== undefined) {
            summaryReturnedToday.textContent = Number(counts.returned_today).toLocaleString();
        }
    }

    function fillCourierSelect(couriers, selectedValue) {
        if (!courierSelect) {
            return;
        }

        const current = selectedValue ?? courierSelect.value;
        courierSelect.innerHTML = '<option value="">All Couriers</option>';

        (couriers || []).forEach((courier) => {
            const option = document.createElement('option');
            option.value = String(courier.id);
            option.textContent = courier.name;
            if (String(current) === String(courier.id)) {
                option.selected = true;
            }
            courierSelect.appendChild(option);
        });
    }

    function buildQueryParams() {
        const params = new URLSearchParams();
        params.set('page', String(state.page));
        params.set('per_page', String(state.perPage));

        if (state.filters.search) {
            params.set('search', state.filters.search);
        }
        if (state.filters.status) {
            params.set('status', state.filters.status);
        }
        if (state.filters.courier_id) {
            params.set('courier_id', state.filters.courier_id);
        }
        if (state.filters.date_from) {
            params.set('date_from', state.filters.date_from);
        }
        if (state.filters.date_to) {
            params.set('date_to', state.filters.date_to);
        }

        return params;
    }

    function renderRows(orders) {
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

        tableBody.innerHTML = orders.map((order) => {
            const showUrl = orderShowUrl(order);
            const viewCell = showUrl
                ? `<a href="${escapeHtml(showUrl)}" class="btn btn-sm btn-light border order-view-btn">View</a>`
                : '—';

            return `
                <tr>
                    <td>
                        <div class="order-number">${escapeHtml(order.order_number || '—')}</div>
                    </td>
                    <td>${escapeHtml(order.customer_name || '—')}</td>
                    <td>${escapeHtml(order.primary_phone || '—')}</td>
                    <td>${escapeHtml(order.courier || '—')}</td>
                    <td>${escapeHtml(order.waybill || order.tracking_number || '—')}</td>
                    <td>${escapeHtml(order.invoice_number || '—')}</td>
                    <td>${formatCod(order)}</td>
                    <td>${statusBadge(order.status)}</td>
                    <td>
                        <div>${escapeHtml(order.dispatched_at || '—')}</div>
                    </td>
                    <td>
                        <div>${escapeHtml(order.updated_at || '—')}</div>
                    </td>
                    <td class="text-end">
                        ${viewCell}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(meta) {
        if (!paginationHost) {
            return;
        }

        if (!meta || !meta.total) {
            paginationHost.innerHTML = '';
            if (resultsMeta) {
                resultsMeta.textContent = '';
            }
            return;
        }

        const total = meta.total || 0;
        const perPage = meta.per_page || state.perPage;
        const currentPage = meta.current_page || state.page;
        const lastPage = meta.last_page || 1;
        const from = total === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
        const to = Math.min(currentPage * perPage, total);

        if (resultsMeta) {
            resultsMeta.textContent = `Showing ${from}–${to} of ${total} orders`;
        }

        let pagesHtml = '';
        for (let pageNum = 1; pageNum <= lastPage; pageNum += 1) {
            if (lastPage > 7 && Math.abs(pageNum - currentPage) > 2 && pageNum !== 1 && pageNum !== lastPage) {
                if (pageNum === 2 || pageNum === lastPage - 1) {
                    pagesHtml += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                }
                continue;
            }

            pagesHtml += `
                <li class="page-item ${pageNum === currentPage ? 'active' : ''}">
                    <button type="button" class="page-link dispatch-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Dispatch orders pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon dispatch-page-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${currentPage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon dispatch-page-btn" data-page="${currentPage + 1}" ${currentPage >= lastPage ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">east</i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                ` : ''}
            </div>
        `;
    }

    function setLoading(isLoading) {
        state.loading = isLoading;
        if (!isLoading) {
            return;
        }

        tableBody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Loading dispatch orders...
                </td>
            </tr>
        `;
    }

    async function loadOrders(options = {}) {
        if (!listUrl) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-danger py-5">
                        Dispatch orders list is not configured.
                    </td>
                </tr>
            `;
            return;
        }

        if (options.page) {
            state.page = options.page;
        }

        clearAlert();
        syncSummaryCards();
        setLoading(true);

        try {
            const params = buildQueryParams();
            const response = await fetch(`${listUrl}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload?.message || 'Unable to load dispatch orders.');
            }

            const orders = Array.isArray(payload.data) ? payload.data : [];
            state.meta = payload.meta || null;
            state.page = state.meta?.current_page || state.page;

            fillCourierSelect(state.meta?.filter_options?.couriers || [], state.filters.courier_id);
            updateSummaryCounts(state.meta?.summary_counts);
            renderRows(orders);
            renderPagination(state.meta);
        } catch (error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-danger py-5">
                        ${escapeHtml(error.message || 'Unable to load dispatch orders.')}
                    </td>
                </tr>
            `;
            if (paginationHost) {
                paginationHost.innerHTML = '';
            }
            if (resultsMeta) {
                resultsMeta.textContent = '';
            }
        } finally {
            state.loading = false;
        }
    }

    function resetFilters({ keepStatus = false } = {}) {
        if (searchInput) {
            searchInput.value = '';
        }
        if (statusSelect && !keepStatus) {
            statusSelect.value = 'DISPATCHED';
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
        loadOrders();
    }

    function normalizeScanValue(value) {
        return String(value || '').trim();
    }

    function focusReturnScanner() {
        if (!scanInput || scanInput.disabled || !canReturn) {
            return;
        }
        scanInput.focus();
        scanInput.select();
    }

    function setReturnScanBusy(busy) {
        state.scanningReturn = Boolean(busy);
        if (scanInput) {
            scanInput.disabled = Boolean(busy);
        }
        if (scanBusy) {
            scanBusy.classList.toggle('d-none', !busy);
        }
        if (scanHint) {
            scanHint.textContent = busy
                ? 'Processing return…'
                : 'Scan a courier waybill to mark the order as returned.';
        }
    }

    function showScanFeedback(type, html) {
        if (!scanResult) {
            return;
        }
        if (scanResultPlaceholder) {
            scanResultPlaceholder.classList.add('d-none');
        }
        scanResult.className = `alert alert-${type} mb-0`;
        scanResult.innerHTML = html;
        scanResult.classList.remove('d-none');
    }

    function clearScanFeedback({ clearInput = false } = {}) {
        if (clearInput && scanInput) {
            scanInput.value = '';
        }
        if (scanResult) {
            scanResult.classList.add('d-none');
            scanResult.className = 'alert alert-light border mb-0 d-none';
            scanResult.innerHTML = '';
        }
        if (scanResultPlaceholder) {
            scanResultPlaceholder.classList.remove('d-none');
        }
        if (scanHint && !state.scanningReturn) {
            scanHint.textContent = 'Scan a courier waybill to mark the order as returned.';
        }
    }

    function friendlyScanError(payload, fallback) {
        if (payload?.message) {
            return String(payload.message);
        }
        const firstError = payload?.errors
            ? Object.values(payload.errors).flat().find(Boolean)
            : null;
        return firstError ? String(firstError) : fallback;
    }

    function syncDamagedCheckAppearance() {
        if (!damagedCheckWrap || !damagedCheckbox) {
            return;
        }
        damagedCheckWrap.classList.toggle('is-checked', Boolean(damagedCheckbox.checked));
    }

    async function processReturnScan(waybill) {
        const trackingNumber = normalizeScanValue(waybill);
        if (!trackingNumber || state.scanningReturn || !canReturn) {
            return;
        }

        if (!scanReturnUrl) {
            showScanFeedback('danger', '<strong>Return scan is not configured.</strong>');
            return;
        }

        // Capture at submit time so a mid-flight checkbox change cannot leak into this request.
        const isDamaged = Boolean(damagedCheckbox?.checked);

        setReturnScanBusy(true);
        showScanFeedback(
            'info',
            `<strong>Processing…</strong><div class="fs-13 mt-1">Waybill: ${escapeHtml(trackingNumber)}</div>`
        );

        try {
            const response = await fetch(scanReturnUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    tracking_number: trackingNumber,
                    is_damaged: isDamaged,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(friendlyScanError(payload, 'Unable to process returned order.'));
            }

            const data = payload.data || {};
            const returnedDamaged = Boolean(data.is_damaged);
            const stockRestored = Boolean(data.stock_restored);

            if (returnedDamaged && stockRestored) {
                throw new Error('Damaged return reported stock restoration unexpectedly.');
            }
            if (!returnedDamaged && !stockRestored) {
                throw new Error('Return completed but stock restoration was not confirmed.');
            }

            const orderNumber = data.order_number || data.id || '—';
            const reasonLabel = data.return_reason || '—';
            const debt = data.debt_obligation || {};
            let debtLine = '';
            if (debt.skipped) {
                debtLine = `<div class="fs-13 mt-1">Return fine not recorded (${escapeHtml(debt.skip_reason || 'skipped')}).</div>`;
            } else if (debt.original_amount) {
                debtLine = `<div class="fs-13 mt-1">Return fine: ${escapeHtml(debt.currency_code || '')} ${escapeHtml(debt.original_amount)}</div>`;
            }

            const resultBody = returnedDamaged
                ? `
                    <div class="fw-semibold mb-1">Order #${escapeHtml(orderNumber)} returned successfully — marked damaged</div>
                    <div class="fs-13">Waybill: ${escapeHtml(data.tracking_number || trackingNumber)}</div>
                    <div class="fs-13 mt-1">Classification: ${escapeHtml(reasonLabel)}</div>
                    <div class="fs-13 mt-1">Stock was not restored.</div>
                    ${debtLine}
                `
                : `
                    <div class="fw-semibold mb-1">Order #${escapeHtml(orderNumber)} returned successfully</div>
                    <div class="fs-13">Waybill: ${escapeHtml(data.tracking_number || trackingNumber)}</div>
                    <div class="fs-13 mt-1">Classification: ${escapeHtml(reasonLabel)}</div>
                    <div class="fs-13 mt-1">Stock restored.</div>
                    ${debtLine}
                `;

            showScanFeedback('success', resultBody);

            if (scanInput) {
                scanInput.value = '';
            }
            // Intentionally keep damaged checkbox state for batch scanning.

            await loadOrders({ page: state.page });
        } catch (error) {
            showScanFeedback(
                'danger',
                `
                    <strong>${escapeHtml(error.message || 'Unable to process returned order.')}</strong>
                    <div class="fs-13 mt-1">Waybill: ${escapeHtml(trackingNumber)}</div>
                `
            );
            if (scanInput) {
                scanInput.value = '';
            }
        } finally {
            setReturnScanBusy(false);
            focusReturnScanner();
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            readFiltersFromForm();
            state.page = 1;
            loadOrders();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => resetFilters());
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => resetFilters());
    }

    [statusSelect, courierSelect, dateFromInput, dateToInput].forEach((el) => {
        if (!el) {
            return;
        }
        el.addEventListener('change', () => {
            readFiltersFromForm();
            state.page = 1;
            loadOrders();
        });
    });

    summaryCards.forEach((card) => {
        card.addEventListener('click', () => {
            const nextStatus = (card.dataset.statusFilter || '').toUpperCase();
            if (!statusSelect) {
                return;
            }

            if (statusSelect.value === nextStatus) {
                statusSelect.value = '';
            } else {
                statusSelect.value = nextStatus;
            }

            readFiltersFromForm();
            state.page = 1;
            loadOrders();
        });
    });

    if (paginationHost) {
        paginationHost.addEventListener('click', (event) => {
            const btn = event.target.closest('.dispatch-page-btn');
            if (!btn || btn.disabled || state.loading) {
                return;
            }
            const nextPage = parseInt(btn.dataset.page || '1', 10);
            if (!Number.isFinite(nextPage) || nextPage < 1) {
                return;
            }
            loadOrders({ page: nextPage });
        });
    }

    if (scanInput && canReturn) {
        scanInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== 'Tab') {
                return;
            }
            event.preventDefault();
            const waybill = normalizeScanValue(scanInput.value);
            if (!waybill || state.scanningReturn) {
                return;
            }
            processReturnScan(waybill);
        });
    }

    if (damagedCheckbox) {
        damagedCheckbox.addEventListener('change', syncDamagedCheckAppearance);
        syncDamagedCheckAppearance();
    }

    if (statusSelect && !statusSelect.value) {
        statusSelect.value = 'DISPATCHED';
    }

    readFiltersFromForm();
    loadOrders();
    focusReturnScanner();
})();
</script>
