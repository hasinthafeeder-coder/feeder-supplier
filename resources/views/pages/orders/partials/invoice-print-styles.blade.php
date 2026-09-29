<style>
    #supplierInvoicePrintSurface {
        display: none;
    }

    body.supplier-invoice-print-active {
        overflow: hidden;
    }

    body.supplier-invoice-print-active #supplierInvoicePrintSurface {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 2000;
        overflow: auto;
        background: #f3f3f3;
    }

    body.supplier-invoice-print-active .supplier-invoice-print-app {
        display: none !important;
    }

    #supplierInvoicePrintSurface .supplier-invoice-print-toolbar {
        position: sticky;
        top: 0;
        z-index: 1;
        display: flex;
        gap: 0.5rem;
        align-items: center;
        padding: 12px 16px;
        background: #fff;
        border-bottom: 1px solid #ddd;
    }

    /* —— A5 invoice document (screen preview) —— */
    #supplierInvoicePrintSurface .invoice-page {
        page-break-after: always;
        break-after: page;
        width: 148mm;
        min-height: 210mm;
        max-width: 100%;
        padding: 3.5mm 3.5mm 2.5mm;
        margin: 12px auto;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        line-height: 1.25;
        box-sizing: border-box;
    }

    #supplierInvoicePrintSurface .invoice-page:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    #supplierInvoicePrintSurface .inv-header {
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

    #supplierInvoicePrintSurface .inv-supplier {
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

    #supplierInvoicePrintSurface .inv-care {
        flex: 0 0 auto;
        margin-top: 0;
        text-align: right;
        color: #fff;
    }

    #supplierInvoicePrintSurface .inv-care-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        margin-bottom: 0.6mm;
        color: #fff;
    }

    #supplierInvoicePrintSurface .inv-care-number {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 0.4px;
        line-height: 1.1;
        color: #fff;
    }

    #supplierInvoicePrintSurface .inv-meta {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 4mm;
        padding: 1.6mm 0 2mm;
        border-bottom: 1px solid #111;
        margin-bottom: 2mm;
    }

    #supplierInvoicePrintSurface .inv-meta-line {
        flex: 1;
        min-width: 0;
        font-size: 15px;
        font-weight: 700;
        line-height: 1.25;
        word-break: break-word;
    }

    #supplierInvoicePrintSurface .inv-meta-line.is-end {
        text-align: right;
    }

    #supplierInvoicePrintSurface .inv-meta-label {
        font-weight: 800;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    #supplierInvoicePrintSurface .inv-label {
        display: block;
        font-size: 7.5px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        margin-bottom: 0.4mm;
        color: #111;
    }

    #supplierInvoicePrintSurface .inv-value {
        font-size: 11px;
        font-weight: 700;
        word-break: break-word;
    }

    #supplierInvoicePrintSurface .inv-value-lg {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.3px;
        word-break: break-word;
    }

    #supplierInvoicePrintSurface .inv-section {
        margin: 0 0 1.5mm;
        padding: 0 0 1.5mm;
        border-bottom: 1px solid #111;
    }

    #supplierInvoicePrintSurface .inv-section:last-child {
        border-bottom: 0;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    #supplierInvoicePrintSurface .inv-section-title {
        display: block;
        background: #111;
        color: #fff;
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        padding: 1mm 1.5mm;
        margin-bottom: 1.5mm;
    }

    #supplierInvoicePrintSurface .inv-field {
        margin: 0 0 1.2mm;
    }

    #supplierInvoicePrintSurface .inv-field:last-child {
        margin-bottom: 0;
    }

    #supplierInvoicePrintSurface .inv-field-text {
        font-size: 10px;
        font-weight: 600;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    #supplierInvoicePrintSurface .inv-two-col {
        display: flex;
        gap: 3mm;
        margin-top: 1mm;
    }

    #supplierInvoicePrintSurface .inv-two-col > * {
        flex: 1;
        min-width: 0;
    }

    #supplierInvoicePrintSurface .inv-customer-details {
        display: grid;
        grid-template-columns: 18mm 1fr;
        column-gap: 2.5mm;
        row-gap: 2mm;
        align-items: baseline;
    }

    #supplierInvoicePrintSurface .inv-customer-details > .inv-customer-line {
        display: contents;
    }

    #supplierInvoicePrintSurface .inv-customer-line {
        display: grid;
        grid-template-columns: 18mm 1fr;
        column-gap: 2.5mm;
        align-items: baseline;
        margin: 0 0 2mm;
        word-break: break-word;
        overflow-wrap: anywhere;
        line-height: 1.3;
    }

    #supplierInvoicePrintSurface .inv-customer-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        line-height: 1.3;
        white-space: nowrap;
    }

    #supplierInvoicePrintSurface .inv-customer-value {
        display: block;
        min-width: 0;
        word-break: break-word;
        overflow-wrap: anywhere;
        font-weight: 700;
    }

    #supplierInvoicePrintSurface .inv-customer-name .inv-customer-value {
        font-size: 23px;
        line-height: 1.25;
    }

    #supplierInvoicePrintSurface .inv-customer-address .inv-customer-value {
        font-size: 19px;
        line-height: 1.3;
    }

    #supplierInvoicePrintSurface .inv-customer-phone .inv-customer-value {
        font-size: 20px;
        line-height: 1.3;
    }

    #supplierInvoicePrintSurface .inv-customer-city,
    #supplierInvoicePrintSurface .inv-customer-district {
        font-size: 18px;
        font-weight: 600;
        line-height: 1.3;
    }

    #supplierInvoicePrintSurface .inv-customer-city .inv-customer-value,
    #supplierInvoicePrintSurface .inv-customer-district .inv-customer-value {
        font-size: 18px;
        font-weight: 600;
        line-height: 1.3;
    }

    #supplierInvoicePrintSurface .inv-customer-split {
        display: flex;
        gap: 4mm;
        align-items: flex-start;
        margin-top: 1mm;
        padding-top: 2mm;
        border-top: 1px solid #111;
    }

    #supplierInvoicePrintSurface .inv-customer-split > .inv-customer-line {
        flex: 1;
        min-width: 0;
        margin-bottom: 0;
        grid-template-columns: max-content 1fr;
    }

    #supplierInvoicePrintSurface .inv-waybill .inv-value-lg {
        font-size: 13px;
        letter-spacing: 0.5px;
    }

    #supplierInvoicePrintSurface .inv-delivery-body {
        display: flex;
        align-items: stretch;
        gap: 3mm;
    }

    #supplierInvoicePrintSurface .inv-delivery-left {
        flex: 1 1 48%;
        min-width: 0;
    }

    #supplierInvoicePrintSurface .inv-delivery-right {
        flex: 1 1 52%;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #supplierInvoicePrintSurface .barcode-container.is-waybill {
        width: 100%;
        margin: 0;
        padding: 1mm 1.5mm;
    }

    #supplierInvoicePrintSurface .barcode-container.is-waybill svg {
        width: 100%;
        max-width: 100%;
        height: 14mm;
    }

    #supplierInvoicePrintSurface .barcode-container.is-waybill .barcode-number {
        margin-top: 1mm;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    #supplierInvoicePrintSurface .inv-item {
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

    #supplierInvoicePrintSurface .inv-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    #supplierInvoicePrintSurface .inv-item:first-child {
        padding-top: 0;
    }

    #supplierInvoicePrintSurface .inv-item-desc {
        flex: 1 1 auto;
        min-width: 0;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    #supplierInvoicePrintSurface .inv-item-price {
        flex: 0 0 auto;
        white-space: nowrap;
        font-weight: 800;
    }

    #supplierInvoicePrintSurface .inv-charges {
        margin-top: 1.5mm;
    }

    #supplierInvoicePrintSurface .inv-charge-row {
        display: flex;
        justify-content: space-between;
        gap: 2mm;
        font-size: 13px;
        font-weight: 600;
        margin: 0.6mm 0;
    }

    #supplierInvoicePrintSurface .inv-charge-row.is-total {
        margin-top: 1mm;
        padding-top: 1mm;
        border-top: 1.5px solid #111;
        font-size: 15px;
        font-weight: 800;
    }

    #supplierInvoicePrintSurface .inv-invoice-line {
        font-size: 16px;
        font-weight: 800;
        line-height: 1.25;
        word-break: break-word;
    }

    #supplierInvoicePrintSurface .inv-invoice-label {
        font-weight: 800;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    #supplierInvoicePrintSurface .inv-invoice-barcode {
        flex: 1 1 54%;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    #supplierInvoicePrintSurface .barcode-container.is-invoice {
        width: 100%;
        margin: 0;
        padding: 0;
        align-items: flex-end;
    }

    #supplierInvoicePrintSurface .barcode-container.is-invoice svg {
        width: 100%;
        max-width: 100%;
        height: 14mm;
    }

    #supplierInvoicePrintSurface .barcode-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 1mm 0 0.5mm;
        padding: 1.5mm 2mm;
    }

    #supplierInvoicePrintSurface .barcode-container svg {
        width: 100%;
        max-width: 100mm;
        height: 11mm;
    }

    #supplierInvoicePrintSurface .barcode-number {
        margin-top: 1mm;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.4px;
    }

    #supplierInvoicePrintSurface .print-barcode-fallback {
        font-family: monospace;
        letter-spacing: 2px;
        border: 1px solid #000;
        display: inline-block;
        padding: 4px 8px;
        font-weight: 700;
    }

    #supplierInvoicePrintSurface .inv-payment {
        margin-top: 0.5mm;
        border: 1.5px solid #111;
        padding: 1.5mm 2mm;
        text-align: center;
    }

    #supplierInvoicePrintSurface .inv-payment-label {
        display: block;
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.9px;
        text-transform: uppercase;
        margin-bottom: 1mm;
    }

    #supplierInvoicePrintSurface .inv-payment.is-cod {
        background: #000;
        color: #fff;
        border-color: #000;
        padding: 2.5mm 3mm;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    #supplierInvoicePrintSurface .inv-payment-line {
        display: block;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 0.2px;
        line-height: 1.15;
        color: #fff;
    }

    #supplierInvoicePrintSurface .inv-payment-amount {
        font-size: 16px;
        font-weight: 800;
        letter-spacing: 0.3px;
        line-height: 1.05;
    }

    #supplierInvoicePrintSurface .inv-payment-sub {
        margin-top: 0.8mm;
        font-size: 10px;
        font-weight: 700;
    }

    /* Print Item List — A4 centered pick list (no barcodes) */
    #supplierInvoicePrintSurface .supplier-invoice-print-documents.is-a4 .item-list-page {
        width: 210mm;
        max-width: 100%;
        min-height: 297mm;
        margin: 0 auto 16px;
        padding: 12mm 14mm;
        background: #fff;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.12);
    }

    #supplierInvoicePrintSurface .item-list-header {
        text-align: center;
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 6px;
    }

    #supplierInvoicePrintSurface .item-list-sub {
        text-align: center;
        font-size: 13px;
        color: #333;
        margin: 0 0 12px;
    }

    #supplierInvoicePrintSurface .item-list-rule {
        border: 0;
        border-top: 2px solid #111;
        margin: 0 0 16px;
        width: 100%;
    }

    #supplierInvoicePrintSurface .item-list-body {
        text-align: left;
    }

    #supplierInvoicePrintSurface .item-list-line {
        font-size: 15px;
        line-height: 1.55;
        margin: 0 0 8px;
    }

    /* Legacy invoice-header helpers (invoice docs + fallbacks) */
    #supplierInvoicePrintSurface .invoice-header {
        text-align: center;
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    #supplierInvoicePrintSurface .invoice-sub {
        text-align: center;
        margin-bottom: 10px;
    }

    #supplierInvoicePrintSurface .invoice-rule {
        border: 0;
        border-top: 1px solid #222;
        margin: 10px 0;
    }

    #supplierInvoicePrintSurface .invoice-row { margin: 3px 0; }

    @media print {
        @page {
            size: A5 portrait;
            margin: 5mm;
        }

        html, body {
            height: auto !important;
            overflow: visible !important;
            background: #fff !important;
        }

        /* Surface is moved to document.body — hide every other top-level node. */
        body.supplier-invoice-print-active > *:not(#supplierInvoicePrintSurface) {
            display: none !important;
        }

        body.supplier-invoice-print-active #supplierInvoicePrintSurface {
            display: block !important;
            position: static !important;
            inset: auto !important;
            width: auto !important;
            height: auto !important;
            overflow: visible !important;
            background: #fff !important;
            z-index: auto !important;
        }

        body.supplier-invoice-print-active #supplierInvoicePrintSurface .supplier-invoice-print-toolbar {
            display: none !important;
        }

        body.supplier-invoice-print-active #supplierInvoicePrintSurface .supplier-invoice-print-documents {
            display: block !important;
        }

        body.supplier-invoice-print-active #supplierInvoicePrintSurface .invoice-page {
            display: block !important;
            width: auto !important;
            min-height: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            page-break-after: always;
            break-after: page;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        body.supplier-invoice-print-active #supplierInvoicePrintSurface .invoice-page:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        body.supplier-invoice-print-active #supplierInvoicePrintSurface .inv-header,
        body.supplier-invoice-print-active #supplierInvoicePrintSurface .inv-payment.is-cod,
        body.supplier-invoice-print-active #supplierInvoicePrintSurface .inv-section-title {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
