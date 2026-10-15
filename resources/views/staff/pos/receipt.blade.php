<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #{{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 12px; 
            line-height: 1.4; 
            width: 280px; 
            margin: 0 auto; 
            padding: 15px; 
            color: #111; 
            background: #fff;
        }
        .header { text-align: center; margin-bottom: 12px; }
        .logo { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .subtitle { font-size: 11px; color: #555; margin-top: 2px; }
        
        .divider { 
            border: 0; 
            border-top: 1px dashed #333; 
            margin: 10px 0; 
        }
        
        .meta-info { font-size: 11px; margin-bottom: 8px; }
        .meta-info div { display: flex; justify-content: space-between; margin-bottom: 2px; }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { text-align: left; font-size: 11px; border-bottom: 1px solid #111; padding-bottom: 3px; }
        td { vertical-align: top; padding: 4px 0; font-size: 12px; }
        .r { text-align: right; }
        .sub { font-size: 10px; color: #555; padding-left: 10px; }
        
        .totals-table { margin-top: 8px; }
        .totals-table td { padding: 3px 0; }
        .tot td { font-weight: bold; font-size: 14px; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 6px 0; margin-top: 4px; }
        
        .footer { text-align: center; margin-top: 15px; font-size: 11px; }
        .footer-note { font-style: italic; color: #444; margin-top: 4px; }

        .no-print { text-align: center; margin-top: 15px; }
        .btn { 
            background: #2c1810; 
            color: #fff; 
            border: none; 
            padding: 8px 14px; 
            font-size: 12px; 
            font-family: inherit;
            border-radius: 4px; 
            cursor: pointer; 
            margin: 2px;
        }
        .btn-secondary { background: #666; }
        .btn:hover { opacity: 0.9; }

        @media print { 
            .no-print { display: none; } 
            body { width: 100%; padding: 0; margin: 0; } 
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <div class="logo">☕ KJM COFFEE SHOP</div>
        <div class="subtitle">Official Receipt / Proof of Payment</div>
    </div>
    
    <hr class="divider">
    
    <div class="meta-info">
        <div><span>Order No:</span> <strong>{{ $order->order_number }}</strong></div>
        <div><span>Date:</span> <span>{{ $order->created_at->format('M d, Y h:i A') }}</span></div>
        <div><span>Cashier:</span> <span>{{ $order->staff->name ?? '—' }}</span></div>
        @if ($order->notes) 
            <div style="margin-top: 4px;"><span>Note:</span> <span>{{ $order->notes }}</span></div> 
        @endif
    </div>
    
    <hr class="divider">
    
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="r">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $it)
                <tr>
                    <td>{{ $it->quantity }}x {{ $it->product_name }}</td>
                    <td class="r">₱{{ number_format($it->total_price, 2) }}</td>
                </tr>
                @if ($it->options->isNotEmpty()) 
                    <tr><td colspan="2" class="sub">+ {{ $it->options->pluck('option_name')->join(', ') }}</td></tr> 
                @endif
                @if ($it->notes) 
                    <tr><td colspan="2" class="sub">Note: {{ $it->notes }}</td></tr> 
                @endif
            @endforeach
        </tbody>
    </table>
    
    <hr class="divider">
    
    <table class="totals-table">
        <tr>
            <td>Subtotal</td>
            <td class="r">₱{{ number_format($order->subtotal, 2) }}</td>
        </tr>
        @if ($order->discount > 0) 
            <tr>
                <td>Discount</td>
                <td class="r">-₱{{ number_format($order->discount, 2) }}</td>
            </tr> 
        @endif
        <tr class="tot">
            <td>TOTAL</td>
            <td class="r">₱{{ number_format($order->total, 2) }}</td>
        </tr>
        @if ($cash !== null)
            <tr>
                <td style="padding-top: 6px;">Cash Tendered</td>
                <td class="r" style="padding-top: 6px;">₱{{ number_format($cash, 2) }}</td>
            </tr>
            <tr>
                <td>Change</td>
                <td class="r">₱{{ number_format($change, 2) }}</td>
            </tr>
        @endif
    </table>
    
    <hr class="divider">
    
    <div class="footer">
        <p>Thank you for your purchase!</p>
        <p class="footer-note">Please come again. ☕</p>
    </div>

    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨 Print</button> 
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
    </div>
</body>
</html>