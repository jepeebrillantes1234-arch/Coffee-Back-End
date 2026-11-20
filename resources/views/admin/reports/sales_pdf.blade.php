<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KJM Coffee Shop Sales Report</title>
    <style>
        @page { size: A4 landscape; margin: 15mm 8mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #2c211d; font-size: 8px; }
        .brand { color: #3b2118; font-size: 18px; font-weight: bold; margin: 0; }
        .subtitle { color: #74675f; font-size: 9px; margin-top: 4px; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 5px; margin: 14px -5px 14px; }
        .summary td { width: 16.66%; padding: 8px; border: 1px solid #e7ddd2; background: #fbf7f1; }
        .label { color: #74675f; font-size: 7px; text-transform: uppercase; }
        .value { color: #3b2118; font-size: 11px; font-weight: bold; margin-top: 4px; }
        .section { color: #3b2118; font-size: 11px; font-weight: bold; margin: 10px 0 6px; }
        table.transactions { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .transactions thead { display: table-header-group; }
        .transactions tfoot { display: table-row-group; }
        .transactions tr { page-break-inside: avoid; }
        .transactions th { color: #fff; background: #3b2118; font-size: 7px; text-align: left; padding: 6px 4px; }
        .transactions td { border-bottom: 1px solid #e7ddd2; padding: 5px 4px; vertical-align: top; overflow-wrap: break-word; }
        .transactions tbody tr:nth-child(even) { background: #fbf7f1; }
        .num { text-align: right !important; white-space: nowrap; }
        .product { margin-bottom: 3px; }
        .muted { color: #74675f; }
        .totals { margin-top: 10px; padding: 8px; background: #f5eee5; border-left: 3px solid #b86a2c; font-weight: bold; }
        .empty { padding: 16px !important; color: #74675f; text-align: center; }
        table.monthly-trend { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 12px; }
        .monthly-trend td { width: 16.66%; padding: 5px; border: 1px solid #e7ddd2; background: #fbf7f1; }
        .trend-month { color: #74675f; font-size: 7px; }
        .trend-value { color: #3b2118; font-size: 9px; font-weight: bold; margin-top: 2px; }
        .trend-track { height: 3px; margin-top: 4px; background: #e7ddd2; }
        .trend-bar { height: 3px; background: #b86a2c; }
    </style>
</head>
<body>
    <h1 class="brand">KJM Coffee Shop</h1>
    <div class="subtitle">Sales Report · {{ $from }} to {{ $to }} · Asia/Manila</div>

    <table class="summary">
        <tr>
            <td><div class="label">Grand total sales</div><div class="value">₱{{ number_format($sales['gross'], 2) }}</div></td>
            <td><div class="label">Total orders</div><div class="value">{{ number_format($sales['orders']) }}</div></td>
            <td><div class="label">Products sold</div><div class="value">{{ number_format($sales['quantity']) }}</div></td>
            <td><div class="label">Average order value</div><div class="value">₱{{ number_format($sales['average'], 2) }}</div></td>
            <td><div class="label">Confirmed refunds</div><div class="value">₱{{ number_format($sales['refunds'], 2) }}</div></td>
            <td><div class="label">Net sales</div><div class="value">₱{{ number_format($sales['net'], 2) }}</div></td>
        </tr>
    </table>

    <div class="section">Monthly Sales Trend - {{ $trendYear }}</div>
    @php($trendMax = max(1, max(array_column($sales['trend'], 'value'))))
    <table class="monthly-trend"><tbody>
        @foreach (array_chunk($sales['trend'], 6) as $trendRow)
            <tr>
                @foreach ($trendRow as $month)
                    <td>
                        <div class="trend-month">{{ $month['label'] }}</div>
                        <div class="trend-value">&#8369;{{ number_format($month['value'], 2) }}</div>
                        <div class="trend-track"><div class="trend-bar" style="width:{{ min(100, $month['value'] / $trendMax * 100) }}%"></div></div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody></table>

    <div class="section">Sales Transactions ({{ count($sales['transactions']) }})</div>
    <table class="transactions">
        <thead>
            <tr>
                <th style="width:9%">Order / Date</th>
                <th style="width:9%">Customer</th>
                <th style="width:30%">Products · Qty · Unit and line price</th>
                <th style="width:7%" class="num">Subtotal</th>
                <th style="width:7%" class="num">Delivery</th>
                <th style="width:7%" class="num">Discount</th>
                <th style="width:10%">Payment / Status / Reference</th>
                <th style="width:6%">Order status</th>
                <th style="width:7%" class="num">Refunded</th>
                <th style="width:8%" class="num">Order total</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($sales['transactions'] as $transaction)
            <tr>
                <td><strong>{{ $transaction['number'] }}</strong><br><span class="muted">{{ $transaction['date'] }}</span></td>
                <td>{{ $transaction['customer'] }}</td>
                <td>
                    @foreach ($transaction['items'] as $item)
                        <div class="product">{{ $item->product_name }} · {{ $item->quantity }} × ₱{{ number_format((float) $item->unit_price, 2) }} = ₱{{ number_format((float) $item->total_price, 2) }}</div>
                    @endforeach
                </td>
                <td class="num">₱{{ number_format($transaction['subtotal'], 2) }}</td>
                <td class="num">₱{{ number_format($transaction['delivery_fee'], 2) }}</td>
                <td class="num">₱{{ number_format($transaction['discount'], 2) }}</td>
                <td>
                    {{ $transaction['payment_method'] }} · {{ $transaction['payment_status'] }}
                    @foreach ($transaction['payments'] as $payment)
                        @if ($payment->reference_no || $payment->provider_payment_id)
                            <br><span class="muted">{{ $payment->reference_no ?: $payment->provider_payment_id }}</span>
                        @endif
                    @endforeach
                </td>
                <td>{{ $transaction['status'] }}</td>
                <td class="num">₱{{ number_format($transaction['refunded'], 2) }}</td>
                <td class="num"><strong>₱{{ number_format($transaction['total'], 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">No paid completed sales found for this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="totals">
        Grand Total Sales: ₱{{ number_format($sales['gross'], 2) }} · Orders: {{ number_format($sales['orders']) }} · Products Sold: {{ number_format($sales['quantity']) }} · Average Order Value: ₱{{ number_format($sales['average'], 2) }} · Confirmed Refunds: ₱{{ number_format($sales['refunds'], 2) }} · Net Sales: ₱{{ number_format($sales['net'], 2) }}
    </div>

</body>
</html>
