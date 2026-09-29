<script>
(function () {
    const page = document.getElementById('supplierPrintOrdersPage');
    if (!page) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const listUrl = page.dataset.listUrl;
    const printDataUrl = page.dataset.printDataUrl || '';
    const sendToPackingUrl = page.dataset.sendToPackingUrl || '';
    const canPrint = page.dataset.canPrint === '1';
    const canSendToPacking = page.dataset.canSendToPacking === '1';
    const canSelect = canPrint || canSendToPacking;
    const emptyMessage = page.dataset.emptyMessage || 'No orders waiting for printing.';
    const maxBatch = parseInt(page.dataset.maxBatch || '24', 10);

    const alertHost = document.getElementById('printOrdersAlertHost');
    const tableBody = document.getElementById('printOrdersTableBody');
    const paginationHost = document.getElementById('printOrdersPaginationHost');
    const filterForm = document.getElementById('printOrdersFilterForm');
    const productSelect = document.getElementById('printFilterProduct');
    const courierSelect = document.getElementById('printFilterCourier');
    const selectAll = document.getElementById('printOrdersSelectAll');
    const selectedCountEl = document.getElementById('printOrdersSelectedCount');
    const printSelectedBtn = document.getElementById('printSelectedBtn');
    const printItemListBtn = document.getElementById('printItemListBtn');
    const sendToPackingBtn = document.getElementById('sendToPackingBtn');
    const clearFiltersBtn = document.getElementById('printOrdersClearFilters');
    const resetFiltersBtn = document.getElementById('printOrdersResetFilters');
    const resultsMeta = document.getElementById('printOrdersResultsMeta');

    const packingModalEl = document.getElementById('sendToPackingModal');
    const packingModalCopy = document.getElementById('sendToPackingModalCopy');
    const packingConfirmBtn = document.getElementById('sendToPackingConfirmBtn');
    const packingModal = packingModalEl && window.bootstrap
        ? new bootstrap.Modal(packingModalEl)
        : null;

    const state = {
        page: 1,
        perPage: 25,
        filters: {
            search: '',
            product_id: '',
            courier_id: '',
        },
        selectedIds: new Set(),
        selectedOrders: new Map(),
        ordersById: {},
        meta: null,
        loading: false,
        submitting: false,
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

    function columnCount() {
        let count = 10;
        if (canSelect) {
            count += 1;
        }
        if (canPrint) {
            count += 1;
        }
        return count;
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

    function formatReseller(order) {
        const company = (order.reseller_company_name || '').toString().trim();
        const name = (order.reseller_name || '').toString().trim();

        if (company && name) {
            return `<div class="fs-14">${escapeHtml(company)}</div><div class="fs-12 text-muted">${escapeHtml(name)}</div>`;
        }

        if (company) {
            return `<div class="fs-14">${escapeHtml(company)}</div>`;
        }

        if (name) {
            return `<div class="fs-14">${escapeHtml(name)}</div>`;
        }

        return '—';
    }

    function printStatusBadge(order) {
        if (order.is_printed) {
            return '<span class="badge-status is-fresh">Printed</span>';
        }

        if (order.is_print_ready === false) {
            return '<span class="badge-status is-fifo">Not ready</span>';
        }

        return '<span class="badge-status">Not printed</span>';
    }

    function barcodeSvg(value, options) {
        if (!value) {
            return '';
        }

        const opts = Object.assign({ height: 48, moduleWidth: 1.6, showText: false }, options || {});

        if (window.SupplierCode128 && typeof window.SupplierCode128.toSvg === 'function') {
            return window.SupplierCode128.toSvg(String(value), opts);
        }

        return `<div class="print-barcode-fallback">${escapeHtml(value)}</div>`;
    }

    function formatInvoiceMoney(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const raw = String(value).trim();
        if (/[a-zA-Z]/.test(raw)) {
            return raw;
        }

        return `Rs. ${raw}`;
    }

    function formatInvoiceLinePrice(item) {
        const unitRaw = item?.unit_selling_price;
        const qty = parseInt(item?.quantity, 10);

        if (unitRaw === null || unitRaw === undefined || unitRaw === '') {
            return '—';
        }

        const unit = parseFloat(String(unitRaw).replace(/,/g, ''));
        if (!Number.isFinite(unit) || !Number.isFinite(qty) || qty <= 0) {
            return formatInvoiceMoney(unitRaw);
        }

        const lineTotal = Math.round(unit * qty * 100) / 100;
        return formatInvoiceMoney(lineTotal.toFixed(2));
    }

    function formatInvoiceItemLabel(item) {
        const name = (item?.product_name || 'Product').toString();
        const variant = (item?.variant || '').toString().trim();
        const qty = item?.quantity ?? '—';

        if (variant) {
            return `${name} (${variant}) x ${qty}`;
        }

        return `${name} x ${qty}`;
    }

    function formatInvoiceDate(value) {
        if (!value) {
            return '—';
        }

        const raw = String(value).trim();
        const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return raw;
        }

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthIndex = parseInt(match[2], 10) - 1;
        const day = parseInt(match[3], 10);
        const month = months[monthIndex] || match[2];

        return `${day} ${month} ${match[1]}`;
    }

    function invoiceBarcodeBlock(value, options) {
        if (!value) {
            return '';
        }

        const opts = options || {};
        const className = opts.className ? ` ${opts.className}` : '';
        const showNumber = opts.showNumber !== false;

        return `
            <div class="barcode-container${className}">
                ${barcodeSvg(value, opts.svg || null)}
                ${showNumber ? `<div class="barcode-number">${escapeHtml(value)}</div>` : ''}
            </div>
        `;
    }

    function selectedOrdersAreAllPrinted() {
        if (state.selectedIds.size === 0) {
            return false;
        }

        for (const id of state.selectedIds) {
            const order = state.selectedOrders.get(String(id)) || state.ordersById[String(id)];
            if (!order || !order.is_printed) {
                return false;
            }
        }

        return true;
    }

    function syncSelectionUi() {
        const count = state.selectedIds.size;
        const canSendPrintedSelection = count > 0 && selectedOrdersAreAllPrinted();

        if (selectedCountEl) {
            selectedCountEl.textContent = String(count);
        }

        if (printSelectedBtn) {
            printSelectedBtn.disabled = count === 0 || state.submitting;
        }

        if (sendToPackingBtn) {
            // Enable only when every selected order has Print Status = Printed.
            sendToPackingBtn.disabled = !canSendPrintedSelection || state.submitting;
            sendToPackingBtn.title = canSendPrintedSelection || count === 0
                ? ''
                : 'Select printed orders only. Print the selected orders first, then send to packing.';
        }

        if (printItemListBtn) {
            printItemListBtn.disabled = state.submitting;
        }

        if (selectAll) {
            const checkboxes = tableBody.querySelectorAll('.order-select-checkbox');
            const total = checkboxes.length;
            const selectedOnPage = Array.from(checkboxes).filter((box) => box.checked).length;
            selectAll.checked = total > 0 && selectedOnPage === total;
            selectAll.indeterminate = selectedOnPage > 0 && selectedOnPage < total;
        }
    }

    function renderRows(orders) {
        state.ordersById = {};
        orders.forEach((order) => {
            state.ordersById[String(order.id)] = order;
            if (state.selectedIds.has(String(order.id))) {
                state.selectedOrders.set(String(order.id), order);
            }
        });

        if (!orders.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${columnCount()}" class="text-center text-muted py-5">
                        ${escapeHtml(emptyMessage)}
                    </td>
                </tr>
            `;
            syncSelectionUi();
            return;
        }

        tableBody.innerHTML = orders.map((order) => {
            const id = String(order.id);
            const checked = state.selectedIds.has(id) ? 'checked' : '';
            const selectCell = canSelect
                ? `<td>
                        <div class="form-check">
                            <input class="form-check-input order-select-checkbox" type="checkbox"
                                value="${escapeHtml(id)}" ${checked}
                                aria-label="Select order ${escapeHtml(order.order_number)}">
                        </div>
                    </td>`
                : '';

            const actionCell = canPrint
                ? `<td class="text-end">
                        <button type="button"
                            class="btn btn-sm btn-outline-primary print-single-btn"
                            data-order-id="${escapeHtml(id)}"
                            ${order.is_print_ready === false ? 'disabled title="Order is not print-ready"' : ''}>
                            Print
                        </button>
                    </td>`
                : '';

            return `
                <tr data-order-id="${escapeHtml(id)}">
                    ${selectCell}
                    <td><div class="order-number">${escapeHtml(order.order_number || '—')}</div></td>
                    <td>${escapeHtml(order.order_date || '—')}</td>
                    <td>${formatReseller(order)}</td>
                    <td>${escapeHtml(order.customer_name || '—')}</td>
                    <td>${formatItems(order)}</td>
                    <td>${escapeHtml(formatQuantity(order))}</td>
                    <td>${escapeHtml(order.courier || '—')}</td>
                    <td>${escapeHtml(order.tracking_number || '—')}</td>
                    <td>${formatPayable(order)}</td>
                    <td>${printStatusBadge(order)}</td>
                    ${actionCell}
                </tr>
            `;
        }).join('');

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
                    <button type="button" class="page-link print-orders-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Print orders pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon print-orders-page-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${currentPage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon print-orders-page-btn" data-page="${currentPage + 1}" ${currentPage >= lastPage ? 'disabled' : ''}>
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
                        Loading print orders...
                    </td>
                </tr>
            `;
        }
    }

    async function loadOrders(options = {}) {
        const preserveAlert = options.preserveAlert === true
            || document.body.classList.contains('supplier-invoice-print-active');

        if (!preserveAlert) {
            clearAlert();
        }
        setLoading(true);

        try {
            const response = await fetch(`${listUrl}?${buildQueryParams().toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'Unable to load print orders.');
            }

            const orders = Array.isArray(payload.data) ? payload.data : [];
            state.meta = payload.meta || null;

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
                        ${escapeHtml(error.message || 'Unable to load print orders.')}
                    </td>
                </tr>
            `;
            if (paginationHost) {
                paginationHost.innerHTML = '';
            }
            showAlert('error', error.message || 'Unable to load print orders.');
        } finally {
            state.loading = false;
        }
    }

    function trySelectOrder(orderId, checked, orderSnapshot) {
        const id = String(orderId);

        if (!checked) {
            state.selectedIds.delete(id);
            state.selectedOrders.delete(id);
            syncSelectionUi();
            return true;
        }

        if (state.selectedIds.has(id)) {
            if (orderSnapshot) {
                state.selectedOrders.set(id, orderSnapshot);
            }
            syncSelectionUi();
            return true;
        }

        if (state.selectedIds.size >= maxBatch) {
            showAlert('warning', `You can select a maximum of ${maxBatch} orders for print or Send to Packing.`);
            return false;
        }

        state.selectedIds.add(id);
        const snapshot = orderSnapshot || state.ordersById[id];
        if (snapshot) {
            state.selectedOrders.set(id, snapshot);
        }
        clearAlert();
        syncSelectionUi();
        return true;
    }

    function selectedOrderIds() {
        return Array.from(state.selectedIds).map((id) => parseInt(id, 10)).filter(Boolean);
    }

    function getPrintSurface() {
        let surface = document.getElementById('supplierInvoicePrintSurface');
        if (!surface) {
            surface = document.createElement('div');
            surface.id = 'supplierInvoicePrintSurface';
            surface.setAttribute('aria-live', 'polite');
            document.body.appendChild(surface);
        } else if (surface.parentElement !== document.body) {
            // Keep the surface at body level so layout overflow/sidebar cannot clip
            // multi-invoice print output.
            document.body.appendChild(surface);
        }

        return surface;
    }

    function closePrintSurface() {
        const surface = getPrintSurface();
        if (surface) {
            surface.innerHTML = '';
        }
        document.body.classList.remove('supplier-invoice-print-active');
    }

    function showPrintSurface(title, bodyHtml, autoPrint, options = {}) {
        const surface = getPrintSurface();
        const pageSize = options.pageSize || 'A5';
        const countLabel = options.countLabel || null;

        surface.innerHTML = `
            <div class="supplier-invoice-print-toolbar">
                <strong>${escapeHtml(title)}</strong>
                <span class="text-muted" id="supplierInvoicePrintCount"></span>
                <button type="button" class="btn btn-primary btn-sm text-white" id="supplierInvoicePrintAgainBtn">Print</button>
                <button type="button" class="btn btn-light border btn-sm" id="supplierInvoicePrintCloseBtn">Close</button>
            </div>
            <div class="supplier-invoice-print-documents${pageSize === 'A4' ? ' is-a4' : ''}">
                ${bodyHtml}
            </div>
        `;

        const pageCount = surface.querySelectorAll('.invoice-page').length;
        const countEl = document.getElementById('supplierInvoicePrintCount');
        if (countEl) {
            if (countLabel) {
                countEl.textContent = countLabel;
            } else {
                countEl.textContent = pageCount === 1
                    ? '1 invoice'
                    : `${pageCount} invoices`;
            }
        }

        document.body.classList.add('supplier-invoice-print-active');

        const printAgainBtn = document.getElementById('supplierInvoicePrintAgainBtn');
        const closeBtn = document.getElementById('supplierInvoicePrintCloseBtn');

        const triggerPrint = function () {
            // Prefer printing through a dedicated iframe document so page-breaks
            // work reliably for multi-document batches.
            printViaIframe(title, bodyHtml, { pageSize });
        };

        if (printAgainBtn) {
            printAgainBtn.addEventListener('click', triggerPrint);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closePrintSurface);
        }

        if (autoPrint !== false) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    window.setTimeout(triggerPrint, 200);
                });
            });
        }

        return pageCount;
    }

    function buildPrintDocumentHtml(title, bodyHtml, options = {}) {
        const pageSize = options.pageSize || 'A5';
        const pageMargin = pageSize === 'A4' ? '12mm' : '5mm';
        const bodyFontSize = pageSize === 'A4' ? '14px' : '10px';

        return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>${escapeHtml(title)}</title>
    <style>
        @page { size: ${pageSize} portrait; margin: ${pageMargin}; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: ${bodyFontSize};
            line-height: 1.25;
        }
        .invoice-page {
            page-break-after: always;
            break-after: page;
            break-inside: avoid;
            page-break-inside: avoid;
            padding: 0;
            margin: 0;
        }
        .invoice-page:last-child {
            page-break-after: auto;
            break-after: auto;
        }
        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 4mm;
            background: #000;
            color: #fff;
            padding: 3.5mm 4mm;
            margin-bottom: 2.5mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .inv-supplier {
            flex: 1 1 auto;
            min-width: 0;
            text-align: left;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            line-height: 1.15;
            word-break: break-word;
            color: #fff;
        }
        .inv-care {
            flex: 0 0 auto;
            margin-top: 0;
            text-align: right;
            color: #fff;
        }
        .inv-care-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 0.6mm;
            color: #fff;
        }
        .inv-care-number {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.4px;
            line-height: 1.1;
            color: #fff;
        }
        .inv-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 4mm;
            padding: 1.6mm 0 2mm;
            border-bottom: 1px solid #111;
            margin-bottom: 2mm;
        }
        .inv-meta-line {
            flex: 1;
            min-width: 0;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
        }
        .inv-meta-line.is-end { text-align: right; }
        .inv-meta-label {
            font-weight: 800;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .inv-label {
            display: block;
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 0.4mm;
            color: #111;
        }
        .inv-value {
            font-size: 11px;
            font-weight: 700;
            word-break: break-word;
        }
        .inv-value-lg {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
            word-break: break-word;
        }
        .inv-section {
            margin: 0 0 1.5mm;
            padding: 0 0 1.5mm;
            border-bottom: 1px solid #111;
        }
        .inv-section:last-child {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .inv-section-title {
            display: block;
            background: #111;
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            padding: 1mm 1.5mm;
            margin-bottom: 1.5mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .inv-field { margin: 0 0 1.2mm; }
        .inv-field:last-child { margin-bottom: 0; }
        .inv-field-text {
            font-size: 10px;
            font-weight: 600;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .inv-two-col {
            display: flex;
            gap: 3mm;
            margin-top: 1mm;
        }
        .inv-two-col > * { flex: 1; min-width: 0; }
        .inv-customer-line {
            font-size: 17px;
            font-weight: 700;
            line-height: 1.25;
            margin: 0 0 1.4mm;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .inv-customer-split {
            display: flex;
            gap: 4mm;
            align-items: flex-start;
            margin-top: 0.6mm;
            padding-top: 1.6mm;
            border-top: 1px solid #111;
        }
        .inv-customer-split > .inv-customer-line {
            flex: 1;
            min-width: 0;
            margin-bottom: 0;
            font-size: 16px;
        }
        .inv-waybill .inv-value-lg {
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        .inv-delivery-body {
            display: flex;
            align-items: stretch;
            gap: 3mm;
        }
        .inv-delivery-left {
            flex: 1 1 48%;
            min-width: 0;
        }
        .inv-delivery-right {
            flex: 1 1 52%;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .barcode-container.is-waybill {
            width: 100%;
            margin: 0;
            padding: 1mm 1.5mm;
        }
        .barcode-container.is-waybill svg {
            width: 100%;
            max-width: 100%;
            height: 14mm;
        }
        .barcode-container.is-waybill .barcode-number {
            margin-top: 1mm;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .inv-item {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 3mm;
            padding: 0.8mm 0;
            border-bottom: 1px solid #333;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.25;
        }
        .inv-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .inv-item:first-child { padding-top: 0; }
        .inv-item-desc {
            flex: 1 1 auto;
            min-width: 0;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .inv-item-price {
            flex: 0 0 auto;
            white-space: nowrap;
            font-weight: 800;
        }
        .inv-charges { margin-top: 1.5mm; }
        .inv-charge-row {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            font-size: 13px;
            font-weight: 600;
            margin: 0.6mm 0;
        }
        .inv-charge-row.is-total {
            margin-top: 1mm;
            padding-top: 1mm;
            border-top: 1.5px solid #111;
            font-size: 15px;
            font-weight: 800;
        }
        .inv-invoice-line {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.25;
            word-break: break-word;
        }
        .inv-invoice-label {
            font-weight: 800;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .inv-invoice-barcode {
            flex: 1 1 54%;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .barcode-container.is-invoice {
            width: 100%;
            margin: 0;
            padding: 0;
            align-items: flex-end;
        }
        .barcode-container.is-invoice svg {
            width: 100%;
            max-width: 100%;
            height: 14mm;
        }
        .barcode-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 1mm 0 0.5mm;
            padding: 1.5mm 2mm;
        }
        .barcode-container svg {
            width: 100%;
            max-width: 100mm;
            height: 11mm;
        }
        .barcode-number {
            margin-top: 1mm;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }
        .print-barcode-fallback {
            font-family: monospace;
            letter-spacing: 2px;
            border: 1px solid #000;
            display: inline-block;
            padding: 4px 8px;
            font-weight: 700;
        }
        .inv-payment {
            margin-top: 0.5mm;
            border: 1.5px solid #111;
            padding: 1.5mm 2mm;
            text-align: center;
        }
        .inv-payment-label {
            display: block;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            margin-bottom: 1mm;
        }
        .inv-payment.is-cod {
            background: #000;
            color: #fff;
            border-color: #000;
            padding: 2.5mm 3mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .inv-payment-line {
            display: block;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.2px;
            line-height: 1.15;
            color: #fff;
        }
        .inv-payment-amount {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.3px;
            line-height: 1.05;
        }
        .inv-payment-sub {
            margin-top: 0.8mm;
            font-size: 10px;
            font-weight: 700;
        }
        .invoice-header {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .invoice-sub { text-align: center; margin-bottom: 10px; }
        .invoice-rule {
            border: 0;
            border-top: 1px solid #222;
            margin: 10px 0;
        }
        .invoice-row { margin: 3px 0; }
        .item-list-page {
            max-width: 210mm;
            margin: 0 auto;
            padding: 8mm 10mm;
        }
        .item-list-header {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .item-list-sub {
            text-align: center;
            font-size: 13px;
            color: #333;
            margin: 0 0 12px;
        }
        .item-list-rule {
            border: 0;
            border-top: 2px solid #111;
            margin: 0 0 16px;
            width: 100%;
        }
        .item-list-body {
            text-align: left;
        }
        .item-list-line {
            font-size: 15px;
            line-height: 1.55;
            margin: 0 0 8px;
        }
    </style>
</head>
<body>
${bodyHtml}
</body>
</html>`;
    }

    function printViaIframe(title, bodyHtml, options = {}) {
        let iframe = document.getElementById('supplierInvoicePrintFrame');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'supplierInvoicePrintFrame';
            iframe.setAttribute('title', 'Invoice print frame');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(iframe);
        }

        const frameWindow = iframe.contentWindow;
        const frameDocument = frameWindow ? frameWindow.document : null;
        if (!frameWindow || !frameDocument) {
            window.print();
            return;
        }

        let printed = false;
        const runPrint = function () {
            if (printed) {
                return;
            }
            printed = true;
            iframe.onload = null;
            try {
                frameWindow.focus();
                frameWindow.print();
            } catch (error) {
                window.print();
            }
        };

        frameDocument.open();
        frameDocument.write(buildPrintDocumentHtml(title, bodyHtml, options));
        frameDocument.close();

        // document.close() can mark the frame complete and also fire load.
        // Either signal is enough; the guard above ignores the second one.
        iframe.onload = function () {
            window.setTimeout(runPrint, 50);
        };

        if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
            window.setTimeout(runPrint, 50);
        } else {
            window.setTimeout(runPrint, 250);
        }
    }

    function renderInvoiceDocuments(payloads) {
        return payloads.map((payload) => {
            const order = payload.order || {};
            const supplier = payload.supplier || {};
            const customerCare = payload.customer_care || {};
            const courier = payload.courier || {};
            const customer = payload.customer || {};
            const charges = payload.charges || {};
            const payment = order.payment || {};
            const items = Array.isArray(payload.items) ? payload.items : [];
            const district = courier.district || courier.state || '—';
            const contacts = [customer.contact_1, customer.contact_2]
                .filter((value) => value)
                .map((value) => escapeHtml(value))
                .join(' | ') || '—';
            const invoiceNumber = order.invoice_number || '';
            const isPaymentCollected = payment.is_payment_collected || charges.is_payment_collected;
            const productTotal = charges.items_subtotal;
            const deliveryCharge = charges.courier_fee;
            const payableTotal = charges.customer_payable_amount
                || payment.cod_amount
                || charges.cod_amount
                || '—';
            const codAmount = payment.cod_amount || charges.cod_amount || charges.customer_payable_amount || '—';

            const paymentBlock = isPaymentCollected
                ? `<div class="inv-payment">
                        <span class="inv-payment-label">Payment Collected</span>
                   </div>`
                : `<div class="inv-payment is-cod">
                        <span class="inv-payment-line">COD AMOUNT : ${escapeHtml(formatInvoiceMoney(codAmount))}</span>
                   </div>`;

            const itemsHtml = items.length
                ? items.map((item) => `
                    <div class="inv-item">
                        <span class="inv-item-desc">${escapeHtml(formatInvoiceItemLabel(item))}</span>
                        <span class="inv-item-price">${escapeHtml(formatInvoiceLinePrice(item))}</span>
                    </div>
                `).join('')
                : '<div class="inv-field-text">No items</div>';

            return `
                <section class="invoice-page">
                    <header class="inv-header">
                        <div class="inv-supplier">${escapeHtml(supplier.company_name || '—')}</div>
                        <div class="inv-care">
                            <span class="inv-care-label">Customer Care</span>
                            <div class="inv-care-number">${escapeHtml(customerCare.reseller_company_phone || '—')}</div>
                        </div>
                    </header>

                    <div class="inv-meta">
                        <div class="inv-meta-line">
                            <span class="inv-meta-label">Company Name</span> : ${escapeHtml(order.reseller_company_name || '—')}
                        </div>
                        <div class="inv-meta-line is-end">
                            <span class="inv-meta-label">Issued Date</span> : ${escapeHtml(formatInvoiceDate(order.issued_date))}
                        </div>
                    </div>

                    <section class="inv-section">
                        <span class="inv-section-title">Delivery Information</span>
                        <div class="inv-delivery-body">
                            <div class="inv-delivery-left">
                                <div class="inv-field">
                                    <span class="inv-label">Delivery Partner</span>
                                    <div class="inv-value">${escapeHtml(courier.label || courier.name || '—')}</div>
                                </div>
                                <div class="inv-field inv-waybill">
                                    <span class="inv-label">Waybill</span>
                                    <div class="inv-value-lg">${escapeHtml(courier.tracking_number || '—')}</div>
                                </div>
                            </div>
                            <div class="inv-delivery-right">
                                ${invoiceBarcodeBlock(courier.tracking_number, {
                                    className: 'is-waybill',
                                    showNumber: false,
                                    svg: { height: 54, moduleWidth: 1.9, showText: false },
                                })}
                            </div>
                        </div>
                    </section>

                    <section class="inv-section">
                        <span class="inv-section-title">Customer</span>
                        <div class="inv-customer-line">NAME : ${escapeHtml(customer.name || '—')}</div>
                        <div class="inv-customer-line">ADDRESS : ${escapeHtml(customer.address || '—')}</div>
                        <div class="inv-customer-line">Contact : ${contacts}</div>
                        <div class="inv-customer-split">
                            <div class="inv-customer-line">NEAREST CITY : ${escapeHtml(courier.city || '—')}</div>
                            <div class="inv-customer-line">DISTRICT : ${escapeHtml(district)}</div>
                        </div>
                    </section>

                    <section class="inv-section">
                        <span class="inv-section-title">Order Items</span>
                        ${itemsHtml}
                        <div class="inv-charges">
                            <div class="inv-charge-row">
                                <span>Product Total</span>
                                <span>${escapeHtml(formatInvoiceMoney(productTotal))}</span>
                            </div>
                            <div class="inv-charge-row">
                                <span>Delivery Charge</span>
                                <span>${escapeHtml(formatInvoiceMoney(deliveryCharge))}</span>
                            </div>
                            <div class="inv-charge-row is-total">
                                <span>Total</span>
                                <span>${escapeHtml(formatInvoiceMoney(payableTotal))}</span>
                            </div>
                        </div>
                    </section>

                    <section class="inv-section">
                        <span class="inv-section-title">Invoice</span>
                        <div class="inv-invoice-line">
                            <span class="inv-invoice-label">Invoice Number</span> : ${escapeHtml(invoiceNumber || '—')}
                        </div>
                    </section>

                    <section class="inv-section">
                        ${paymentBlock}
                    </section>
                </section>
            `;
        }).join('');
    }

    function collectSelectedOrderIds() {
        const fromState = Array.from(state.selectedIds)
            .map((id) => parseInt(id, 10))
            .filter((id) => Number.isInteger(id) && id > 0);

        if (fromState.length > 0) {
            return fromState;
        }

        return Array.from(tableBody.querySelectorAll('.order-select-checkbox:checked'))
            .map((box) => parseInt(box.value, 10))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    function resolveSelectedOrdersForPrint(orderIds) {
        const readyIds = [];
        const skipped = [];

        orderIds.forEach((id) => {
            const key = String(id);
            const order = state.selectedOrders.get(key) || state.ordersById[key] || null;

            if (order && order.is_print_ready === false) {
                skipped.push(order.order_number || key);
                return;
            }

            readyIds.push(id);
        });

        return { readyIds, skipped };
    }

    async function prepareAndPrint(orderIds, options = {}) {
        if (!canPrint || !printDataUrl || state.submitting) {
            return;
        }

        const uniqueIds = Array.from(new Set(
            (orderIds || [])
                .map((id) => parseInt(id, 10))
                .filter((id) => Number.isInteger(id) && id > 0)
        ));

        if (!uniqueIds.length) {
            showAlert('warning', 'Select at least one order to print.');
            return;
        }

        if (uniqueIds.length > maxBatch) {
            showAlert('warning', `You can print a maximum of ${maxBatch} orders at once.`);
            return;
        }

        const { readyIds, skipped } = options.skipReadyCheck
            ? { readyIds: uniqueIds, skipped: [] }
            : resolveSelectedOrdersForPrint(uniqueIds);

        if (!readyIds.length) {
            showAlert(
                'warning',
                'None of the selected orders are print-ready. Ensure each order has a courier, city, and waybill, then try again.'
            );
            return;
        }

        if (skipped.length > 0) {
            showAlert(
                'warning',
                `Skipping ${skipped.length} not-ready order(s). Preparing ${readyIds.length} invoice(s)…`
            );
        }

        state.submitting = true;
        syncSelectionUi();

        if (printSelectedBtn) {
            printSelectedBtn.disabled = true;
            printSelectedBtn.textContent = 'Preparing...';
        }

        try {
            const response = await fetch(printDataUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order_ids: readyIds }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const detail = payload.message
                    || (payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().join(' ')
                        : null)
                    || 'Unable to prepare print data.';
                throw new Error(detail);
            }

            const documents = Array.isArray(payload.data) ? payload.data : [];
            if (!documents.length) {
                throw new Error('Print data was empty.');
            }

            showPrintSurface(
                documents.length === 1 ? 'Supplier Invoice' : `Supplier Invoices (${documents.length})`,
                renderInvoiceDocuments(documents),
                true
            );

            showAlert(
                'success',
                payload.message || `Prepared ${documents.length} invoice(s) for printing.`
            );

            await loadOrders({ preserveAlert: true });
        } catch (error) {
            closePrintSurface();
            showAlert('error', error.message || 'Unable to prepare print data.');
            await loadOrders({ preserveAlert: true });
        } finally {
            state.submitting = false;
            if (printSelectedBtn) {
                printSelectedBtn.textContent = 'Print Selected';
            }
            syncSelectionUi();
        }
    }

    function variantKey(item) {
        if (item.product_variant_id) {
            return `v:${item.product_variant_id}`;
        }

        return `p:${item.product_id || 0}|${item.product_name || ''}|${item.variant || ''}|${item.barcode || ''}`;
    }

    function aggregateItemList(orders) {
        const products = new Map();

        orders.forEach((order) => {
            (order.items || []).forEach((item) => {
                const productId = item.product_id || item.product_name || 'unknown';
                const productName = item.product_name || 'Product';

                if (!products.has(String(productId))) {
                    products.set(String(productId), {
                        product_id: productId,
                        product_name: productName,
                        variants: new Map(),
                    });
                }

                const product = products.get(String(productId));
                const key = variantKey(item);
                const existing = product.variants.get(key);

                if (existing) {
                    existing.quantity += parseInt(item.quantity, 10) || 0;
                } else {
                    product.variants.set(key, {
                        variant: item.variant || 'Default',
                        quantity: parseInt(item.quantity, 10) || 0,
                    });
                }
            });
        });

        return Array.from(products.values()).map((product) => ({
            product_name: product.product_name,
            variants: Array.from(product.variants.values()),
        }));
    }

    function renderItemListDocument(aggregated, orderCount) {
        const lines = aggregated.flatMap((product) => {
            const productName = product.product_name || 'Product';

            return product.variants.map((variant) => {
                const variantName = variant.variant || 'Default';

                return `
                    <div class="item-list-line">
                        ${escapeHtml(productName)} (${escapeHtml(variantName)}) x ${escapeHtml(variant.quantity)}
                    </div>
                `;
            });
        }).join('');

        return `
            <section class="invoice-page item-list-page">
                <div class="item-list-header">Print Item List</div>
                <div class="item-list-sub">${escapeHtml(orderCount)} order(s)</div>
                <hr class="item-list-rule">
                <div class="item-list-body">
                    ${lines || '<div class="item-list-line">No items to print.</div>'}
                </div>
            </section>
        `;
    }

    function currentPageOrdersForItemList() {
        const orders = Object.values(state.ordersById);

        if (!orders.length) {
            throw new Error('No orders on this page to print.');
        }

        return orders;
    }

    async function printItemList() {
        if (!canPrint || state.submitting) {
            return;
        }

        state.submitting = true;
        syncSelectionUi();

        if (printItemListBtn) {
            printItemListBtn.disabled = true;
            printItemListBtn.textContent = 'Preparing...';
        }

        try {
            const orders = currentPageOrdersForItemList();
            const aggregated = aggregateItemList(orders);
            showPrintSurface(
                'Print Item List',
                renderItemListDocument(aggregated, orders.length),
                true,
                {
                    pageSize: 'A4',
                    countLabel: `${orders.length} order(s)`,
                }
            );
            clearAlert();
        } catch (error) {
            closePrintSurface();
            showAlert('error', error.message || 'Unable to print item list.');
        } finally {
            state.submitting = false;
            if (printItemListBtn) {
                printItemListBtn.textContent = 'Print Item List';
            }
            syncSelectionUi();
        }
    }

    function openSendToPackingModal() {
        if (!canSendToPacking || !packingModal) {
            return;
        }

        const orderIds = selectedOrderIds();

        if (!orderIds.length) {
            showAlert('warning', 'Select at least one printed order to send to packing.');
            return;
        }

        if (orderIds.length > maxBatch) {
            showAlert('warning', `You can select a maximum of ${maxBatch} orders for Send to Packing.`);
            return;
        }

        const unprinted = orderIds.filter((id) => {
            const order = state.selectedOrders.get(String(id)) || state.ordersById[String(id)];
            return !order || !order.is_printed;
        });

        if (unprinted.length > 0) {
            showAlert(
                'warning',
                'Send to Packing requires printed orders only. Print the selected orders first, then try again.'
            );
            return;
        }

        if (packingModalCopy) {
            packingModalCopy.textContent = `Send ${orderIds.length} printed order(s) to packaging? Reservation stays active.`;
        }

        packingModal.show();
    }

    async function confirmSendToPacking() {
        if (!canSendToPacking || !sendToPackingUrl || state.submitting) {
            return;
        }

        const orderIds = selectedOrderIds();

        if (!orderIds.length) {
            showAlert('warning', 'Select at least one printed order to send to packing.');
            return;
        }

        if (orderIds.length > maxBatch) {
            showAlert('warning', `You can select a maximum of ${maxBatch} orders for Send to Packing.`);
            return;
        }

        state.submitting = true;
        syncSelectionUi();

        if (packingConfirmBtn) {
            packingConfirmBtn.disabled = true;
            packingConfirmBtn.textContent = 'Sending...';
        }

        if (sendToPackingBtn) {
            sendToPackingBtn.disabled = true;
            sendToPackingBtn.textContent = 'Sending...';
        }

        try {
            const response = await fetch(sendToPackingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order_ids: orderIds }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const detail = payload.message
                    || (payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().join(' ')
                        : null)
                    || 'Unable to send orders to packing.';
                throw new Error(detail);
            }

            state.selectedIds.clear();
            state.selectedOrders.clear();

            if (packingModal) {
                packingModal.hide();
            }

            showAlert('success', payload.message || 'Orders sent to packaging.');
            await loadOrders();
        } catch (error) {
            showAlert('error', error.message || 'Unable to send orders to packing.');
            await loadOrders();
        } finally {
            state.submitting = false;
            if (packingConfirmBtn) {
                packingConfirmBtn.disabled = false;
                packingConfirmBtn.textContent = 'Send to Packing';
            }
            if (sendToPackingBtn) {
                sendToPackingBtn.textContent = 'Send to Packing';
            }
            syncSelectionUi();
        }
    }

    function clearPrintFilters() {
        if (filterForm) {
            filterForm.reset();
        }
        state.filters = {
            search: '',
            product_id: '',
            courier_id: '',
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

    ['printFilterCourier', 'printFilterProduct'].forEach(function (id) {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }

        el.addEventListener('change', function () {
            readFiltersFromForm();
            state.page = 1;
            state.selectedIds.clear();
            state.selectedOrders.clear();
            loadOrders();
        });
    });

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearPrintFilters);
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', clearPrintFilters);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const checkboxes = Array.from(tableBody.querySelectorAll('.order-select-checkbox'));
            if (selectAll.checked) {
                for (const checkbox of checkboxes) {
                    const order = state.ordersById[checkbox.value];
                    const accepted = trySelectOrder(checkbox.value, true, order);
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

        const accepted = trySelectOrder(target.value, target.checked, state.ordersById[target.value]);
        if (!accepted) {
            target.checked = false;
        }
    });

    tableBody.addEventListener('click', function (event) {
        const button = event.target.closest('.print-single-btn');
        if (!button || button.disabled) {
            return;
        }

        const orderId = parseInt(button.dataset.orderId, 10);
        if (!orderId) {
            return;
        }

        // Row action is already disabled when not print-ready.
        prepareAndPrint([orderId], { skipReadyCheck: true });
    });

    if (paginationHost) {
        paginationHost.addEventListener('click', function (event) {
            const button = event.target.closest('.print-orders-page-btn');
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

    if (printSelectedBtn) {
        printSelectedBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            prepareAndPrint(collectSelectedOrderIds());
        });
    }

    if (printItemListBtn) {
        printItemListBtn.addEventListener('click', printItemList);
    }

    if (sendToPackingBtn) {
        sendToPackingBtn.addEventListener('click', openSendToPackingModal);
    }

    if (packingConfirmBtn) {
        packingConfirmBtn.addEventListener('click', confirmSendToPacking);
    }

    loadOrders();
})();
</script>
