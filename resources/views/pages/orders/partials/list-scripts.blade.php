<script>
(function () {
    const page = document.getElementById('supplierOrdersPage');
    if (!page) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const listUrls = {
        fresh: page.dataset.listUrlFresh || '',
        'out-of-stock': page.dataset.listUrlOutOfStock || '',
    };
    const emptyMessages = {
        fresh: page.dataset.emptyMessageFresh || 'No fresh orders found.',
        'out-of-stock': page.dataset.emptyMessageOutOfStock || 'No out-of-stock orders found.',
        exchange: page.dataset.emptyMessageExchange || 'Exchange fulfillment is not available yet.',
    };
    const sendToPrintUrl = page.dataset.sendToPrintUrl || '';
    const cancelUrlTemplate = page.dataset.cancelUrlTemplate || '';
    const showUrlTemplate = page.dataset.showUrlTemplate || '';
    const permissionCanSendToPrint = page.dataset.canSendToPrint === '1';
    const canCancel = page.dataset.canCancel === '1';
    const maxBatchRaw = parseInt(page.dataset.maxBatch || '0', 10);
    const maxBatch = Number.isFinite(maxBatchRaw) && maxBatchRaw > 0 ? maxBatchRaw : 0;

    const alertHost = document.getElementById('ordersAlertHost');
    const searchLocationHost = document.getElementById('ordersSearchLocationHost');
    const tableBody = document.getElementById('ordersTableBody');
    const paginationHost = document.getElementById('ordersPaginationHost');
    const filterForm = document.getElementById('ordersFilterForm');
    const filtersPanel = document.getElementById('ordersFiltersPanel');
    const productSelect = document.getElementById('filterProduct');
    const courierSelect = document.getElementById('filterCourier');
    const selectAll = document.getElementById('ordersSelectAll');
    const selectedCountEl = document.getElementById('ordersSelectedCount');
    const sendToPrintBtn = document.getElementById('ordersSendToPrintBtn');
    const sendCountInput = document.getElementById('ordersSendCountInput');
    const sendCountBtn = document.getElementById('ordersSendCountBtn');
    const sendEligibleCountEl = document.getElementById('ordersSendEligibleCount');
    const countSendBar = document.getElementById('ordersCountSendBar');
    const clearFiltersBtn = document.getElementById('ordersClearFilters');
    const resetFiltersBtn = document.getElementById('ordersResetFilters');
    const bulkActionsBar = document.getElementById('ordersBulkActionsBar');
    const selectCol = document.querySelector('.orders-select-col');
    const listPanel = document.getElementById('ordersListPanel');
    const exchangePlaceholder = document.getElementById('ordersExchangePlaceholder');
    const workspaceTitle = document.getElementById('ordersWorkspaceTitle');
    const resultsMeta = document.getElementById('ordersResultsMeta');
    const modeButtons = Array.from(document.querySelectorAll('.orders-mode-btn'));
    const modeTitles = {
        fresh: 'Fresh Orders',
        exchange: 'Exchange Orders',
        'out-of-stock': 'Out of Stock Orders',
    };

    const cancelModalEl = document.getElementById('orderCancelModal');
    const cancelModalCopy = document.getElementById('orderCancelModalCopy');
    const cancelReasonInput = document.getElementById('orderCancelReason');
    const cancelConfirmBtn = document.getElementById('orderCancelConfirmBtn');
    const cancelModal = cancelModalEl && window.bootstrap
        ? new bootstrap.Modal(cancelModalEl)
        : null;

    const state = {
        mode: page.dataset.mode || 'fresh',
        page: 1,
        perPage: 25,
        filters: {
            search: '',
            product_id: '',
            courier_id: '',
            date_from: '',
            date_to: '',
        },
        selectedIds: new Set(),
        ordersById: {},
        meta: null,
        loading: false,
        submitting: false,
        pendingCancelOrderId: null,
        pendingCancelOrderUuid: null,
    };

    function canSendToPrint() {
        return permissionCanSendToPrint && state.mode === 'fresh';
    }

    function emptyMessage() {
        return emptyMessages[state.mode] || 'No orders found.';
    }

    function listUrl() {
        return listUrls[state.mode] || '';
    }

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

        const className = type === 'success'
            ? 'alert alert-success'
            : (type === 'warning' ? 'alert alert-warning' : 'alert alert-danger');

        alertHost.className = className;
        alertHost.textContent = message;
        alertHost.classList.remove('d-none');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearAlert() {
        if (!alertHost) {
            return;
        }
        alertHost.className = 'alert d-none';
        alertHost.textContent = '';
    }

    function clearSearchLocation() {
        if (!searchLocationHost) {
            return;
        }
        searchLocationHost.className = 'alert alert-info d-none mb-3';
        searchLocationHost.textContent = '';
    }

    function showSearchLocation(message) {
        if (!searchLocationHost || !message) {
            clearSearchLocation();
            return;
        }

        searchLocationHost.className = 'alert alert-info mb-3';
        searchLocationHost.textContent = message;
    }

    function updateEligibleSendCount(total) {
        if (!sendEligibleCountEl) {
            return;
        }

        const count = Number.isFinite(Number(total)) ? Number(total) : 0;
        sendEligibleCountEl.textContent = count === 1 ? '1 order' : `${count} orders`;
    }

    function columnCount() {
        let count = 10;
        if (canSendToPrint()) {
            count += 1;
        }
        return count;
    }

    function orderShowUrl(order) {
        const uuid = order.uuid || '';
        if (!showUrlTemplate || !uuid) {
            return '';
        }
        return showUrlTemplate.replace('__ORDER_UUID__', encodeURIComponent(uuid));
    }

    function orderCancelUrl(order) {
        const uuid = order.uuid || state.pendingCancelOrderUuid || '';
        if (!cancelUrlTemplate || !uuid) {
            return '';
        }
        return cancelUrlTemplate.replace('__ORDER_UUID__', encodeURIComponent(uuid));
    }

    function syncModeCounts(counts) {
        if (!counts || typeof counts !== 'object') {
            return;
        }

        modeButtons.forEach((button) => {
            const mode = button.dataset.mode;
            const countEl = button.querySelector('[data-mode-count]');
            if (!countEl || mode === undefined) {
                return;
            }

            const value = counts[mode];
            if (value === undefined || value === null) {
                return;
            }

            countEl.textContent = String(Number(value) || 0);
        });
    }

    function syncModeUi() {
        modeButtons.forEach((button) => {
            const isActive = button.dataset.mode === state.mode;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (workspaceTitle) {
            workspaceTitle.textContent = modeTitles[state.mode] || 'New Orders';
        }

        const isExchange = state.mode === 'exchange';
        if (listPanel) {
            listPanel.classList.toggle('d-none', isExchange);
        }
        if (exchangePlaceholder) {
            exchangePlaceholder.classList.toggle('d-none', !isExchange);
        }
        if (filtersPanel) {
            filtersPanel.classList.toggle('d-none', isExchange);
        }

        if (selectCol) {
            selectCol.classList.toggle('d-none', !canSendToPrint());
        }
        if (bulkActionsBar) {
            bulkActionsBar.classList.toggle('hidden', !canSendToPrint());
            bulkActionsBar.classList.toggle('d-none', !canSendToPrint());
        }
        if (countSendBar) {
            countSendBar.classList.toggle('d-none', !canSendToPrint());
        }

        page.dataset.mode = state.mode;
    }

    function syncModeQueryParam() {
        const url = new URL(window.location.href);
        if (state.mode === 'fresh') {
            url.searchParams.delete('mode');
        } else {
            url.searchParams.set('mode', state.mode);
        }
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
    }

    function readFiltersFromForm() {
        if (!filterForm) {
            return;
        }

        const formData = new FormData(filterForm);
        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            product_id: (formData.get('product_id') || '').toString(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function buildQueryParams() {
        const params = new URLSearchParams();
        params.set('page', String(state.page));
        params.set('per_page', String(state.perPage));

        Object.entries(state.filters).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                params.set(key, value);
            }
        });

        return params;
    }

    function fillSelect(select, options, selectedValue, allLabel) {
        if (!select) {
            return;
        }

        const previous = selectedValue ?? select.value;
        select.innerHTML = '';

        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = allLabel;
        select.appendChild(allOption);

        (options || []).forEach((option) => {
            const el = document.createElement('option');
            el.value = String(option.id);
            el.textContent = option.name;
            select.appendChild(el);
        });

        if (previous && !Array.from(select.options).some((opt) => opt.value === previous)) {
            const retained = document.createElement('option');
            retained.value = previous;
            retained.textContent = 'Selected filter';
            select.appendChild(retained);
        }

        select.value = previous || '';
    }

    function formatPayable(order) {
        const amount = order.customer_payable_amount;
        if (amount === null || amount === undefined || amount === '') {
            return '—';
        }

        const code = order.currency_code ? `${escapeHtml(order.currency_code)} ` : '';
        return `${code}${escapeHtml(amount)}`;
    }

    function formatItems(order) {
        const items = Array.isArray(order.items) ? order.items : [];
        if (items.length === 0) {
            return '—';
        }

        return items.map((item) => {
            const name = escapeHtml(item.product_name || 'Product');
            const variant = item.variant ? ` (${escapeHtml(item.variant)})` : '';
            return `<div class="fs-14">${name}${variant}</div>`;
        }).join('');
    }

    function formatQuantity(order) {
        const items = Array.isArray(order.items) ? order.items : [];
        const total = items.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
        return total > 0 ? String(total) : '—';
    }

    function statusBadge(order) {
        const status = (order.fulfillment_status || '').toString();
        if (status === 'OUT_OF_STOCK' || state.mode === 'out-of-stock') {
            return '<span class="badge-status is-out-of-stock">Out of Stock</span>';
        }

        if (status === 'FRESH') {
            let badge = '<span class="badge-status is-fresh">Fresh</span>';
            if (order.is_out_of_stock_by_fifo) {
                badge += ' <span class="badge-status is-fifo">FIFO shortage</span>';
            }
            return badge;
        }

        return `<span class="badge-status">${escapeHtml(status || '—')}</span>`;
    }

    function syncSelectionUi() {
        const count = state.selectedIds.size;

        if (selectedCountEl) {
            selectedCountEl.textContent = String(count);
        }

        if (sendToPrintBtn) {
            sendToPrintBtn.disabled = !canSendToPrint() || count === 0 || state.submitting;
        }

        if (selectAll) {
            const checkboxes = tableBody.querySelectorAll('.order-select-checkbox');
            const total = checkboxes.length;
            const selectedOnPage = Array.from(checkboxes).filter((box) => box.checked).length;
            selectAll.checked = total > 0 && selectedOnPage === total;
            selectAll.indeterminate = selectedOnPage > 0 && selectedOnPage < total;
        }
    }

    function pruneSelectionToVisible(orders) {
        const visibleIds = new Set(orders.map((order) => String(order.id)));
        Array.from(state.selectedIds).forEach((id) => {
            if (!visibleIds.has(String(id)) && !state.ordersById[id]) {
                // Keep selections across pages only while still tracked.
            }
        });
    }

    function renderRows(orders) {
        state.ordersById = {};
        orders.forEach((order) => {
            state.ordersById[String(order.id)] = order;
        });

        if (!orders.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${columnCount()}" class="text-center text-muted py-5">
                        ${escapeHtml(emptyMessage())}
                    </td>
                </tr>
            `;
            syncSelectionUi();
            return;
        }

        tableBody.innerHTML = orders.map((order) => {
            const id = String(order.id);
            const uuid = String(order.uuid || '');
            const checked = state.selectedIds.has(id) ? 'checked' : '';
            const selectCell = canSendToPrint()
                ? `<td>
                        <div class="form-check">
                            <input class="form-check-input order-select-checkbox" type="checkbox"
                                value="${escapeHtml(id)}" ${checked} aria-label="Select order ${escapeHtml(order.order_number)}">
                        </div>
                    </td>`
                : '';

            const showUrl = orderShowUrl(order);
            const viewBtn = showUrl
                ? `<a href="${escapeHtml(showUrl)}" class="btn btn-sm btn-light border order-view-btn">View Order</a>`
                : '';

            const cancelBtn = canCancel
                ? `<button type="button"
                        class="btn btn-sm btn-outline-danger order-cancel-btn"
                        data-order-id="${escapeHtml(id)}"
                        data-order-uuid="${escapeHtml(uuid)}"
                        data-order-number="${escapeHtml(order.order_number || '')}">
                        Cancel
                    </button>`
                : '';

            const actionCell = `
                <td class="text-end">
                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                        ${viewBtn}
                        ${cancelBtn}
                    </div>
                </td>
            `;

            return `
                <tr data-order-id="${escapeHtml(id)}">
                    ${selectCell}
                    <td>
                        <div class="order-number">${escapeHtml(order.order_number || '—')}</div>
                    </td>
                    <td>${escapeHtml(order.order_date || '—')}</td>
                    <td>${escapeHtml(order.customer_name || '—')}</td>
                    <td>
                        <div>${escapeHtml(order.primary_phone || '—')}</div>
                        ${order.secondary_phone ? `<div class="order-sub">${escapeHtml(order.secondary_phone)}</div>` : ''}
                    </td>
                    <td>${formatItems(order)}</td>
                    <td>${escapeHtml(formatQuantity(order))}</td>
                    <td>${escapeHtml(order.courier || '—')}</td>
                    <td>${formatPayable(order)}</td>
                    <td>${statusBadge(order)}</td>
                    ${actionCell}
                </tr>
            `;
        }).join('');

        pruneSelectionToVisible(orders);
        syncSelectionUi();
    }

    function renderPagination(meta) {
        if (!paginationHost || !meta) {
            return;
        }

        const total = meta.total || 0;
        const currentPage = meta.current_page || 1;
        const lastPage = meta.last_page || 1;
        const perPage = meta.per_page || state.perPage;

        if (total === 0) {
            paginationHost.innerHTML = '';
            return;
        }

        const from = ((currentPage - 1) * perPage) + 1;
        const to = Math.min(currentPage * perPage, total);

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
                    <button type="button" class="page-link orders-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Orders pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon orders-page-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${currentPage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon orders-page-btn" data-page="${currentPage + 1}" ${currentPage >= lastPage ? 'disabled' : ''}>
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
        if (isLoading) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${columnCount()}" class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        Loading orders...
                    </td>
                </tr>
            `;
        }
    }

    async function loadOrders() {
        clearAlert();
        clearSearchLocation();
        syncModeUi();

        if (state.mode === 'exchange') {
            if (paginationHost) {
                paginationHost.innerHTML = '';
            }
            updateEligibleSendCount(0);
            state.loading = false;
            return;
        }

        const url = listUrl();
        if (!url) {
            showAlert('error', 'Unable to load orders for this filter.');
            return;
        }

        setLoading(true);

        try {
            const response = await fetch(`${url}?${buildQueryParams().toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'Unable to load orders.');
            }

            const orders = Array.isArray(payload.data) ? payload.data : [];
            state.meta = payload.meta || null;
            syncModeCounts(state.meta?.mode_counts);

            fillSelect(
                productSelect,
                state.meta?.filter_options?.products || [],
                state.filters.product_id,
                'All products'
            );
            fillSelect(
                courierSelect,
                state.meta?.filter_options?.couriers || [],
                state.filters.courier_id,
                'All couriers'
            );

            renderRows(orders);
            renderPagination(state.meta);

            const total = state.meta?.total || 0;
            updateEligibleSendCount(canSendToPrint() ? total : 0);

            if (resultsMeta && state.meta) {
                resultsMeta.textContent = total === 1 ? '1 order' : `${total} orders`;
            } else if (resultsMeta) {
                resultsMeta.textContent = '';
            }

            if (state.meta?.search_location?.message) {
                showSearchLocation(state.meta.search_location.message);
            } else {
                clearSearchLocation();
            }
        } catch (error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${columnCount()}" class="text-center text-danger py-5">
                        ${escapeHtml(error.message || 'Unable to load orders.')}
                    </td>
                </tr>
            `;
            paginationHost.innerHTML = '';
            updateEligibleSendCount(0);
            showAlert('error', error.message || 'Unable to load orders.');
        } finally {
            state.loading = false;
        }
    }

    function setMode(nextMode) {
        if (!['fresh', 'exchange', 'out-of-stock'].includes(nextMode) || nextMode === state.mode) {
            return;
        }

        state.mode = nextMode;
        state.page = 1;
        state.selectedIds.clear();
        syncModeQueryParam();
        loadOrders();
    }

    function trySelectOrder(orderId, checked) {
        const id = String(orderId);

        if (!checked) {
            state.selectedIds.delete(id);
            syncSelectionUi();
            return true;
        }

        if (state.selectedIds.has(id)) {
            syncSelectionUi();
            return true;
        }

        if (maxBatch > 0 && state.selectedIds.size >= maxBatch) {
            showAlert('warning', `You can select a maximum of ${maxBatch} orders for Send to Print.`);
            return false;
        }

        state.selectedIds.add(id);
        clearAlert();
        syncSelectionUi();
        return true;
    }

    async function postSendToPrint(body) {
        const response = await fetch(sendToPrintUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const detail = payload.message
                || (payload.errors && typeof payload.errors === 'object'
                    ? Object.values(payload.errors).flat().join(' ')
                    : null)
                || 'Unable to send orders to print.';
            throw new Error(detail);
        }

        return payload;
    }

    async function sendToPrint() {
        if (!canSendToPrint() || !sendToPrintUrl || state.submitting) {
            return;
        }

        const orderIds = Array.from(state.selectedIds).map((id) => parseInt(id, 10)).filter(Boolean);

        if (orderIds.length === 0) {
            showAlert('warning', 'Select at least one order to send to print.');
            return;
        }

        if (maxBatch > 0 && orderIds.length > maxBatch) {
            showAlert('warning', `You can select a maximum of ${maxBatch} orders for Send to Print.`);
            return;
        }

        state.submitting = true;
        syncSelectionUi();
        if (sendToPrintBtn) {
            sendToPrintBtn.disabled = true;
            sendToPrintBtn.textContent = 'Sending...';
        }

        try {
            const payload = await postSendToPrint({
                mode: 'manual',
                order_ids: orderIds,
            });

            state.selectedIds.clear();
            // Refresh first — loadOrders() clears alerts; show result after.
            await loadOrders();
            showAlert('success', payload.message || 'Orders sent to print.');
        } catch (error) {
            await loadOrders();
            showAlert('error', error.message || 'Unable to send orders to print.');
        } finally {
            state.submitting = false;
            if (sendToPrintBtn) {
                sendToPrintBtn.textContent = 'Send to Print';
            }
            syncSelectionUi();
        }
    }

    async function sendToPrintByCount() {
        if (!canSendToPrint() || !sendToPrintUrl || state.submitting) {
            return;
        }

        const count = parseInt(sendCountInput ? sendCountInput.value : '', 10);

        if (!Number.isFinite(count) || count < 1) {
            showAlert('warning', 'Enter a count of at least 1 to send orders to print.');
            if (sendCountInput) {
                sendCountInput.focus();
            }
            return;
        }

        state.submitting = true;
        syncSelectionUi();
        if (sendCountBtn) {
            sendCountBtn.disabled = true;
            sendCountBtn.textContent = 'Sending...';
        }

        try {
            const payload = await postSendToPrint({
                mode: 'count',
                count: count,
            });

            state.selectedIds.clear();
            if (sendCountInput) {
                sendCountInput.value = '';
            }
            const sent = Array.isArray(payload.data) ? payload.data.length : count;
            // Refresh first — loadOrders() clears alerts; show result after.
            await loadOrders();
            showAlert('success', payload.message || `Sent ${sent} order(s) to print.`);
        } catch (error) {
            await loadOrders();
            showAlert('error', error.message || 'Unable to send orders to print.');
        } finally {
            state.submitting = false;
            if (sendCountBtn) {
                sendCountBtn.disabled = false;
                sendCountBtn.textContent = 'Send to Print';
            }
            syncSelectionUi();
        }
    }

    function openCancelModal(orderId, orderNumber, orderUuid) {
        if (!canCancel || !cancelModal) {
            return;
        }

        state.pendingCancelOrderId = orderId;
        state.pendingCancelOrderUuid = orderUuid || '';
        if (cancelReasonInput) {
            cancelReasonInput.value = '';
        }
        if (cancelModalCopy) {
            cancelModalCopy.innerHTML = `Cancel order <strong>${escapeHtml(orderNumber || orderId)}</strong> from the supplier fulfillment queue?`;
        }
        cancelModal.show();
    }

    async function confirmCancel() {
        if (!canCancel || !state.pendingCancelOrderId || state.submitting) {
            return;
        }

        const orderId = state.pendingCancelOrderId;
        const url = orderCancelUrl({ uuid: state.pendingCancelOrderUuid });

        if (!url) {
            showAlert('error', 'Unable to cancel order.');
            return;
        }

        state.submitting = true;
        if (cancelConfirmBtn) {
            cancelConfirmBtn.disabled = true;
            cancelConfirmBtn.textContent = 'Cancelling...';
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    reason: cancelReasonInput ? cancelReasonInput.value.trim() : '',
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const detail = payload.message
                    || (payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().join(' ')
                        : null)
                    || 'Unable to cancel order.';
                throw new Error(detail);
            }

            state.selectedIds.delete(String(orderId));
            if (cancelModal) {
                cancelModal.hide();
            }
            // Refresh first — loadOrders() clears alerts; show result after.
            await loadOrders();
            showAlert('success', payload.message || 'Order cancelled.');
        } catch (error) {
            showAlert('error', error.message || 'Unable to cancel order.');
        } finally {
            state.submitting = false;
            state.pendingCancelOrderId = null;
            state.pendingCancelOrderUuid = null;
            if (cancelConfirmBtn) {
                cancelConfirmBtn.disabled = false;
                cancelConfirmBtn.textContent = 'Cancel order';
            }
            syncSelectionUi();
        }
    }

    modeButtons.forEach((button) => {
        button.addEventListener('click', function () {
            setMode(button.dataset.mode);
        });
    });

    function clearFilterState() {
        if (filterForm) {
            filterForm.reset();
        }
        state.filters = {
            search: '',
            product_id: '',
            courier_id: '',
            date_from: '',
            date_to: '',
        };
        state.page = 1;
        loadOrders();
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            readFiltersFromForm();
            state.page = 1;
            loadOrders();
        });
    }

    ['filterCourier', 'filterProduct', 'filterDateFrom', 'filterDateTo'].forEach(function (id) {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }

        el.addEventListener('change', function () {
            readFiltersFromForm();
            state.page = 1;
            state.selectedIds.clear();
            loadOrders();
        });
    });

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearFilterState);
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', clearFilterState);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const checkboxes = Array.from(tableBody.querySelectorAll('.order-select-checkbox'));
            if (selectAll.checked) {
                for (const checkbox of checkboxes) {
                    const accepted = trySelectOrder(checkbox.value, true);
                    checkbox.checked = accepted || state.selectedIds.has(checkbox.value);
                    if (!accepted && !state.selectedIds.has(checkbox.value)) {
                        selectAll.checked = false;
                        break;
                    }
                }
            } else {
                checkboxes.forEach((checkbox) => {
                    trySelectOrder(checkbox.value, false);
                    checkbox.checked = false;
                });
            }
            syncSelectionUi();
        });
    }

    tableBody.addEventListener('change', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('order-select-checkbox')) {
            return;
        }

        const accepted = trySelectOrder(target.value, target.checked);
        if (!accepted) {
            target.checked = false;
        }
    });

    tableBody.addEventListener('click', function (event) {
        const button = event.target.closest('.order-cancel-btn');
        if (!button) {
            return;
        }

        openCancelModal(button.dataset.orderId, button.dataset.orderNumber, button.dataset.orderUuid);
    });

    if (paginationHost) {
        paginationHost.addEventListener('click', function (event) {
            const button = event.target.closest('.orders-page-btn');
            if (!button || button.disabled) {
                return;
            }

            const nextPage = parseInt(button.dataset.page, 10);
            if (!nextPage || nextPage === state.page) {
                return;
            }

            state.page = nextPage;
            loadOrders();
        });
    }

    if (sendToPrintBtn) {
        sendToPrintBtn.addEventListener('click', sendToPrint);
    }

    if (sendCountBtn) {
        sendCountBtn.addEventListener('click', sendToPrintByCount);
    }

    if (sendCountInput) {
        sendCountInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendToPrintByCount();
            }
        });
    }

    if (cancelConfirmBtn) {
        cancelConfirmBtn.addEventListener('click', confirmCancel);
    }

    loadOrders();
})();
</script>
