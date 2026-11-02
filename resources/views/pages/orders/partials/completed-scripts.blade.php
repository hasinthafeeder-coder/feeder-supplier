<script>
(function () {
    const page = document.getElementById('supplierCompletedOrdersPage');
    if (!page) {
        return;
    }

    const tableBody = document.getElementById('completedOrdersTableBody');
    const resultsMeta = document.getElementById('completedOrdersResultsMeta');
    const paginationHost = document.getElementById('completedOrdersPaginationHost');
    const filterForm = document.getElementById('completedOrdersFilterForm');
    const searchInput = document.getElementById('completedFilterSearch');
    const outcomeSelect = document.getElementById('completedFilterOutcome');
    const courierSelect = document.getElementById('completedFilterCourier');
    const dateFromInput = document.getElementById('completedFilterDateFrom');
    const dateToInput = document.getElementById('completedFilterDateTo');
    const clearBtn = document.getElementById('completedOrdersClearFilters');
    const resetBtn = document.getElementById('completedOrdersResetFilters');
    const summaryCards = Array.from(page.querySelectorAll('.dispatch-summary-card[data-outcome-filter]'));
    const viewModalEl = document.getElementById('completedOrdersViewModal');

    let workspaceOrders = [];
    try {
        workspaceOrders = JSON.parse(page.dataset.orders || '[]');
    } catch (error) {
        workspaceOrders = [];
    }

    let viewModalInstance = null;
    if (viewModalEl && window.bootstrap) {
        viewModalInstance = bootstrap.Modal.getOrCreateInstance(viewModalEl);
    }

    const state = {
        page: 1,
        perPage: parseInt(page.dataset.perPage || '25', 10) || 25,
        filters: {
            search: '',
            outcome: '',
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

    function outcomeBadge(outcome) {
        const value = String(outcome || '').toUpperCase();
        let classes = 'badge-status';

        if (value === 'DELIVERED') {
            classes = 'badge-status is-delivered';
        } else if (value === 'RETURNED') {
            classes = 'badge-status is-returned';
        }

        return `<span class="${classes}">${escapeHtml(value || '—')}</span>`;
    }

    function settlementLabel(status) {
        const value = String(status || '').toUpperCase();
        if (value === 'SETTLED') {
            return 'Settled';
        }
        if (value === 'SETTLEMENT_PENDING') {
            return 'Settlement Pending';
        }
        if (value === 'NO_SETTLEMENT') {
            return 'No settlement record';
        }
        return '—';
    }

    function settlementBadge(status) {
        const value = String(status || '').toUpperCase();
        let classes = 'badge-status';

        if (value === 'SETTLED') {
            classes = 'badge-status is-settled';
        } else if (value === 'SETTLEMENT_PENDING') {
            classes = 'badge-status is-settlement-pending';
        }

        return `<span class="${classes}">${escapeHtml(settlementLabel(value))}</span>`;
    }

    function formatMoney(amount, currencyCode) {
        if (amount === null || amount === undefined || amount === '') {
            return '—';
        }
        const code = currencyCode ? `${escapeHtml(currencyCode)} ` : '';
        return `${code}${escapeHtml(amount)}`;
    }

    function formatCod(order) {
        return formatMoney(order.cod_amount, order.currency_code);
    }

    function formatSettlementAmount(order) {
        return formatMoney(order.settlement_amount, order.settlement_currency_code || order.currency_code);
    }

    function emptyMessage() {
        const hasSearch = Boolean(state.filters.search);
        const hasCourier = Boolean(state.filters.courier_id);
        const hasDates = Boolean(state.filters.date_from || state.filters.date_to);

        if (hasSearch || hasCourier || hasDates) {
            return page.dataset.emptyMessageSearch || 'No orders match your search or filters.';
        }

        const outcome = String(state.filters.outcome || '').toUpperCase();
        if (outcome === 'DELIVERED') {
            return page.dataset.emptyMessageDelivered || 'No delivered orders found.';
        }
        if (outcome === 'RETURNED') {
            return page.dataset.emptyMessageReturned || 'No returned orders found.';
        }

        return page.dataset.emptyMessageAll || 'No completed orders found.';
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);
        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            outcome: (formData.get('outcome') || '').toString(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function syncSummaryCards() {
        summaryCards.forEach((card) => {
            const cardOutcome = (card.dataset.outcomeFilter || '').toUpperCase();
            const isActive = state.filters.outcome === cardOutcome;
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
                order.primary_phone,
                order.waybill,
            ].join(' ').toLowerCase();

            if (!haystack.includes(search)) {
                return false;
            }
        }

        if (state.filters.outcome && String(order.outcome).toUpperCase() !== state.filters.outcome.toUpperCase()) {
            return false;
        }

        if (state.filters.courier_id && order.courier_id !== state.filters.courier_id) {
            return false;
        }

        const orderDate = order.completed_date || '';
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

    function renderRows(orders) {
        if (!orders.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
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
                <td>${escapeHtml(order.primary_phone || '—')}</td>
                <td>${escapeHtml(order.courier || '—')}</td>
                <td>${escapeHtml(order.waybill || '—')}</td>
                <td>${formatCod(order)}</td>
                <td>${outcomeBadge(order.outcome)}</td>
                <td>
                    <div>${escapeHtml(order.completed_at || '—')}</div>
                </td>
                <td>
                    <div>${settlementBadge(order.settlement_status)}</div>
                    <div class="order-sub">${formatSettlementAmount(order)}</div>
                </td>
                <td class="text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-light border completed-order-view-btn"
                        title="View completed order"
                        data-order-number="${escapeHtml(order.order_number || '')}"
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
                    <button type="button" class="page-link completed-page-btn" data-page="${pageNum}">${pageNum}</button>
                </li>
            `;
        }

        paginationHost.innerHTML = `
            <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                <span class="fs-15">Showing ${from} to ${to} of ${total} entries</span>
                ${lastPage > 1 ? `
                    <nav class="custom-pagination" aria-label="Completed orders pagination">
                        <ul class="pagination mb-0 justify-content-center">
                            <li class="page-item ${safePage <= 1 ? 'disabled' : ''}">
                                <button type="button" class="page-link icon completed-page-btn" data-page="${safePage - 1}" ${safePage <= 1 ? 'disabled' : ''}>
                                    <i class="material-symbols-outlined">west</i>
                                </button>
                            </li>
                            ${pagesHtml}
                            <li class="page-item ${safePage >= lastPage ? 'disabled' : ''}">
                                <button type="button" class="page-link icon completed-page-btn" data-page="${safePage + 1}" ${safePage >= lastPage ? 'disabled' : ''}>
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

    function resetFilters() {
        if (searchInput) {
            searchInput.value = '';
        }
        if (outcomeSelect) {
            outcomeSelect.value = '';
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

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value || '—';
        }
    }

    function openViewModal(order) {
        if (!order || !viewModalInstance) {
            return;
        }

        setText('completedViewOrder', order.order_number);
        setText('completedViewCustomer', order.customer_name);
        setText('completedViewPhone', order.primary_phone);
        setText('completedViewCourier', order.courier);
        setText('completedViewWaybill', order.waybill);
        setText('completedViewCompletedAt', order.completed_at);

        const codEl = document.getElementById('completedViewCod');
        if (codEl) {
            const code = order.currency_code ? `${order.currency_code} ` : '';
            codEl.textContent = order.cod_amount ? `${code}${order.cod_amount}` : '—';
        }

        const outcomeEl = document.getElementById('completedViewOutcome');
        if (outcomeEl) {
            outcomeEl.innerHTML = outcomeBadge(order.outcome);
        }

        const settlementEl = document.getElementById('completedViewSettlement');
        if (settlementEl) {
            settlementEl.innerHTML = settlementBadge(order.settlement_status);
        }

        const settlementAmountEl = document.getElementById('completedViewSettlementAmount');
        if (settlementAmountEl) {
            const settlementCode = order.settlement_currency_code || order.currency_code;
            const prefix = settlementCode ? `${settlementCode} ` : '';
            settlementAmountEl.textContent = order.settlement_amount !== undefined && order.settlement_amount !== null && order.settlement_amount !== ''
                ? `${prefix}${order.settlement_amount}`
                : '—';
        }

        viewModalInstance.show();
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

    [outcomeSelect, courierSelect, dateFromInput, dateToInput].forEach((el) => {
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
            const nextOutcome = (card.dataset.outcomeFilter || '').toUpperCase();
            if (!outcomeSelect) {
                return;
            }

            if (outcomeSelect.value === nextOutcome) {
                outcomeSelect.value = '';
            } else {
                outcomeSelect.value = nextOutcome;
            }

            readFiltersFromForm();
            state.page = 1;
            refreshTable();
        });
    });

    if (paginationHost) {
        paginationHost.addEventListener('click', (event) => {
            const btn = event.target.closest('.completed-page-btn');
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

    if (tableBody) {
        tableBody.addEventListener('click', (event) => {
            const viewBtn = event.target.closest('.completed-order-view-btn');
            if (!viewBtn) {
                return;
            }
            const orderNumber = viewBtn.dataset.orderNumber || '';
            const order = workspaceOrders.find((row) => String(row.order_number) === String(orderNumber));
            if (order) {
                openViewModal(order);
            }
        });
    }

    readFiltersFromForm();
    refreshTable();
})();
</script>
