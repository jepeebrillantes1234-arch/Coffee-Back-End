<style>
    .sales-wrap{color:#2c211d}.sales-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}.sales-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}.sales-card,.sales-panel{background:#fff;border:1px solid #eee8e2;border-radius:14px;padding:16px;box-shadow:0 4px 16px #3923180a}.sales-label{font-size:12px;color:#7d7773}.sales-value{font-size:23px;font-weight:750;color:#3b2118;margin-top:5px}.sales-sub{font-size:11px;color:#7d7773;margin-top:4px}.sales-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px;margin:18px 0}.sales-filter label{display:block;font-size:11px;color:#6e625c;margin-bottom:4px}.sales-filter select,.sales-filter input{border:1px solid #ddd2c9;border-radius:8px;padding:9px 10px;background:#fff}.sales-button{display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:10px 14px;background:#3b2118;color:#fff;font-weight:650;border:0;cursor:pointer}.sales-button.gold{background:#b86a2c}.sales-panel{margin:14px 0;overflow:hidden}.sales-panel h2{margin:0 0 12px;font-size:16px;color:#3b2118}.sales-scroll{overflow:auto}.sales-table{width:100%;border-collapse:collapse;font-size:12px}.sales-table th,.sales-table td{padding:9px 10px;border-bottom:1px solid #eee8e2;text-align:left;vertical-align:top}.sales-table th{background:#fbf7f1;color:#6c5040;white-space:nowrap}.sales-table .num{text-align:right;white-space:nowrap}.trend-bars{min-height:180px;display:flex;align-items:flex-end;gap:8px;overflow:auto;padding:16px 4px 4px;border-bottom:1px solid #eee8e2}.trend-item{height:150px;min-width:40px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:5px}.trend-bar{width:25px;min-height:2px;border-radius:5px 5px 0 0;background:#b86a2c}.trend-label{font-size:10px;color:#74675f;white-space:nowrap;writing-mode:vertical-rl;transform:rotate(180deg);max-height:65px}.rank-track{height:7px;background:#f0e9e3;border-radius:8px;overflow:hidden;min-width:70px}.rank-fill{height:100%;background:#b86a2c;border-radius:8px}.print-only{display:none}
    @media(max-width:800px){.sales-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sales-header{display:block}}
    @media(max-width:520px){.sales-grid{grid-template-columns:1fr 1fr;gap:8px}.sales-card{padding:12px}.sales-value{font-size:19px}.sales-filter>*,.sales-month-filter>*{flex:1 1 42%}}
    @page{size:A4 landscape;margin:10mm;@bottom-right{content:"Page " counter(page) " of " counter(pages);font-size:8px;color:#666}}
    @media print{body{background:#fff!important;color:#111!important;font-size:9px}.admin-sidebar,.topbar,.admin-profile-panel,#mobileBackdrop,.sales-filter,.print-hidden{display:none!important}.admin-shell,.admin-content,.workspace{display:block!important;width:100%!important;min-height:0!important;margin:0!important;padding:0!important;overflow:visible!important}.sales-wrap{color:#111}.sales-card,.sales-panel{box-shadow:none;border:1px solid #aaa;border-radius:0;break-inside:avoid;padding:8px}.sales-grid{grid-template-columns:repeat(6,1fr);gap:5px;margin:7px 0}.sales-value{font-size:15px}.sales-panel{margin:7px 0}.sales-panel h2{font-size:12px}.sales-table{font-size:7px}.sales-table th,.sales-table td{padding:4px 5px;border:1px solid #bbb}.sales-table thead{display:table-header-group}.sales-table tr{break-inside:avoid}.trend-bars{height:90px;min-height:90px;overflow:hidden}.trend-item{height:76px}.sales-report-table{font-size:6.5px}.sales-report-table th,.sales-report-table td{padding:3px}.print-only{display:block}.print-footer{margin-top:8px;text-align:right;color:#555;font-size:8px}}
    .chart-legend{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 8px;color:#6e625c;font-size:11px}.chart-legend span{display:inline-flex;align-items:center;gap:6px}.legend-swatch{width:10px;height:10px;border-radius:3px;background:#b86a2c;display:inline-block}.legend-swatch.line{height:3px;border-radius:3px;background:#3b2118}.sales-trend-svg{display:block;width:100%;min-width:620px;height:auto}.sales-chart-scroll{overflow-x:auto}.chart-grid-line{stroke:#eee8e2;stroke-width:1}.chart-axis-label{fill:#80766f;font:11px Arial,sans-serif}.chart-date-label{fill:#6e625c;font:10px Arial,sans-serif}.chart-trend-line{fill:none;stroke:#3b2118;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.chart-trend-point{fill:#d6a35d;stroke:#fff;stroke-width:2}.product-chart-layout{display:grid;grid-template-columns:minmax(190px,.8fr) minmax(240px,1.2fr);align-items:center;gap:22px;margin:6px 0 18px}.product-donut-wrap{display:flex;justify-content:center;align-items:center}.product-donut{width:190px;height:190px;border-radius:50%;display:grid;place-items:center;position:relative}.product-donut:before{content:"";position:absolute;inset:30px;border-radius:50%;background:#fff}.product-donut-center{position:relative;text-align:center;color:#3b2118;font-size:12px;font-weight:700;line-height:1.5}.product-donut-center small{display:block;color:#7d7773;font-size:10px;font-weight:500}.product-legend{display:grid;gap:7px}.product-legend-row{display:grid;grid-template-columns:10px minmax(0,1fr) auto;align-items:center;gap:8px;font-size:11px;color:#49423e}.product-legend-dot{width:10px;height:10px;border-radius:3px}.product-legend-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.product-legend-value{color:#6e625c;white-space:nowrap}.product-ranking-title{margin-top:14px!important}
    @media(max-width:640px){.product-chart-layout{grid-template-columns:1fr;gap:15px}.product-legend{padding:0 6px}.product-donut{width:170px;height:170px}.product-donut:before{inset:27px}}
    @media print{.sales-chart-scroll{overflow:visible!important}.sales-trend-svg{min-width:0;width:100%}.product-chart-layout{grid-template-columns:190px 1fr;break-inside:avoid}.product-donut{width:145px;height:145px}.product-donut:before{inset:24px}}
    .sales-month-filter{display:flex;align-items:end;gap:10px;margin:14px 0}.sales-month-filter label{display:block;font-size:11px;color:#6e625c;margin-bottom:4px}.sales-month-filter input{border:1px solid #ddd2c9;border-radius:8px;padding:9px 10px;background:#fff;color:#3b2118}.chart-month-link{cursor:pointer}.chart-month-link:hover .chart-date-label{text-decoration:underline;fill:#b86a2c}.chart-trend-line,.chart-trend-point{pointer-events:none}
</style>

<div class="sales-wrap" id="sales-report">
    <div class="sales-header">
        <div>
            <h1 style="font-size:24px;font-weight:750;margin:0;color:#3b2118">KJM Coffee Shop · Sales Statistics</h1>
            <p class="sales-sub">Paid, delivered or completed orders · {{ $sales['period'] }}</p>
        </div>
        <a class="sales-button gold print-hidden" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['export'=>'pdf']) }}">Download Sales Report (PDF)</a>
    </div>

    <form method="GET" action="{{ route('admin.reports.show', ['type' => 'sales']) }}" class="sales-month-filter print-hidden">
        <input type="hidden" name="range" value="custom">
        <div><label for="sales-month">Choose month</label><input id="sales-month" type="month" name="month" value="{{ $selectedMonth }}" required></div>
        <button class="sales-button gold">View Month</button>
    </form>

    <form method="GET" action="{{ route('admin.reports.show', ['type' => 'sales']) }}" class="sales-month-filter print-hidden">
        <input type="hidden" name="range" value="custom">
        <div><label for="sales-from">From day</label><input id="sales-from" type="date" name="from" value="{{ $from }}" required></div>
        <div><label for="sales-to">To day</label><input id="sales-to" type="date" name="to" value="{{ $to }}" required></div>
        <button class="sales-button">View Dates</button>
    </form>

    <form method="GET" class="sales-filter print-hidden">
        <div><label for="sales-range">Reporting period</label><select id="sales-range" name="range">
            @foreach (['today'=>'Today','yesterday'=>'Yesterday','this_week'=>'This Week','this_month'=>'This Month','this_year'=>'This Year'] as $key=>$label)
                <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
            @endforeach
        </select></div>
        <button class="sales-button">Apply</button>
        <a class="sales-button" style="background:#fff;color:#3b2118;border:1px solid #ddd2c9;text-decoration:none" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}">Export CSV</a>
    </form>

    <div class="sales-grid">
        @foreach ([['Total Sales Revenue',$sales['gross']],['Completed Orders',$sales['orders']],['Products Sold',$sales['quantity']],['Average Order Value',$sales['average']],['Confirmed Refunds',$sales['refunds']],['Net Sales',$sales['net']]] as [$label,$value])
            <section class="sales-card"><div class="sales-label">{{ $label }}</div><div class="sales-value">{{ is_int($value) ? number_format($value) : '₱'.number_format((float)$value,2) }}</div>
                @if ($label === 'Confirmed Refunds')<div class="sales-sub">PayMongo-confirmed successful refunds</div>@endif
            </section>
        @endforeach
    </div>

    <section class="sales-panel">
        <h2>Monthly Sales Trend · {{ $trendYear }}</h2>
        <p class="sales-sub">The chart shows all 12 months of {{ $trendYear }}. Select a month to see its sales transactions.</p>
        @if (collect($sales['trend'])->sum('value') > 0)
            @php
                $trendWidth = 960;
                $trendHeight = 280;
                $trendLeft = 62;
                $trendRight = 18;
                $trendTop = 20;
                $trendBottom = 42;
                $plotWidth = $trendWidth - $trendLeft - $trendRight;
                $plotHeight = $trendHeight - $trendTop - $trendBottom;
                $trendCount = count($sales['trend']);
                $trendMax = max(1, max(array_column($sales['trend'], 'value')));
                $bandWidth = $plotWidth / max(1, $trendCount);
                $trendPoints = [];
                $averageLine = [];
                foreach ($sales['trend'] as $index => $point) {
                    $x = $trendLeft + $index * $bandWidth + $bandWidth / 2;
                    $value = (float) $point['value'];
                    $averageValues = array_column(array_slice($sales['trend'], max(0, $index - 1), min($trendCount, 3), true), 'value');
                    $average = array_sum($averageValues) / max(1, count($averageValues));
                    $trendPoints[] = [
                        'x' => $x,
                        'y' => $trendTop + $plotHeight - ($value / $trendMax) * $plotHeight,
                        'barX' => $trendLeft + $index * $bandWidth + $bandWidth * .28,
                        'barWidth' => min(36, $bandWidth * .44),
                        'barY' => $trendTop + $plotHeight - ($value / $trendMax) * $plotHeight,
                        'barHeight' => max(1, ($value / $trendMax) * $plotHeight),
                        'label' => $point['label'],
                        'month' => $point['month'],
                        'value' => $value,
                        'average' => $average,
                        'averageY' => $trendTop + $plotHeight - ($average / $trendMax) * $plotHeight,
                    ];
                    $averageLine[] = number_format($x, 1, '.', '').','.number_format($trendTop + $plotHeight - ($average / $trendMax) * $plotHeight, 1, '.', '');
                }
                $labelStep = 1;
            @endphp
            <div class="chart-legend"><span><i class="legend-swatch"></i>Sales</span><span><i class="legend-swatch line"></i>3-period trend</span></div>
            <div class="sales-chart-scroll">
                <svg id="sales-trend-svg" class="sales-trend-svg" viewBox="0 0 {{ $trendWidth }} {{ $trendHeight }}" role="img" aria-label="Sales by period with a three-period trend line">
                    <defs><linearGradient id="salesBarGradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#d6a35d"/><stop offset="100%" stop-color="#b86a2c"/></linearGradient></defs>
                    @for ($grid = 0; $grid <= 4; $grid++)
                        @php
                            $gridY = $trendTop + ($plotHeight * $grid / 4);
                            $gridValue = $trendMax * (4 - $grid) / 4;
                        @endphp
                        <line class="chart-grid-line" x1="{{ $trendLeft }}" y1="{{ $gridY }}" x2="{{ $trendWidth - $trendRight }}" y2="{{ $gridY }}"/>
                        <text class="chart-axis-label" x="{{ $trendLeft - 9 }}" y="{{ $gridY + 4 }}" text-anchor="end">₱{{ number_format($gridValue, 0) }}</text>
                    @endfor
                    @foreach ($trendPoints as $index => $point)
                        <a class="chart-month-link" href="{{ route('admin.reports.show', ['type' => 'sales', 'range' => 'custom', 'month' => $point['month']]) }}" aria-label="View all sales for {{ $point['label'] }}">
                            <rect x="{{ number_format($trendLeft + $index * $bandWidth, 1, '.', '') }}" y="{{ $trendTop }}" width="{{ number_format($bandWidth, 1, '.', '') }}" height="{{ $plotHeight }}" fill="transparent"/>
                        <rect x="{{ number_format($point['barX'], 1, '.', '') }}" y="{{ number_format($point['barY'], 1, '.', '') }}" width="{{ number_format($point['barWidth'], 1, '.', '') }}" height="{{ number_format($point['barHeight'], 1, '.', '') }}" rx="5" fill="url(#salesBarGradient)">
                            <title>{{ $point['label'] }}: ₱{{ number_format($point['value'], 2) }}</title>
                        </rect>
                        </a>
                        @if ($index % $labelStep === 0 || $index === $trendCount - 1)
                            <a class="chart-month-link" href="{{ route('admin.reports.show', ['type' => 'sales', 'range' => 'custom', 'month' => $point['month']]) }}" aria-label="View all sales for {{ $point['label'] }}"><text class="chart-date-label" x="{{ number_format($point['x'], 1, '.', '') }}" y="{{ $trendHeight - 12 }}" text-anchor="middle">{{ $point['label'] }}</text></a>
                        @endif
                    @endforeach
                    <polyline class="chart-trend-line" points="{{ implode(' ', $averageLine) }}" pointer-events="none"/>
                    @foreach ($trendPoints as $point)
                        <circle class="chart-trend-point" cx="{{ number_format($point['x'], 1, '.', '') }}" cy="{{ number_format($point['averageY'], 1, '.', '') }}" r="4"><title>3-period trend: ₱{{ number_format($point['average'], 2) }}</title></circle>
                    @endforeach
                </svg>
            </div>
        @else
            <p class="sales-sub">No paid completed sales in this period.</p>
        @endif
    </section>

    <section class="sales-panel">
        <h2>Top-Selling Products · Share of Top 10 Quantity</h2>
        @php
            $productPalette = ['#3b2118', '#b86a2c', '#d6a35d', '#8c5a3c', '#e2be8c', '#754225', '#c78955', '#aa7748', '#5f4737', '#e9d9c4'];
            $topProductQuantity = (int) collect($sales['products'])->sum('quantity');
            $productSlices = [];
            $sliceStart = 0;
            foreach ($sales['products'] as $index => $product) {
                $sliceEnd = $topProductQuantity > 0 ? $sliceStart + ((int) $product['quantity'] / $topProductQuantity * 100) : $sliceStart;
                $productSlices[] = [...$product, 'color' => $productPalette[$index % count($productPalette)], 'start' => $sliceStart, 'end' => $sliceEnd];
                $sliceStart = $sliceEnd;
            }
            $donutGradient = implode(', ', array_map(fn ($slice) => $slice['color'].' '.number_format($slice['start'], 2, '.', '').'% '.number_format($slice['end'], 2, '.', '').'%', $productSlices));
        @endphp
        @if (count($productSlices))
            <div class="product-chart-layout">
                <div class="product-donut-wrap"><div id="top-products-donut" class="product-donut" role="img" aria-label="Top selling products by quantity" style="background:conic-gradient({{ $donutGradient }})"><div class="product-donut-center">{{ number_format($topProductQuantity) }}<small>Top 10 sold</small></div></div></div>
                <div class="product-legend" aria-label="Top products legend">
                    @foreach ($productSlices as $slice)
                        <div class="product-legend-row"><span class="product-legend-dot" style="background:{{ $slice['color'] }}"></span><span class="product-legend-name">{{ $slice['name'] }}</span><span class="product-legend-value">{{ number_format($slice['quantity']) }} · {{ number_format($topProductQuantity ? $slice['quantity'] / $topProductQuantity * 100 : 0, 1) }}%</span></div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="sales-sub">No product sales in this period.</p>
        @endif
        <h2 class="product-ranking-title">Product Sales Detail</h2>
        <div class="sales-scroll"><table class="sales-table"><thead><tr><th>Rank</th><th>Product</th><th>Quantity sold</th><th>Revenue</th></tr></thead><tbody>
        @forelse ($sales['products'] as $product)
            <tr><td>#{{ $product['rank'] }}</td><td>{{ $product['name'] }}</td><td>{{ number_format($product['quantity']) }}</td><td>₱{{ number_format($product['revenue'],2) }}</td></tr>
        @empty<tr><td colspan="4">No product sales in this period.</td></tr>@endforelse
        </tbody></table></div>
        <h2 style="margin-top:16px">Least-Selling Products</h2>
        <div class="sales-scroll"><table class="sales-table"><thead><tr><th>Product</th><th>Quantity sold</th><th>Revenue</th></tr></thead><tbody>
        @forelse ($sales['least_products'] as $product)<tr><td>{{ $product['name'] }}</td><td>{{ number_format($product['quantity']) }}</td><td>₱{{ number_format($product['revenue'],2) }}</td></tr>
        @empty<tr><td colspan="3">No product sales in this period.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="sales-panel">
        <h2>Sales Transactions ({{ count($sales['transactions']) }})</h2>
        <div class="sales-scroll"><table class="sales-table sales-report-table"><thead><tr><th>Order</th><th>Date / time</th><th>Customer</th><th>Products, quantity and unit price</th><th>Subtotal</th><th>Delivery</th><th>Discount</th><th>Payment</th><th>Payment status</th><th>Order status</th><th>Refunded</th><th>Final total</th></tr></thead><tbody>
        @forelse ($sales['transactions'] as $transaction)
            <tr><td>{{ $transaction['number'] }}</td><td>{{ $transaction['date'] }}</td><td>{{ $transaction['customer'] }}</td><td>@foreach ($transaction['items'] as $item)<div>{{ $item->product_name }} · {{ $item->quantity }} × ₱{{ number_format($item->unit_price,2) }}</div>@endforeach</td><td>₱{{ number_format($transaction['subtotal'],2) }}</td><td>₱{{ number_format($transaction['delivery_fee'],2) }}</td><td>₱{{ number_format($transaction['discount'],2) }}</td><td>{{ $transaction['payment_method'] }}</td><td>{{ $transaction['payment_status'] }}</td><td>{{ $transaction['status'] }}</td><td>₱{{ number_format($transaction['refunded'],2) }}</td><td><strong>₱{{ number_format($transaction['total'],2) }}</strong></td></tr>
        @empty<tr><td colspan="12" style="text-align:center">No paid completed sales found for this period.</td></tr>@endforelse
        </tbody></table></div>
        <div class="print-footer"><strong>Grand Total Sales Revenue: ₱{{ number_format($sales['gross'],2) }}</strong> · Completed orders: {{ number_format($sales['orders']) }} · Products sold: {{ number_format($sales['quantity']) }} · Average order: ₱{{ number_format($sales['average'],2) }} · Confirmed refunds: ₱{{ number_format($sales['refunds'],2) }} · Net sales: ₱{{ number_format($sales['net'],2) }}</div>
    </section>
    <p class="print-only print-footer">KJM Coffee Shop · Sales Report · Generated {{ now('Asia/Manila')->format('M j, Y g:i A') }} · {{ $sales['period'] }}</p>
</div>
