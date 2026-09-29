/**
 * Shared supplier invoice print runtime for screen preview and iframe printing.
 * Depends on window.SupplierCode128.toSvg for barcode rendering.
 */
(function (global) {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function barcodeSvg(value, options) {
        if (!value) {
            return '';
        }

        var opts = Object.assign({ height: 48, moduleWidth: 1.6, showText: false }, options || {});

        if (global.SupplierCode128 && typeof global.SupplierCode128.toSvg === 'function') {
            return global.SupplierCode128.toSvg(String(value), opts);
        }

        return '<div class="print-barcode-fallback">' + escapeHtml(value) + '</div>';
    }

    function formatInvoiceMoney(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        var raw = String(value).trim();
        if (/[a-zA-Z]/.test(raw)) {
            return raw;
        }

        return 'Rs. ' + raw;
    }

    function formatInvoiceLinePrice(item) {
        var unitRaw = item && item.unit_selling_price;
        var qty = parseInt(item && item.quantity, 10);

        if (unitRaw === null || unitRaw === undefined || unitRaw === '') {
            return '—';
        }

        var unit = parseFloat(String(unitRaw).replace(/,/g, ''));
        if (!Number.isFinite(unit) || !Number.isFinite(qty) || qty <= 0) {
            return formatInvoiceMoney(unitRaw);
        }

        var lineTotal = Math.round(unit * qty * 100) / 100;
        return formatInvoiceMoney(lineTotal.toFixed(2));
    }

    function formatInvoiceItemLabel(item) {
        var name = (item && item.product_name || 'Product').toString();
        var variant = (item && item.variant || '').toString().trim();
        var qty = item && item.quantity != null ? item.quantity : '—';

        if (variant) {
            return name + ' (' + variant + ') x ' + qty;
        }

        return name + ' x ' + qty;
    }

    function formatInvoiceDate(value) {
        if (!value) {
            return '—';
        }

        var raw = String(value).trim();
        var match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return raw;
        }

        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var monthIndex = parseInt(match[2], 10) - 1;
        var day = parseInt(match[3], 10);
        var month = months[monthIndex] || match[2];

        return day + ' ' + month + ' ' + match[1];
    }

    function invoiceBarcodeBlock(value, options) {
        if (!value) {
            return '';
        }

        var opts = options || {};
        var className = opts.className ? ' ' + opts.className : '';
        var showNumber = opts.showNumber !== false;

        return (
            '<div class="barcode-container' + className + '">' +
                barcodeSvg(value, opts.svg || null) +
                (showNumber ? '<div class="barcode-number">' + escapeHtml(value) + '</div>' : '') +
            '</div>'
        );
    }

    function getPrintSurface() {
        var surface = document.getElementById('supplierInvoicePrintSurface');
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
        var surface = getPrintSurface();
        if (surface) {
            surface.innerHTML = '';
        }
        document.body.classList.remove('supplier-invoice-print-active');
    }

    function showPrintSurface(title, bodyHtml, autoPrint) {
        var surface = getPrintSurface();

        surface.innerHTML =
            '<div class="supplier-invoice-print-toolbar">' +
                '<strong>' + escapeHtml(title) + '</strong>' +
                '<span class="text-muted" id="supplierInvoicePrintCount"></span>' +
                '<button type="button" class="btn btn-primary btn-sm text-white" id="supplierInvoicePrintAgainBtn">Print</button>' +
                '<button type="button" class="btn btn-light border btn-sm" id="supplierInvoicePrintCloseBtn">Close</button>' +
            '</div>' +
            '<div class="supplier-invoice-print-documents">' +
                bodyHtml +
            '</div>';

        var pageCount = surface.querySelectorAll('.invoice-page').length;
        var countEl = document.getElementById('supplierInvoicePrintCount');
        if (countEl) {
            countEl.textContent = pageCount === 1
                ? '1 invoice'
                : pageCount + ' invoices';
        }

        document.body.classList.add('supplier-invoice-print-active');

        var printAgainBtn = document.getElementById('supplierInvoicePrintAgainBtn');
        var closeBtn = document.getElementById('supplierInvoicePrintCloseBtn');

        var triggerPrint = function () {
            // Prefer printing through a dedicated iframe document so A5 page-breaks
            // work reliably for multi-invoice batches.
            printViaIframe(title, bodyHtml);
        };

        if (printAgainBtn) {
            printAgainBtn.addEventListener('click', triggerPrint);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closePrintSurface);
        }

        if (autoPrint !== false) {
            global.requestAnimationFrame(function () {
                global.requestAnimationFrame(function () {
                    global.setTimeout(triggerPrint, 200);
                });
            });
        }

        return pageCount;
    }

    function buildPrintDocumentHtml(title, bodyHtml) {
        return '<!DOCTYPE html>\n' +
'<html lang="en">\n' +
'<head>\n' +
'    <meta charset="utf-8">\n' +
'    <title>' + escapeHtml(title) + '</title>\n' +
'    <style>\n' +
'        @page { size: A5 portrait; margin: 5mm; }\n' +
'        * { box-sizing: border-box; }\n' +
'        html, body {\n' +
'            margin: 0;\n' +
'            padding: 0;\n' +
'            background: #fff;\n' +
'            color: #111;\n' +
'            font-family: Arial, Helvetica, sans-serif;\n' +
'            font-size: 10px;\n' +
'            line-height: 1.25;\n' +
'        }\n' +
'        .invoice-page {\n' +
'            page-break-after: always;\n' +
'            break-after: page;\n' +
'            break-inside: avoid;\n' +
'            page-break-inside: avoid;\n' +
'            padding: 0;\n' +
'            margin: 0;\n' +
'        }\n' +
'        .invoice-page:last-child {\n' +
'            page-break-after: auto;\n' +
'            break-after: auto;\n' +
'        }\n' +
'        .inv-header {\n' +
'            display: flex;\n' +
'            justify-content: space-between;\n' +
'            align-items: center;\n' +
'            gap: 4mm;\n' +
'            background: #000;\n' +
'            color: #fff;\n' +
'            padding: 3.5mm 4mm;\n' +
'            margin-bottom: 2.5mm;\n' +
'            -webkit-print-color-adjust: exact;\n' +
'            print-color-adjust: exact;\n' +
'        }\n' +
'        .inv-supplier {\n' +
'            flex: 1 1 auto;\n' +
'            min-width: 0;\n' +
'            text-align: left;\n' +
'            font-size: 22px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.2px;\n' +
'            text-transform: uppercase;\n' +
'            line-height: 1.15;\n' +
'            word-break: break-word;\n' +
'            color: #fff;\n' +
'        }\n' +
'        .inv-care {\n' +
'            flex: 0 0 auto;\n' +
'            margin-top: 0;\n' +
'            text-align: right;\n' +
'            color: #fff;\n' +
'        }\n' +
'        .inv-care-label {\n' +
'            display: block;\n' +
'            font-size: 11px;\n' +
'            font-weight: 700;\n' +
'            letter-spacing: 0.6px;\n' +
'            text-transform: uppercase;\n' +
'            margin-bottom: 0.6mm;\n' +
'            color: #fff;\n' +
'        }\n' +
'        .inv-care-number {\n' +
'            font-size: 20px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.4px;\n' +
'            line-height: 1.1;\n' +
'            color: #fff;\n' +
'        }\n' +
'        .inv-meta {\n' +
'            display: flex;\n' +
'            justify-content: space-between;\n' +
'            align-items: flex-start;\n' +
'            gap: 4mm;\n' +
'            padding: 1.6mm 0 2mm;\n' +
'            border-bottom: 1px solid #111;\n' +
'            margin-bottom: 2mm;\n' +
'        }\n' +
'        .inv-meta-line {\n' +
'            flex: 1;\n' +
'            min-width: 0;\n' +
'            font-size: 15px;\n' +
'            font-weight: 700;\n' +
'            line-height: 1.25;\n' +
'            word-break: break-word;\n' +
'        }\n' +
'        .inv-meta-line.is-end { text-align: right; }\n' +
'        .inv-meta-label {\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.3px;\n' +
'            text-transform: uppercase;\n' +
'        }\n' +
'        .inv-label {\n' +
'            display: block;\n' +
'            font-size: 7.5px;\n' +
'            font-weight: 700;\n' +
'            letter-spacing: 0.6px;\n' +
'            text-transform: uppercase;\n' +
'            margin-bottom: 0.4mm;\n' +
'            color: #111;\n' +
'        }\n' +
'        .inv-value {\n' +
'            font-size: 11px;\n' +
'            font-weight: 700;\n' +
'            word-break: break-word;\n' +
'        }\n' +
'        .inv-value-lg {\n' +
'            font-size: 12px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.3px;\n' +
'            word-break: break-word;\n' +
'        }\n' +
'        .inv-section {\n' +
'            margin: 0 0 1.5mm;\n' +
'            padding: 0 0 1.5mm;\n' +
'            border-bottom: 1px solid #111;\n' +
'        }\n' +
'        .inv-section:last-child {\n' +
'            border-bottom: 0;\n' +
'            margin-bottom: 0;\n' +
'            padding-bottom: 0;\n' +
'        }\n' +
'        .inv-section-title {\n' +
'            display: block;\n' +
'            background: #111;\n' +
'            color: #fff;\n' +
'            font-size: 8px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.7px;\n' +
'            text-transform: uppercase;\n' +
'            padding: 1mm 1.5mm;\n' +
'            margin-bottom: 1.5mm;\n' +
'            -webkit-print-color-adjust: exact;\n' +
'            print-color-adjust: exact;\n' +
'        }\n' +
'        .inv-field { margin: 0 0 1.2mm; }\n' +
'        .inv-field:last-child { margin-bottom: 0; }\n' +
'        .inv-field-text {\n' +
'            font-size: 10px;\n' +
'            font-weight: 600;\n' +
'            word-break: break-word;\n' +
'            overflow-wrap: anywhere;\n' +
'        }\n' +
'        .inv-two-col {\n' +
'            display: flex;\n' +
'            gap: 3mm;\n' +
'            margin-top: 1mm;\n' +
'        }\n' +
'        .inv-two-col > * { flex: 1; min-width: 0; }\n' +
'        .inv-customer-line {\n' +
'            font-size: 17px;\n' +
'            font-weight: 700;\n' +
'            line-height: 1.25;\n' +
'            margin: 0 0 1.4mm;\n' +
'            word-break: break-word;\n' +
'            overflow-wrap: anywhere;\n' +
'        }\n' +
'        .inv-customer-split {\n' +
'            display: flex;\n' +
'            gap: 4mm;\n' +
'            align-items: flex-start;\n' +
'            margin-top: 0.6mm;\n' +
'            padding-top: 1.6mm;\n' +
'            border-top: 1px solid #111;\n' +
'        }\n' +
'        .inv-customer-split > .inv-customer-line {\n' +
'            flex: 1;\n' +
'            min-width: 0;\n' +
'            margin-bottom: 0;\n' +
'            font-size: 16px;\n' +
'        }\n' +
'        .inv-waybill .inv-value-lg {\n' +
'            font-size: 13px;\n' +
'            letter-spacing: 0.5px;\n' +
'        }\n' +
'        .inv-delivery-body {\n' +
'            display: flex;\n' +
'            align-items: stretch;\n' +
'            gap: 3mm;\n' +
'        }\n' +
'        .inv-delivery-left {\n' +
'            flex: 1 1 48%;\n' +
'            min-width: 0;\n' +
'        }\n' +
'        .inv-delivery-right {\n' +
'            flex: 1 1 52%;\n' +
'            min-width: 0;\n' +
'            display: flex;\n' +
'            align-items: center;\n' +
'            justify-content: center;\n' +
'        }\n' +
'        .barcode-container.is-waybill {\n' +
'            width: 100%;\n' +
'            margin: 0;\n' +
'            padding: 1mm 1.5mm;\n' +
'        }\n' +
'        .barcode-container.is-waybill svg {\n' +
'            width: 100%;\n' +
'            max-width: 100%;\n' +
'            height: 14mm;\n' +
'        }\n' +
'        .barcode-container.is-waybill .barcode-number {\n' +
'            margin-top: 1mm;\n' +
'            font-size: 10px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.5px;\n' +
'        }\n' +
'        .inv-item {\n' +
'            display: flex;\n' +
'            justify-content: space-between;\n' +
'            align-items: baseline;\n' +
'            gap: 3mm;\n' +
'            padding: 0.8mm 0;\n' +
'            border-bottom: 1px solid #333;\n' +
'            font-size: 14px;\n' +
'            font-weight: 700;\n' +
'            line-height: 1.25;\n' +
'        }\n' +
'        .inv-item:last-child {\n' +
'            border-bottom: 0;\n' +
'            padding-bottom: 0;\n' +
'        }\n' +
'        .inv-item:first-child { padding-top: 0; }\n' +
'        .inv-item-desc {\n' +
'            flex: 1 1 auto;\n' +
'            min-width: 0;\n' +
'            word-break: break-word;\n' +
'            overflow-wrap: anywhere;\n' +
'        }\n' +
'        .inv-item-price {\n' +
'            flex: 0 0 auto;\n' +
'            white-space: nowrap;\n' +
'            font-weight: 800;\n' +
'        }\n' +
'        .inv-charges { margin-top: 1.5mm; }\n' +
'        .inv-charge-row {\n' +
'            display: flex;\n' +
'            justify-content: space-between;\n' +
'            gap: 2mm;\n' +
'            font-size: 13px;\n' +
'            font-weight: 600;\n' +
'            margin: 0.6mm 0;\n' +
'        }\n' +
'        .inv-charge-row.is-total {\n' +
'            margin-top: 1mm;\n' +
'            padding-top: 1mm;\n' +
'            border-top: 1.5px solid #111;\n' +
'            font-size: 15px;\n' +
'            font-weight: 800;\n' +
'        }\n' +
'        .inv-invoice-line {\n' +
'            font-size: 16px;\n' +
'            font-weight: 800;\n' +
'            line-height: 1.25;\n' +
'            word-break: break-word;\n' +
'        }\n' +
'        .inv-invoice-label {\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.3px;\n' +
'            text-transform: uppercase;\n' +
'        }\n' +
'        .inv-invoice-barcode {\n' +
'            flex: 1 1 54%;\n' +
'            min-width: 0;\n' +
'            display: flex;\n' +
'            align-items: center;\n' +
'            justify-content: flex-end;\n' +
'        }\n' +
'        .barcode-container.is-invoice {\n' +
'            width: 100%;\n' +
'            margin: 0;\n' +
'            padding: 0;\n' +
'            align-items: flex-end;\n' +
'        }\n' +
'        .barcode-container.is-invoice svg {\n' +
'            width: 100%;\n' +
'            max-width: 100%;\n' +
'            height: 14mm;\n' +
'        }\n' +
'        .barcode-container {\n' +
'            display: flex;\n' +
'            flex-direction: column;\n' +
'            align-items: center;\n' +
'            justify-content: center;\n' +
'            margin: 1mm 0 0.5mm;\n' +
'            padding: 1.5mm 2mm;\n' +
'        }\n' +
'        .barcode-container svg {\n' +
'            width: 100%;\n' +
'            max-width: 100mm;\n' +
'            height: 11mm;\n' +
'        }\n' +
'        .barcode-number {\n' +
'            margin-top: 1mm;\n' +
'            font-size: 9px;\n' +
'            font-weight: 700;\n' +
'            letter-spacing: 0.4px;\n' +
'        }\n' +
'        .print-barcode-fallback {\n' +
'            font-family: monospace;\n' +
'            letter-spacing: 2px;\n' +
'            border: 1px solid #000;\n' +
'            display: inline-block;\n' +
'            padding: 4px 8px;\n' +
'            font-weight: 700;\n' +
'        }\n' +
'        .inv-payment {\n' +
'            margin-top: 0.5mm;\n' +
'            border: 1.5px solid #111;\n' +
'            padding: 1.5mm 2mm;\n' +
'            text-align: center;\n' +
'        }\n' +
'        .inv-payment-label {\n' +
'            display: block;\n' +
'            font-size: 8px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.9px;\n' +
'            text-transform: uppercase;\n' +
'            margin-bottom: 1mm;\n' +
'        }\n' +
'        .inv-payment.is-cod {\n' +
'            background: #000;\n' +
'            color: #fff;\n' +
'            border-color: #000;\n' +
'            padding: 2.5mm 3mm;\n' +
'            -webkit-print-color-adjust: exact;\n' +
'            print-color-adjust: exact;\n' +
'        }\n' +
'        .inv-payment-line {\n' +
'            display: block;\n' +
'            font-size: 22px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.2px;\n' +
'            line-height: 1.15;\n' +
'            color: #fff;\n' +
'        }\n' +
'        .inv-payment-amount {\n' +
'            font-size: 16px;\n' +
'            font-weight: 800;\n' +
'            letter-spacing: 0.3px;\n' +
'            line-height: 1.05;\n' +
'        }\n' +
'        .inv-payment-sub {\n' +
'            margin-top: 0.8mm;\n' +
'            font-size: 10px;\n' +
'            font-weight: 700;\n' +
'        }\n' +
'        .invoice-header {\n' +
'            text-align: center;\n' +
'            font-size: 16px;\n' +
'            font-weight: 700;\n' +
'            margin-bottom: 4px;\n' +
'        }\n' +
'        .invoice-sub { text-align: center; margin-bottom: 10px; }\n' +
'        .invoice-rule {\n' +
'            border: 0;\n' +
'            border-top: 1px solid #222;\n' +
'            margin: 10px 0;\n' +
'        }\n' +
'        .invoice-row { margin: 3px 0; }\n' +
'        .barcode-block { margin: 8px 0; text-align: center; }\n' +
'        .barcode-block svg { max-width: 100%; height: auto; }\n' +
'        .item-list-product { margin: 14px 0 6px; font-weight: 700; font-size: 15px; }\n' +
'        .item-list-variant { margin: 4px 0 4px 14px; }\n' +
'        .item-list-meta { color: #333; font-size: 12px; margin-left: 14px; }\n' +
'    </style>\n' +
'</head>\n' +
'<body>\n' +
bodyHtml +
'\n</body>\n' +
'</html>';
    }

    function printViaIframe(title, bodyHtml) {
        var iframe = document.getElementById('supplierInvoicePrintFrame');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'supplierInvoicePrintFrame';
            iframe.setAttribute('title', 'Invoice print frame');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(iframe);
        }

        var frameWindow = iframe.contentWindow;
        var frameDocument = frameWindow ? frameWindow.document : null;
        if (!frameWindow || !frameDocument) {
            global.print();
            return;
        }

        var printed = false;
        var runPrint = function () {
            if (printed) {
                return;
            }
            printed = true;
            iframe.onload = null;
            try {
                frameWindow.focus();
                frameWindow.print();
            } catch (error) {
                global.print();
            }
        };

        frameDocument.open();
        frameDocument.write(buildPrintDocumentHtml(title, bodyHtml));
        frameDocument.close();

        // document.close() can mark the frame complete and also fire load.
        // Either signal is enough; the guard above ignores the second one.
        iframe.onload = function () {
            global.setTimeout(runPrint, 50);
        };

        if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
            global.setTimeout(runPrint, 50);
        } else {
            global.setTimeout(runPrint, 250);
        }
    }

    function renderInvoiceDocuments(payloads) {
        return payloads.map(function (payload) {
            var order = payload.order || {};
            var supplier = payload.supplier || {};
            var customerCare = payload.customer_care || {};
            var courier = payload.courier || {};
            var customer = payload.customer || {};
            var charges = payload.charges || {};
            var payment = order.payment || {};
            var items = Array.isArray(payload.items) ? payload.items : [];
            var district = courier.district || courier.state || '—';
            var contacts = [customer.contact_1, customer.contact_2]
                .filter(function (value) { return value; })
                .map(function (value) { return escapeHtml(value); })
                .join(' | ') || '—';
            var invoiceNumber = order.invoice_number || '';
            var isPaymentCollected = payment.is_payment_collected || charges.is_payment_collected;
            var productTotal = charges.items_subtotal;
            var deliveryCharge = charges.courier_fee;
            var payableTotal = charges.customer_payable_amount
                || payment.cod_amount
                || charges.cod_amount
                || '—';
            var codAmount = payment.cod_amount || charges.cod_amount || charges.customer_payable_amount || '—';

            var paymentBlock = isPaymentCollected
                ? '<div class="inv-payment">' +
                        '<span class="inv-payment-label">Payment Collected</span>' +
                   '</div>'
                : '<div class="inv-payment is-cod">' +
                        '<span class="inv-payment-line">COD AMOUNT : ' + escapeHtml(formatInvoiceMoney(codAmount)) + '</span>' +
                   '</div>';

            var itemsHtml = items.length
                ? items.map(function (item) {
                    return (
                        '<div class="inv-item">' +
                            '<span class="inv-item-desc">' + escapeHtml(formatInvoiceItemLabel(item)) + '</span>' +
                            '<span class="inv-item-price">' + escapeHtml(formatInvoiceLinePrice(item)) + '</span>' +
                        '</div>'
                    );
                }).join('')
                : '<div class="inv-field-text">No items</div>';

            return (
                '<section class="invoice-page">' +
                    '<header class="inv-header">' +
                        '<div class="inv-supplier">' + escapeHtml(supplier.company_name || '—') + '</div>' +
                        '<div class="inv-care">' +
                            '<span class="inv-care-label">Customer Care</span>' +
                            '<div class="inv-care-number">' + escapeHtml(customerCare.reseller_company_phone || '—') + '</div>' +
                        '</div>' +
                    '</header>' +

                    '<div class="inv-meta">' +
                        '<div class="inv-meta-line">' +
                            '<span class="inv-meta-label">Company Name</span> : ' + escapeHtml(order.reseller_company_name || '—') +
                        '</div>' +
                        '<div class="inv-meta-line is-end">' +
                            '<span class="inv-meta-label">Issued Date</span> : ' + escapeHtml(formatInvoiceDate(order.issued_date)) +
                        '</div>' +
                    '</div>' +

                    '<section class="inv-section">' +
                        '<span class="inv-section-title">Delivery Information</span>' +
                        '<div class="inv-delivery-body">' +
                            '<div class="inv-delivery-left">' +
                                '<div class="inv-field">' +
                                    '<span class="inv-label">Delivery Partner</span>' +
                                    '<div class="inv-value">' + escapeHtml(courier.label || courier.name || '—') + '</div>' +
                                '</div>' +
                                '<div class="inv-field inv-waybill">' +
                                    '<span class="inv-label">Waybill</span>' +
                                    '<div class="inv-value-lg">' + escapeHtml(courier.tracking_number || '—') + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="inv-delivery-right">' +
                                invoiceBarcodeBlock(courier.tracking_number, {
                                    className: 'is-waybill',
                                    showNumber: false,
                                    svg: { height: 54, moduleWidth: 1.9, showText: false },
                                }) +
                            '</div>' +
                        '</div>' +
                    '</section>' +

                    '<section class="inv-section">' +
                        '<span class="inv-section-title">Customer</span>' +
                        '<div class="inv-customer-line">NAME : ' + escapeHtml(customer.name || '—') + '</div>' +
                        '<div class="inv-customer-line">ADDRESS : ' + escapeHtml(customer.address || '—') + '</div>' +
                        '<div class="inv-customer-line">Contact : ' + contacts + '</div>' +
                        '<div class="inv-customer-split">' +
                            '<div class="inv-customer-line">NEAREST CITY : ' + escapeHtml(courier.city || '—') + '</div>' +
                            '<div class="inv-customer-line">DISTRICT : ' + escapeHtml(district) + '</div>' +
                        '</div>' +
                    '</section>' +

                    '<section class="inv-section">' +
                        '<span class="inv-section-title">Order Items</span>' +
                        itemsHtml +
                        '<div class="inv-charges">' +
                            '<div class="inv-charge-row">' +
                                '<span>Product Total</span>' +
                                '<span>' + escapeHtml(formatInvoiceMoney(productTotal)) + '</span>' +
                            '</div>' +
                            '<div class="inv-charge-row">' +
                                '<span>Delivery Charge</span>' +
                                '<span>' + escapeHtml(formatInvoiceMoney(deliveryCharge)) + '</span>' +
                            '</div>' +
                            '<div class="inv-charge-row is-total">' +
                                '<span>Total</span>' +
                                '<span>' + escapeHtml(formatInvoiceMoney(payableTotal)) + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</section>' +

                    '<section class="inv-section">' +
                        '<span class="inv-section-title">Invoice</span>' +
                        '<div class="inv-invoice-line">' +
                            '<span class="inv-invoice-label">Invoice Number</span> : ' + escapeHtml(invoiceNumber || '—') +
                        '</div>' +
                    '</section>' +

                    '<section class="inv-section">' +
                        paymentBlock +
                    '</section>' +
                '</section>'
            );
        }).join('');
    }

    global.SupplierInvoicePrint = {
        showDocuments: function (title, payloads, options) {
            var opts = options || {};
            var autoPrint = opts.autoPrint !== false;
            var bodyHtml = renderInvoiceDocuments(payloads || []);
            return showPrintSurface(title, bodyHtml, autoPrint);
        },
        close: closePrintSurface,
        renderDocuments: renderInvoiceDocuments,
    };
})(window);
