<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatch Note {{ $note['dispatch_no'] ?? '' }}</title>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: #1f2937;
            background: #fff;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 22px;
        }
        .meta {
            margin-bottom: 18px;
            font-size: 13px;
            color: #4b5563;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px 24px;
            margin-bottom: 20px;
        }
        .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .value {
            font-size: 14px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6b7280;
        }
        td.num, th.num {
            text-align: right;
        }
        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: #6b7280;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    @php
        $orders = $note['orders'] ?? [];
    @endphp

    <div class="no-print" style="margin-bottom: 16px;">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <h1>Dispatch Note {{ $note['dispatch_no'] ?? '—' }}</h1>
    <div class="meta">Courier handover record. Order statuses are unchanged.</div>

    <div class="grid">
        <div>
            <div class="label">Supplier</div>
            <div class="value">{{ $note['supplier'] ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Courier Service</div>
            <div class="value">{{ $note['courier'] ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Operator</div>
            <div class="value">{{ $note['operator'] ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Date</div>
            <div class="value">{{ $note['dispatch_date_display'] ?? $note['dispatch_date'] ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Time</div>
            <div class="value">{{ $note['dispatch_time_display'] ?? $note['dispatch_time'] ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Orders / COD</div>
            <div class="value">
                {{ $note['orders_count'] ?? count($orders) }}
                /
                @if (!empty($note['currency_code']))
                    {{ $note['currency_code'] }}
                @endif
                {{ $note['total_cod'] ?? '—' }}
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Waybill</th>
                <th>Invoice</th>
                <th class="num">COD</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order['order_number'] ?? '—' }}</td>
                    <td>{{ $order['customer_name'] ?? '—' }}</td>
                    <td>{{ $order['primary_phone'] ?? '—' }}</td>
                    <td>{{ $order['waybill'] ?? '—' }}</td>
                    <td>{{ $order['invoice_number'] ?? '—' }}</td>
                    <td class="num">
                        @if (!empty($order['currency_code']))
                            {{ $order['currency_code'] }}
                        @endif
                        {{ $order['cod_amount'] ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No orders on this Dispatch Note.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated from Feeder Supplier Dispatch Summary.</div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
