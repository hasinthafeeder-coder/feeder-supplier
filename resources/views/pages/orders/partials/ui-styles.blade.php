{{-- Shared supplier order list UI — mirrors reseller call-center workspace patterns. --}}
<style>
    .supplier-orders-ui .workspace-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-bottom: 1rem;
    }

    .supplier-orders-ui .workspace-tab {
        border: 1px solid rgba(15, 23, 42, 0.1);
        background: #fff;
        color: #334155;
        border-radius: 8px;
        padding: 0.45rem 0.85rem;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
    }

    .supplier-orders-ui .workspace-tab:hover {
        background: #f8fafc;
    }

    .supplier-orders-ui .workspace-tab.is-active {
        background: #ef4923;
        border-color: #ef4923;
        color: #fff;
    }

    .supplier-orders-ui .workspace-tab .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.35rem;
        height: 1.2rem;
        padding: 0 0.35rem;
        margin-left: 0.35rem;
        border-radius: 999px;
        font-size: 11px;
        background: rgba(15, 23, 42, 0.08);
        color: inherit;
    }

    .supplier-orders-ui .workspace-tab.is-active .tab-count {
        background: rgba(255, 255, 255, 0.22);
    }

    .supplier-orders-ui .search-filter-card .search-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        align-items: stretch;
    }

    .supplier-orders-ui .search-filter-card .search-input-wrap {
        flex: 1 1 280px;
        position: relative;
    }

    .supplier-orders-ui .search-filter-card .search-input-wrap .material-symbols-outlined {
        position: absolute;
        left: 0.7rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        color: #94a3b8;
        pointer-events: none;
    }

    .supplier-orders-ui .search-filter-card .search-input-wrap input {
        padding-left: 2.35rem;
    }

    .supplier-orders-ui .advanced-filters {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 0.95rem;
        padding-top: 0.95rem;
        border-top: 1px solid rgba(15, 23, 42, 0.06);
    }

    .supplier-orders-ui .advanced-filters.is-compact {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .supplier-orders-ui .advanced-filters .label {
        display: block;
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 0.3rem;
    }

    .supplier-orders-ui .bulk-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border: 1px solid rgba(239, 73, 35, 0.22);
        background: rgba(239, 73, 35, 0.06);
        border-radius: 10px;
        margin-bottom: 1rem;
    }

    .supplier-orders-ui .bulk-toolbar.hidden,
    .supplier-orders-ui .hidden {
        display: none !important;
    }

    .supplier-orders-ui .bulk-toolbar .bulk-count {
        font-size: 13px;
        font-weight: 600;
        color: #ef4923;
    }

    .supplier-orders-ui .bulk-toolbar .bulk-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .supplier-orders-ui .results-meta {
        font-size: 12px;
        color: #64748b;
    }

    .supplier-orders-ui .orders-table {
        width: 100%;
        margin: 0;
        font-size: 13px;
    }

    .supplier-orders-ui .orders-table thead th {
        font-size: 11px;
        font-weight: 650;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        white-space: nowrap;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.7rem 0.65rem;
        background: #fafbfc;
    }

    .supplier-orders-ui .orders-table tbody td {
        padding: 0.8rem 0.65rem;
        vertical-align: top;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }

    .supplier-orders-ui .orders-table tbody tr:hover {
        background: #f8fafc;
    }

    .supplier-orders-ui .orders-table tbody tr.is-selected {
        background: rgba(239, 73, 35, 0.06);
    }

    .supplier-orders-ui .order-number {
        font-weight: 700;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.25;
    }

    .supplier-orders-ui .order-sub {
        font-size: 12px;
        color: #64748b;
        margin-top: 0.1rem;
    }

    .supplier-orders-ui .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 0.28rem 0.55rem;
        border-radius: 4px;
        line-height: 1.2;
        white-space: nowrap;
        background: #e9ecef;
        color: #41464b;
    }

    .supplier-orders-ui .badge-status.is-fresh {
        background: #d1e7dd;
        color: #0f5132;
    }

    .supplier-orders-ui .badge-status.is-out-of-stock {
        background: #f8d7da;
        color: #842029;
    }

    .supplier-orders-ui .badge-status.is-fifo {
        background: #fff3cd;
        color: #664d03;
    }

    .supplier-orders-ui .badge-status.is-print,
    .supplier-orders-ui .badge-status.is-packing {
        background: #cfe2ff;
        color: #084298;
    }

    .supplier-orders-ui .order-ui-select-col {
        width: 2.25rem;
        text-align: center;
        vertical-align: middle !important;
    }

    @media (max-width: 1199.98px) {
        .supplier-orders-ui .advanced-filters,
        .supplier-orders-ui .advanced-filters.is-compact {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .supplier-orders-ui .advanced-filters,
        .supplier-orders-ui .advanced-filters.is-compact {
            grid-template-columns: 1fr;
        }
    }
</style>
