<script>
(function () {
    const page = document.getElementById('supplierPackagingOrdersPage');
    if (!page) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const listUrl = page.dataset.listUrl;
    const scanOrderUrl = page.dataset.scanOrderUrl || '';
    const scanProductUrl = page.dataset.scanProductUrl || '';
    const completeUrl = page.dataset.completeUrl || '';
    const canPack = page.dataset.canPack === '1';
    const canPrint = page.dataset.canPrint === '1';
    const reprintUrl = page.dataset.reprintUrl || '';
    const showActions = canPack || canPrint;
    const completeBarcode = (page.dataset.completeBarcode || 'FCP').trim();
    let completeBarcodeAliases = [completeBarcode];
    try {
        const parsedAliases = JSON.parse(page.dataset.completeBarcodeAliases || '[]');
        if (Array.isArray(parsedAliases) && parsedAliases.length) {
            completeBarcodeAliases = parsedAliases.map((value) => String(value || '').trim()).filter(Boolean);
        }
    } catch (error) {
        completeBarcodeAliases = [completeBarcode];
    }
    if (!completeBarcodeAliases.includes(completeBarcode)) {
        completeBarcodeAliases.unshift(completeBarcode);
    }
    const emptyMessage = page.dataset.emptyMessage || 'No orders waiting for packaging.';

    const alertHost = document.getElementById('packagingOrdersAlertHost');
    const tableBody = document.getElementById('packagingOrdersTableBody');
    const paginationHost = document.getElementById('packagingOrdersPaginationHost');
    const filterForm = document.getElementById('packagingOrdersFilterForm');
    const productSelect = document.getElementById('packagingFilterProduct');
    const courierSelect = document.getElementById('packagingFilterCourier');
    const clearFiltersBtn = document.getElementById('packagingOrdersClearFilters');
    const resetFiltersBtn = document.getElementById('packagingOrdersResetFilters');
    const resultsMeta = document.getElementById('packagingOrdersResultsMeta');

    const orderScanInput = document.getElementById('packagingOrderScanInput');
    const orderScanBusy = document.getElementById('packagingOrderScanBusy');
    const productScanInput = document.getElementById('packagingProductScanInput');
    const productScanBusy = document.getElementById('packagingProductScanBusy');
    const completeScanInput = document.getElementById('packagingCompleteScanInput');
    const completeBtn = document.getElementById('packagingCompleteBtn');
    const completeBarcodeDownload = document.getElementById('packagingCompleteBarcodeDownload');

    const modalEl = document.getElementById('packagingWorkspaceModal');
    const modalAlert = document.getElementById('packagingWorkspaceAlert');
    const modalTitle = document.getElementById('packagingWorkspaceModalTitle');
    const modalInvoice = document.getElementById('packagingWorkspaceInvoice');
    const modalCustomer = document.getElementById('packagingWorkspaceCustomer');
    const modalContact = document.getElementById('packagingWorkspaceContact');
    const modalCourier = document.getElementById('packagingWorkspaceCourier');
    const modalWaybill = document.getElementById('packagingWorkspaceWaybill');
    const modalAddress = document.getElementById('packagingWorkspaceAddress');
    const progressText = document.getElementById('packagingWorkspaceProgressText');
    const progressPct = document.getElementById('packagingWorkspaceProgressPct');
    const progressBar = document.getElementById('packagingWorkspaceProgressBar');
    const itemsBody = document.getElementById('packagingWorkspaceItemsBody');
    const completeBarcodeSvgHost = document.getElementById('packagingCompleteBarcodeSvgHost');
    const completeBarcodeValue = document.getElementById('packagingCompleteBarcodeValue');

    const workspaceModal = modalEl && window.bootstrap
        ? new bootstrap.Modal(modalEl)
        : null;

    const state = {
        page: 1,
        perPage: 25,
        filters: {
            search: '',
            product_id: '',
            courier_id: '',
        },
        meta: null,
        loading: false,
        scanningOrder: false,
        scanningProduct: false,
        completing: false,
        reprinting: false,
        activeOrder: null,
    };

    const ERROR_MESSAGES = {
        SUPPLIER_NOT_AUTHORIZED: 'Order not found for this supplier.',
        ORDER_NOT_PACKAGING: 'Order is not ready for packaging.',
        WRONG_PRODUCT_BARCODE: 'Wrong product for this order.',
        OVER_SCAN: 'This item is already fully packed.',
        PACKAGE_INCOMPLETE: 'Package is incomplete. Keep scanning remaining items.',
        ALREADY_DISPATCHED: 'This package has already been completed.',
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
        alertHost.className = 'd-none';
        alertHost.textContent = '';
    }

    function showModalAlert(type, message) {
        if (!modalAlert) {
            return;
        }

        const className = type === 'success'
            ? 'alert alert-success mb-3'
            : (type === 'warning' ? 'alert alert-warning mb-3' : 'alert alert-danger mb-3');

        modalAlert.className = className;
        modalAlert.textContent = message;
        modalAlert.classList.remove('d-none');
    }

    function clearModalAlert() {
        if (!modalAlert) {
            return;
        }
        modalAlert.className = 'd-none mb-3';
        modalAlert.textContent = '';
    }

    function friendlyError(payload, fallback) {
        const code = payload?.code || '';
        if (code && ERROR_MESSAGES[code]) {
            return ERROR_MESSAGES[code];
        }

        if (payload?.message && typeof payload.message === 'string') {
            return payload.message;
        }

        return fallback || 'Something went wrong. Try again.';
    }

    function columnCount() {
        return showActions ? 10 : 9;
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
        };
    }

    function buildQueryParams(overrides) {
        const params = new URLSearchParams();
        const pageNum = overrides?.page ?? state.page;
        const perPage = overrides?.per_page ?? state.perPage;

        params.set('page', String(pageNum));
        params.set('per_page', String(perPage));

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

    function packingTotals(order) {
        const ordered = parseInt(order.ordered_units, 10);
        const packed = parseInt(order.packed_units, 10);

        if (!Number.isNaN(ordered) && !Number.isNaN(packed)) {
            return { ordered, packed, remaining: Math.max(0, ordered - packed) };
        }

        const items = Array.isArray(order.items) ? order.items : [];
        const totals = items.reduce((acc, item) => {
            acc.ordered += parseInt(item.ordered_quantity, 10) || 0;
            acc.packed += parseInt(item.packed_quantity, 10) || 0;
            return acc;
        }, { ordered: 0, packed: 0 });

        totals.remaining = Math.max(0, totals.ordered - totals.packed);
        return totals;
    }

    function progressLabel(order) {
        const totals = packingTotals(order);
        return `${totals.packed} / ${totals.ordered} packed`;
    }

    function itemStatusBadge(item) {
        const status = (item.item_status || '').toString().toUpperCase();
        let label = 'WAITING';
        let classes = 'badge-status';

        if (status === 'PACKED') {
            label = 'PACKED';
            classes = 'badge-status is-fresh';
        } else if (status === 'PARTIAL' || status === 'PARTIALLY PACKED') {
            label = 'PARTIALLY PACKED';
            classes = 'badge-status is-fifo';
        } else {
            const packed = parseInt(item.packed_quantity, 10) || 0;
            const ordered = parseInt(item.ordered_quantity, 10) || 0;
            if (ordered > 0 && packed >= ordered) {
                label = 'PACKED';
                classes = 'badge-status is-fresh';
            } else if (packed > 0) {
                label = 'PARTIALLY PACKED';
                classes = 'badge-status is-fifo';
            }
        }

        return `<span class="${classes}">${label}</span>`;
    }

    function fulfillmentBadge(order) {
        const status = (order.fulfillment_status || 'PACKAGING').toString();
        const isDispatched = status === 'DISPATCHED';
        const classes = isDispatched
            ? 'badge-status is-fresh'
            : 'badge-status is-packing';

        return `<span class="${classes}">${escapeHtml(status)}</span>`;
    }

    function normalizeScanValue(value) {
        return String(value || '')
            .replace(/[\u0000-\u001F\u007F]/g, '')
            .trim();
    }

    function isCompletePackageBarcode(value) {
        const normalized = normalizeScanValue(value).toUpperCase();
        if (!normalized) {
            return false;
        }

        return completeBarcodeAliases.some((alias) => normalizeScanValue(alias).toUpperCase() === normalized);
    }

    function barcodeSvg(value, options) {
        if (!value) {
            return '';
        }

        const opts = Object.assign({
            height: 72,
            moduleWidth: 2.4,
            showText: true,
        }, options || {});

        if (window.SupplierCode128 && typeof window.SupplierCode128.toSvg === 'function') {
            return window.SupplierCode128.toSvg(String(value), opts);
        }

        return `<div class="fs-18 fw-semibold">${escapeHtml(value)}</div>`;
    }

    function renderCompletePackageBarcode() {
        if (completeBarcodeValue) {
            completeBarcodeValue.textContent = completeBarcode;
        }

        if (completeBarcodeSvgHost) {
            completeBarcodeSvgHost.innerHTML = barcodeSvg(completeBarcode, {
                height: 80,
                moduleWidth: 3,
                showText: true,
            });
        }

        if (completeBarcodeDownload) {
            completeBarcodeDownload.setAttribute(
                'download',
                `complete-package-barcode-${completeBarcode}.svg`
            );
        }
    }

    function downloadCompletePackageBarcode(event) {
        if (event) {
            event.preventDefault();
        }

        if (!completeBarcodeSvgHost) {
            return;
        }

        let svg = completeBarcodeSvgHost.querySelector('svg');
        if (!svg) {
            renderCompletePackageBarcode();
            svg = completeBarcodeSvgHost.querySelector('svg');
        }

        if (!svg) {
            showModalAlert('warning', 'Barcode is not ready to download yet.');
            return;
        }

        const markup = svg.outerHTML;
        const blob = new Blob([markup], { type: 'image/svg+xml;charset=utf-8' });
        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = `complete-package-barcode-${completeBarcode}.svg`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    }

    function focusOrderScanner() {
        if (!canPack || !orderScanInput) {
            return;
        }

        window.setTimeout(() => {
            orderScanInput.focus();
            orderScanInput.select();
        }, 50);
    }

    function focusProductScanner() {
        if (!canPack || !productScanInput) {
            return;
        }

        window.setTimeout(() => {
            productScanInput.focus();
            productScanInput.select();
        }, 50);
    }

    function setOrderScanBusy(isBusy) {
        state.scanningOrder = isBusy;
        if (orderScanBusy) {
            orderScanBusy.classList.toggle('d-none', !isBusy);
        }
        if (orderScanInput) {
            orderScanInput.disabled = isBusy;
        }
    }

    function setProductScanBusy(isBusy) {
        state.scanningProduct = isBusy || state.completing;
        if (productScanBusy) {
            productScanBusy.classList.toggle('d-none', !(isBusy || state.completing));
        }
        if (productScanInput) {
            productScanInput.disabled = isBusy || state.completing;
        }
        if (completeScanInput) {
            completeScanInput.disabled = isBusy || state.completing;
        }
        if (completeBtn) {
            const totals = state.activeOrder ? packingTotals(state.activeOrder) : { remaining: 1 };
            completeBtn.disabled = isBusy || state.completing || totals.remaining > 0;
        }
    }

    function renderRows(orders) {
        if (!orders.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${columnCount()}" class="text-center text-muted py-5">
                        ${escapeHtml(emptyMessage)}
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = orders.map((order) => {
            const id = String(order.id);
            const totals = packingTotals(order);
            const actions = [];

            if (canPrint) {
                actions.push(`
                    <button type="button"
                        class="btn btn-sm btn-outline-secondary packaging-reprint-btn"
                        data-order-id="${escapeHtml(id)}"
                        data-order-number="${escapeHtml(order.order_number || id)}">
                        Re-print
                    </button>
                `);
            }

            if (canPack) {
                actions.push(`
                    <button type="button"
                        class="btn btn-sm btn-outline-primary packaging-open-btn"
                        data-order-reference="${escapeHtml(order.order_number || id)}">
                        Pack
                    </button>
                `);
            }

            const actionCell = showActions
                ? `<td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                            ${actions.join('')}
                        </div>
                    </td>`
                : '';

            return `
                <tr data-order-id="${escapeHtml(id)}">
                    <td><div class="order-number">${escapeHtml(order.order_number || '—')}</div></td>
                    <td>${escapeHtml(order.invoice_number || '—')}</td>
                    <td>${escapeHtml(order.customer_name || '—')}</td>
                    <td>${formatItems(order)}</td>
                    <td>${escapeHtml(String(totals.ordered || '—'))}</td>
                    <td>${escapeHtml(order.courier || '—')}</td>
                    <td>${escapeHtml(order.tracking_number || '—')}</td>
                    <td>
                        <div class="order-number">${escapeHtml(progressLabel(order))}</div>
                        <div class="progress mt-1" style="height: 6px; max-width: 140px;">
                            <div class="progress-bar bg-success" style="width: ${totals.ordered > 0 ? Math.min(100, Math.round((totals.packed / totals.ordered) * 100)) : 0}%;"></div>
                        </div>
                    </td>
                    <td>${fulfillmentBadge(order)}</td>
                    ${actionCell}
                </tr>
            `;
        }).join('');
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
                    <button type="button" class="page-link packaging-orders-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Packaging orders pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon packaging-orders-page-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${currentPage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon packaging-orders-page-btn" data-page="${currentPage + 1}" ${currentPage >= lastPage ? 'disabled' : ''}>
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
                <td colspan="${columnCount()}" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Loading packaging orders...
                </td>
            </tr>
        `;
    }

    async function loadQueue(options) {
        const keepPage = options?.keepPage === true;
        if (!keepPage && options?.page) {
            state.page = options.page;
        }

        setLoading(true);

        try {
            const params = buildQueryParams({ page: state.page });
            const response = await fetch(`${listUrl}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(friendlyError(payload, 'Unable to load packaging orders.'));
            }

            const orders = Array.isArray(payload.data) ? payload.data : [];
            state.meta = payload.meta || null;

            const filterOptions = state.meta?.filter_options || {};
            fillSelect(productSelect, filterOptions.products || [], state.filters.product_id, 'All products');
            fillSelect(courierSelect, filterOptions.couriers || [], state.filters.courier_id, 'All couriers');

            renderRows(orders);
            renderPagination(state.meta);

            if (resultsMeta && state.meta) {
                const total = state.meta.total || 0;
                resultsMeta.textContent = total === 1 ? '1 order' : `${total} orders`;
            } else if (resultsMeta) {
                resultsMeta.textContent = '';
            }
        } catch (error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${columnCount()}" class="text-center text-danger py-5">
                        ${escapeHtml(error.message || 'Unable to load packaging orders.')}
                    </td>
                </tr>
            `;
            if (paginationHost) {
                paginationHost.innerHTML = '';
            }
        } finally {
            state.loading = false;
        }
    }

    function renderWorkspace(order) {
        state.activeOrder = order;
        clearModalAlert();

        const orderNumber = order.order_number || order.id;
        if (modalTitle) {
            modalTitle.textContent = `Package Order #${orderNumber}`;
        }
        if (modalInvoice) {
            modalInvoice.textContent = `Invoice ${order.invoice_number || '—'}`;
        }
        if (modalCustomer) {
            modalCustomer.textContent = order.customer_name || '—';
        }

        const phones = [order.primary_phone, order.secondary_phone].filter(Boolean).join(' / ');
        if (modalContact) {
            modalContact.textContent = phones || '—';
        }
        if (modalCourier) {
            modalCourier.textContent = order.courier || '—';
        }
        if (modalWaybill) {
            modalWaybill.textContent = order.tracking_number || '—';
        }
        if (modalAddress) {
            modalAddress.textContent = order.address || '—';
        }

        const totals = packingTotals(order);
        const pct = totals.ordered > 0
            ? Math.min(100, Math.round((totals.packed / totals.ordered) * 100))
            : 0;

        if (progressText) {
            progressText.textContent = `${totals.packed} / ${totals.ordered} items packed`;
        }
        if (progressPct) {
            progressPct.textContent = `${pct}%`;
        }
        if (progressBar) {
            progressBar.style.width = `${pct}%`;
            progressBar.setAttribute('aria-valuenow', String(pct));
        }

        const items = Array.isArray(order.items) ? order.items : [];
        if (itemsBody) {
            if (!items.length) {
                itemsBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-muted text-center py-4">No items</td>
                    </tr>
                `;
            } else {
                itemsBody.innerHTML = items.map((item) => `
                    <tr>
                        <td class="fw-medium">${escapeHtml(item.product_name || '—')}</td>
                        <td>${escapeHtml(item.variant || '—')}</td>
                        <td><code class="fs-13">${escapeHtml(item.barcode || '—')}</code></td>
                        <td class="text-center fs-18 fw-semibold">${escapeHtml(String(item.ordered_quantity ?? 0))}</td>
                        <td class="text-center fs-18 fw-semibold text-success">${escapeHtml(String(item.packed_quantity ?? 0))}</td>
                        <td class="text-center fs-18 fw-semibold ${((item.remaining_quantity ?? 0) > 0) ? 'text-warning' : 'text-muted'}">${escapeHtml(String(item.remaining_quantity ?? 0))}</td>
                        <td>${itemStatusBadge(item)}</td>
                    </tr>
                `).join('');
            }
        }

        renderCompletePackageBarcode();

        if (completeBtn) {
            completeBtn.disabled = state.completing || state.scanningProduct || totals.remaining > 0;
        }
    }

    function openWorkspace(order) {
        renderWorkspace(order);
        if (workspaceModal) {
            workspaceModal.show();
        }
        focusProductScanner();
    }

    function closeWorkspace() {
        state.activeOrder = null;
        clearModalAlert();
        if (productScanInput) {
            productScanInput.value = '';
        }
        if (completeScanInput) {
            completeScanInput.value = '';
        }
        if (workspaceModal) {
            workspaceModal.hide();
        }
        focusOrderScanner();
    }

    async function scanOrder(reference) {
        const value = normalizeScanValue(reference);
        if (!value || !canPack || state.scanningOrder) {
            return;
        }

        if (isCompletePackageBarcode(value)) {
            showAlert('warning', 'Scan a Feeder Order ID first, then scan Complete Package inside the workspace.');
            if (orderScanInput) {
                orderScanInput.value = '';
            }
            focusOrderScanner();
            return;
        }

        clearAlert();
        setOrderScanBusy(true);

        try {
            const response = await fetch(scanOrderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order_reference: value }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw Object.assign(new Error(friendlyError(payload, 'Order is not ready for packaging.')), {
                    code: payload?.code,
                });
            }

            const order = payload.data;
            if (!order || !order.id) {
                throw new Error('Order is not ready for packaging.');
            }

            if (orderScanInput) {
                orderScanInput.value = '';
            }

            openWorkspace(order);
        } catch (error) {
            showAlert('danger', error.message || 'Order is not ready for packaging.');
            if (orderScanInput) {
                orderScanInput.value = '';
            }
            focusOrderScanner();
        } finally {
            setOrderScanBusy(false);
            if (!state.activeOrder) {
                focusOrderScanner();
            }
        }
    }

    async function scanProduct(barcode) {
        const value = normalizeScanValue(barcode);
        if (!value || !canPack || !state.activeOrder || state.completing) {
            return;
        }

        // Complete Package keyword must mirror the Complete Package button.
        if (isCompletePackageBarcode(value)) {
            if (productScanInput) {
                productScanInput.value = '';
            }
            if (completeScanInput) {
                completeScanInput.value = '';
            }
            await completePackage();
            return;
        }

        if (state.scanningProduct) {
            return;
        }

        clearModalAlert();
        setProductScanBusy(true);

        try {
            const response = await fetch(scanProductUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    order_id: state.activeOrder.id,
                    product_barcode: value,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (payload?.data && payload.data.id) {
                    renderWorkspace(payload.data);
                }
                throw Object.assign(new Error(friendlyError(payload, 'Wrong product for this order.')), {
                    code: payload?.code,
                });
            }

            if (payload.data) {
                renderWorkspace(payload.data);
            }

            if (productScanInput) {
                productScanInput.value = '';
            }
        } catch (error) {
            showModalAlert('danger', error.message || 'Wrong product for this order.');
            if (productScanInput) {
                productScanInput.value = '';
            }
        } finally {
            setProductScanBusy(false);
            focusProductScanner();
        }
    }

    async function completePackage() {
        if (!canPack || !state.activeOrder || state.completing) {
            return;
        }

        const totals = packingTotals(state.activeOrder);
        if (totals.remaining > 0) {
            showModalAlert('warning', 'Package is incomplete. Keep scanning remaining items.');
            focusProductScanner();
            return;
        }

        clearModalAlert();
        state.completing = true;
        setProductScanBusy(true);

        try {
            const response = await fetch(completeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order_id: state.activeOrder.id }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (payload?.data && payload.data.items) {
                    renderWorkspace(payload.data);
                }
                throw Object.assign(new Error(friendlyError(payload, 'Unable to complete package.')), {
                    code: payload?.code,
                });
            }

            const orderNumber = payload?.data?.order_number || state.activeOrder.order_number || '';
            showAlert('success', payload.message || `Package completed. Order ${orderNumber} is DISPATCHED.`);

            if (workspaceModal) {
                workspaceModal.hide();
            }
            state.activeOrder = null;
            clearModalAlert();

            await loadQueue({ keepPage: true });
            focusOrderScanner();
        } catch (error) {
            showModalAlert('danger', error.message || 'Unable to complete package.');
            focusProductScanner();
        } finally {
            state.completing = false;
            setProductScanBusy(false);
        }
    }

    async function reprintOrder(orderId, orderNumber) {
        if (!canPrint || !reprintUrl || state.reprinting) {
            return;
        }

        const id = parseInt(orderId, 10);
        if (!Number.isInteger(id) || id <= 0) {
            showAlert('warning', 'Unable to re-print this order.');
            return;
        }

        if (!window.SupplierInvoicePrint || typeof window.SupplierInvoicePrint.showDocuments !== 'function') {
            showAlert('danger', 'Invoice print runtime is not available. Refresh the page and try again.');
            return;
        }

        state.reprinting = true;
        clearAlert();

        const buttons = tableBody
            ? Array.from(tableBody.querySelectorAll(`.packaging-reprint-btn[data-order-id="${id}"]`))
            : [];
        buttons.forEach((button) => {
            button.disabled = true;
            button.textContent = 'Preparing...';
        });

        try {
            const response = await fetch(reprintUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order_ids: [id] }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const detail = payload.message
                    || (payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().join(' ')
                        : null)
                    || 'Unable to prepare invoice for re-printing.';
                throw new Error(detail);
            }

            const documents = Array.isArray(payload.data) ? payload.data : [];
            if (!documents.length) {
                throw new Error('Re-print data was empty.');
            }

            const label = orderNumber
                ? `Re-print Invoice #${orderNumber}`
                : 'Re-print Invoice';

            window.SupplierInvoicePrint.showDocuments(label, documents, { autoPrint: true });
            showAlert('success', payload.message || 'Invoice ready for re-printing.');
        } catch (error) {
            if (window.SupplierInvoicePrint && typeof window.SupplierInvoicePrint.close === 'function') {
                window.SupplierInvoicePrint.close();
            }
            showAlert('danger', error.message || 'Unable to re-print invoice.');
        } finally {
            state.reprinting = false;
            buttons.forEach((button) => {
                button.disabled = false;
                button.textContent = 'Re-print';
            });
            if (!state.activeOrder) {
                focusOrderScanner();
            }
        }
    }

    function bindScannerInput(input, handler) {
        if (!input) {
            return;
        }

        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== 'Tab') {
                return;
            }

            event.preventDefault();
            const value = normalizeScanValue(input.value);
            input.value = '';
            handler(value);
        });

        // Some wedge scanners dump characters without a trailing Enter/Tab.
        input.addEventListener('input', () => {
            const value = normalizeScanValue(input.value);
            if (!isCompletePackageBarcode(value)) {
                return;
            }

            input.value = '';
            handler(value);
        });
    }

    function clearPackagingFilters() {
        if (filterForm) {
            filterForm.reset();
        }
        state.filters = { search: '', product_id: '', courier_id: '' };
        state.page = 1;
        loadQueue();
        focusOrderScanner();
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            readFiltersFromForm();
            state.page = 1;
            loadQueue();
            focusOrderScanner();
        });
    }

    ['packagingFilterCourier', 'packagingFilterProduct'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }

        el.addEventListener('change', () => {
            readFiltersFromForm();
            state.page = 1;
            loadQueue();
            focusOrderScanner();
        });
    });

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearPackagingFilters);
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', clearPackagingFilters);
    }

    if (paginationHost) {
        paginationHost.addEventListener('click', (event) => {
            const button = event.target.closest('.packaging-orders-page-btn');
            if (!button || button.disabled) {
                return;
            }

            const pageNum = parseInt(button.dataset.page || '1', 10);
            if (!Number.isNaN(pageNum) && pageNum !== state.page) {
                state.page = pageNum;
                loadQueue();
            }
        });
    }

    if (tableBody) {
        tableBody.addEventListener('click', (event) => {
            const reprintButton = event.target.closest('.packaging-reprint-btn');
            if (reprintButton && canPrint) {
                reprintOrder(
                    reprintButton.dataset.orderId || '',
                    reprintButton.dataset.orderNumber || '',
                );
                return;
            }

            const button = event.target.closest('.packaging-open-btn');
            if (!button || !canPack) {
                return;
            }

            const reference = button.dataset.orderReference || '';
            scanOrder(reference);
        });
    }

    bindScannerInput(orderScanInput, scanOrder);
    bindScannerInput(productScanInput, scanProduct);
    bindScannerInput(completeScanInput, (value) => {
        if (isCompletePackageBarcode(value)) {
            completePackage();
            return;
        }

        if (normalizeScanValue(value) !== '') {
            scanProduct(value);
        }
    });

    if (completeBarcodeDownload) {
        completeBarcodeDownload.addEventListener('click', downloadCompletePackageBarcode);
    }

    if (completeBtn) {
        completeBtn.addEventListener('click', () => {
            completePackage();
        });
    }

    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', () => {
            focusProductScanner();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            state.activeOrder = null;
            clearModalAlert();
            if (productScanInput) {
                productScanInput.value = '';
            }
            if (completeScanInput) {
                completeScanInput.value = '';
            }
            focusOrderScanner();
        });
    }

    // Keep order scanner focused when clicking empty page areas (warehouse UX).
    document.addEventListener('click', (event) => {
        if (!canPack || state.activeOrder || state.reprinting) {
            return;
        }

        if (document.body.classList.contains('supplier-invoice-print-active')) {
            return;
        }

        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (target.closest('input, select, textarea, button, a, label, .modal, #supplierInvoicePrintSurface')) {
            return;
        }

        focusOrderScanner();
    });

    loadQueue();
    focusOrderScanner();
})();
</script>
