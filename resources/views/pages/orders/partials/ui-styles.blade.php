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

    /* Print Orders: Courier | Product | Warranty | Reset on one desktop row */
    .supplier-orders-ui .advanced-filters.print-orders-filters {
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 1.35fr) minmax(180px, max-content) minmax(132px, max-content);
        align-items: end;
        column-gap: 0.75rem;
        row-gap: 0.75rem;
    }

    .supplier-orders-ui .advanced-filters.print-orders-filters .print-orders-filter-warranty .form-check {
        min-height: 38px;
        display: flex;
        align-items: center;
        padding-top: 0;
        margin-bottom: 0.125rem;
    }

    .supplier-orders-ui .advanced-filters.print-orders-filters .print-orders-filter-reset .btn {
        white-space: nowrap;
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

    .supplier-orders-ui .print-action-link {
        color: #ef4923;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.3;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }

    .supplier-orders-ui .print-action-link:hover,
    .supplier-orders-ui .print-action-link:focus-visible {
        color: #c53716;
        font-weight: 700;
        text-decoration: underline;
    }

    .supplier-orders-ui .print-action-link.is-disabled,
    .supplier-orders-ui .print-action-link[aria-disabled="true"] {
        color: #94a3b8;
        font-weight: 600;
        text-decoration: none;
        cursor: not-allowed;
        pointer-events: none;
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
        color: #475569;
        white-space: nowrap;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.7rem 0.65rem;
        background-color: #fde5e0;
    }

    .supplier-orders-ui .orders-table tbody td {
        padding: 0.8rem 0.65rem;
        vertical-align: top;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        background-color: #fff;
    }

    .supplier-orders-ui .orders-table tbody tr:hover td {
        background-color: #f8fafc;
    }

    .supplier-orders-ui .orders-table tbody tr.is-selected td {
        background-color: rgba(239, 73, 35, 0.06);
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
    .supplier-orders-ui .badge-status.is-packing,
    .supplier-orders-ui .badge-status.is-dispatched {
        background: #cfe2ff;
        color: #084298;
    }

    .supplier-orders-ui .badge-status.is-delivered {
        background: #d1e7dd;
        color: #0f5132;
    }

    .supplier-orders-ui .badge-status.is-returned {
        background: #fff3cd;
        color: #664d03;
    }

    .supplier-orders-ui .badge-status.is-payment-pending {
        background: #fff3cd;
        color: #664d03;
    }

    .supplier-orders-ui .badge-status.is-payment-received {
        background: #d1e7dd;
        color: #0f5132;
    }

    .supplier-orders-ui .badge-status.is-payment-paid {
        background: #cfe2ff;
        color: #084298;
    }

    .supplier-orders-ui .badge-status.is-settlement-pending {
        background: #fff3cd;
        color: #664d03;
    }

    .supplier-orders-ui .badge-status.is-settled {
        background: #d1e7dd;
        color: #0f5132;
    }

    .supplier-orders-ui .dispatch-summary-cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .supplier-orders-ui .dispatch-summary-cards.dispatch-orders-summary-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .supplier-orders-ui .dispatch-summary-cards.order-payments-summary-cards,
    .supplier-orders-ui .dispatch-summary-cards.completed-orders-summary-cards {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .supplier-orders-ui .dispatch-summary-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #fff;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        text-align: left;
        width: 100%;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .supplier-orders-ui .dispatch-summary-card:hover,
    .supplier-orders-ui .dispatch-summary-card.is-active {
        border-color: rgba(239, 73, 35, 0.35);
        box-shadow: 0 0 0 1px rgba(239, 73, 35, 0.12);
    }

    .supplier-orders-ui .dispatch-summary-card .summary-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.35rem;
    }

    .supplier-orders-ui .dispatch-summary-card .summary-count {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
        margin: 0;
    }

    .supplier-orders-ui .dispatch-summary-card .summary-count.summary-count-sm {
        font-size: 1.15rem;
    }

    .supplier-orders-ui .dispatch-summary-card .summary-supporting {
        font-size: 12px;
        color: #64748b;
        margin-top: 0.35rem;
        line-height: 1.35;
    }

    .supplier-orders-ui .dispatch-summary-card.is-static {
        cursor: default;
    }

    .supplier-orders-ui .dispatch-summary-card.is-static:hover {
        border-color: rgba(15, 23, 42, 0.08);
        box-shadow: none;
    }

    @media (max-width: 1199.98px) {
        .supplier-orders-ui .dispatch-summary-cards.order-payments-summary-cards,
        .supplier-orders-ui .dispatch-summary-cards.completed-orders-summary-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .supplier-orders-ui .dispatch-summary-cards,
        .supplier-orders-ui .dispatch-summary-cards.order-payments-summary-cards,
        .supplier-orders-ui .dispatch-summary-cards.completed-orders-summary-cards {
            grid-template-columns: 1fr;
        }
    }

    .supplier-orders-ui .dispatch-note-callout {
        background: #f8fafc;
    }

    .supplier-orders-ui .advanced-filters.dispatch-summary-history-filters {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .supplier-orders-ui .dispatch-summary-preview-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 10px;
        background: #fafbfc;
    }

    .supplier-orders-ui .dispatch-summary-preview-strip .preview-label {
        display: block;
        font-size: 11px;
        font-weight: 650;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        margin-bottom: 0.2rem;
    }

    .supplier-orders-ui .dispatch-summary-preview-strip .preview-value {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
    }

    .supplier-orders-ui .dispatch-summary-modal-table-wrap {
        max-height: min(42vh, 360px);
        overflow: auto;
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 8px;
    }

    .supplier-orders-ui .dispatch-summary-modal-table-wrap .orders-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    @media (max-width: 767.98px) {
        .supplier-orders-ui .dispatch-summary-preview-strip {
            grid-template-columns: 1fr;
        }
    }

    .supplier-orders-ui .order-ui-select-col {
        width: 2.25rem;
        text-align: center;
        vertical-align: middle !important;
    }

    .supplier-orders-ui .dispatch-return-damaged-check {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 0.8rem;
        border: 1px solid rgba(15, 23, 42, 0.14);
        border-radius: 8px;
        background: #fff;
        white-space: normal;
        max-width: 100%;
        margin: 0;
        cursor: pointer;
        float: none;
        min-height: 0;
        padding-left: 0.8rem;
    }

    .supplier-orders-ui .dispatch-return-damaged-check .form-check-input {
        position: static;
        float: none;
        margin: 0;
        flex: 0 0 auto;
        width: 1.1em;
        height: 1.1em;
    }

    .supplier-orders-ui .dispatch-return-damaged-check > span {
        line-height: 1.3;
    }

    .supplier-orders-ui .dispatch-return-damaged-check.is-checked {
        border-color: #ef4923;
        background: rgba(239, 73, 35, 0.08);
        color: #9a2a12;
    }

    .supplier-orders-ui .dispatch-return-damaged-check.is-checked .form-check-input {
        background-color: #ef4923;
        border-color: #ef4923;
    }

    .supplier-orders-ui .order-ui-scan-zone {
        border: 1px dashed rgba(15, 23, 42, 0.18);
        border-radius: 10px;
        background: #f8fafc;
        padding: 2rem 1.25rem;
        text-align: center;
    }

    .supplier-orders-ui .order-ui-scan-zone:focus-within {
        border-color: #ef4923;
        background: rgba(239, 73, 35, 0.06);
    }

    .supplier-orders-ui .order-ui-scan-zone .material-symbols-outlined {
        font-size: 42px;
        color: #ef4923;
        margin-bottom: 0.75rem;
    }

    .supplier-orders-ui .order-ui-scan-input-wrap {
        max-width: 420px;
        width: 100%;
        margin: 0 auto;
    }

    .supplier-orders-ui .order-ui-scan-input-wrap input {
        height: 48px;
        font-size: 16px;
        text-align: center;
    }

    .supplier-orders-ui .order-ui-scan-status {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 1.25rem;
    }

    @media (max-width: 1199.98px) {
        .supplier-orders-ui .advanced-filters,
        .supplier-orders-ui .advanced-filters.is-compact {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters {
            display: flex;
            flex-wrap: nowrap;
            align-items: end;
            gap: 0.75rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 0.15rem;
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters > .print-orders-filter-field {
            flex: 0 0 auto;
            min-width: 168px;
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters > .print-orders-filter-warranty {
            min-width: 190px;
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters > .print-orders-filter-reset {
            min-width: 140px;
        }
    }

    @media (max-width: 575.98px) {
        .supplier-orders-ui .advanced-filters,
        .supplier-orders-ui .advanced-filters.is-compact {
            grid-template-columns: 1fr;
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters {
            flex-wrap: wrap;
            overflow-x: visible;
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters > .print-orders-filter-field {
            flex: 1 1 calc(50% - 0.75rem);
            min-width: 140px;
        }

        .supplier-orders-ui .advanced-filters.print-orders-filters > .print-orders-filter-warranty,
        .supplier-orders-ui .advanced-filters.print-orders-filters > .print-orders-filter-reset {
            flex: 1 1 100%;
        }
    }
</style>
