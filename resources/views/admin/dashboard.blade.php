@extends('layouts.admin')
@section('title', 'Dashboard')
@section('admin-styles')
<style>
.dashboard-title{display:flex;align-items:center;gap:13px;margin:0;color:var(--coffee);font:700 29px 'Playfair Display',serif}.dashboard-title-icon{width:42px;height:42px;border-radius:13px;background:linear-gradient(145deg,#683718,#9b5425);display:grid;place-items:center;color:white;box-shadow:0 5px 14px #6b381d33}.dashboard-title-icon svg{width:22px;height:22px}.dashboard-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:13px;margin:17px 0}.stat-card,.panel,.recent-panel{background:var(--surface);border:1px solid #f0eae4;border-radius:15px;box-shadow:0 5px 18px #3923180a}.stat-card{padding:15px;display:flex;align-items:center;gap:12px;min-width:0}.stat-icon{width:43px;height:43px;border-radius:14px;display:grid;place-items:center;color:white;flex:none}.stat-icon svg{width:21px;height:21px}.stat-label{font-size:11px;color:#76716e;white-space:nowrap}.stat-value{font-size:20px;line-height:1.2;color:#201713;font-weight:700;margin-top:5px;white-space:nowrap}.dashboard-grid{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(280px,.9fr);gap:14px}.panel{padding:17px}.panel-title{display:flex;align-items:center;gap:9px;color:#392319;font-size:14px;font-weight:700;margin:0 0 14px}.panel-title svg{width:19px;height:19px;color:#a85b25}.sales-chart{height:190px;display:flex;align-items:stretch;gap:12px;padding:12px 0 0;border-top:1px solid #f0ece8;background:repeating-linear-gradient(to bottom,transparent 0,transparent 42px,#f1eeeb 43px)}.sales-day{height:100%;flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;min-width:0;position:relative}.sales-amount{font-size:10px;color:#86471f;font-weight:700;margin-bottom:4px;white-space:nowrap}.sales-bar{width:min(52px,72%);min-height:2px;border-radius:7px 7px 2px 2px;background:linear-gradient(180deg,#cf843f,#a95b27);box-shadow:0 3px 8px #b5682d22}.sales-label{font-size:10px;color:#67615d;margin-top:8px;white-space:nowrap}.top-list{list-style:none;margin:0;padding:0;display:grid;gap:7px}.top-item{min-height:41px;display:flex;align-items:center;gap:10px;padding:6px 9px;border:1px solid #f0ece8;border-radius:10px}.top-rank{color:#934d24;font-size:12px;font-weight:700;width:16px}.top-product-icon{width:28px;height:28px;border-radius:9px;background:#f7eee6;color:#9d5727;display:grid;place-items:center;font-size:15px}.top-product-name{flex:1;min-width:0;font-size:12px;color:#49423e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.top-qty{font-size:11px;color:#77716d;white-space:nowrap}.recent-panel{margin-top:14px;overflow:hidden}.recent-heading{display:flex;align-items:center;justify-content:space-between;padding:15px 17px;border-bottom:1px solid #f0ece8}.recent-heading .panel-title{margin:0}.view-all{font-size:12px;font-weight:700;color:#a2511c;text-decoration:none}.view-all:hover{text-decoration:underline}.orders-wrap{overflow:auto}.orders-table{width:100%;border-collapse:collapse;min-width:650px;font-size:12px}.orders-table thead{background:#f8f4f0;color:#77513b;text-align:left}.orders-table th{font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:10px 14px}.orders-table td{padding:9px 14px;border-top:1px solid #f1ede9;color:#5e5955}.orders-table tbody tr:hover{background:#fdfaf7}.orders-table .order-no{color:#a25321;font-weight:600;text-decoration:none}.orders-table .total{text-align:right;color:#29211d;font-weight:600}.empty-cell{text-align:center!important;padding:24px!important;color:#999!important}
@media(max-width:1250px){.dashboard-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.dashboard-grid{grid-template-columns:1fr}}@media(max-width:580px){.dashboard-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.stat-card{padding:11px;gap:9px}.stat-icon{width:37px;height:37px;border-radius:12px}.stat-value{font-size:17px}.stat-label{font-size:10px}.dashboard-title{font-size:25px}.sales-chart{gap:5px}.sales-label{font-size:9px}}
</style>
@endsection
@section('content')
@php $max = max($chart->max('total'), 1); @endphp
<div class="dashboard">
    <h1 class="dashboard-title"><span class="dashboard-title-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>Dashboard</h1>
    <div class="dashboard-stats">
        @foreach ([
            ['Today\'s Sales', "\u{20B1}" . number_format($stats['sales_today'], 2), 'dollar', 'linear-gradient(145deg,#9a4d19,#bd742f)'],
            ['Orders Today', $stats['orders_today'], 'bag', 'linear-gradient(145deg,#27834c,#49ad6c)'],
            ['Pending Orders', $stats['pending_orders'], 'box', 'linear-gradient(145deg,#ee8a11,#ffb02f)'],
            ['Active Riders', $stats['active_riders'], 'bike', 'linear-gradient(145deg,#7a4bd0,#a879ee)'],
            ['Customers', $stats['customers'], 'users', 'linear-gradient(145deg,#1676bc,#329ae5)'],
            ['Products', $stats['products'], 'grid', 'linear-gradient(145deg,#e84c62,#ff7183)'],
        ] as [$label, $value, $icon, $color])
            <article class="stat-card"><span class="stat-icon" style="background:{{ $color }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">@if($icon === 'dollar')<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>@elseif($icon === 'bag')<path d="M6 7h12l1 14H5L6 7zM9 7a3 3 0 0 1 6 0"/>@elseif($icon === 'box')<path d="m12 3 9 5-9 5-9-5 9-5zM3 8v9l9 5 9-5V8M12 13v9"/>@elseif($icon === 'bike')<circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM12 17.5V14l-3-3 4-3 2 3h3"/>@elseif($icon === 'users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>@else<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@endif</svg></span><span style="min-width:0"><span class="stat-label">{{ $label }}</span><span class="stat-value">{{ $value }}</span></span></article>
        @endforeach
    </div>
    <div class="dashboard-grid">
        <section class="panel"><h2 class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18M7 15l4-4 3 3 5-7"/></svg>Sales (Last 7 Days)</h2><div class="sales-chart">
            @foreach ($chart as $c)
                <div class="sales-day"><span class="sales-amount">{{ $c['total'] > 0 ? "\u{20B1}" . number_format($c['total'], 0) : '' }}</span><div class="sales-bar" style="height:{{ max(($c['total'] / $max) * 100, $c['total'] > 0 ? 4 : 1) }}%"></div><span class="sales-label">{{ $c['label'] }}</span></div>
            @endforeach
        </div></section>
        <section class="panel"><h2 class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 21 4-4 4 4V3H8z"/><path d="M8 5H5v16l4-4M16 5h3v16l-4-4"/></svg>Top Products (30 Days)</h2><ol class="top-list">
            @forelse ($topProducts as $i => $p)
                <li class="top-item"><span class="top-rank">{{ $i + 1 }}.</span><span class="top-product-icon">?</span><span class="top-product-name">{{ $p->product_name }}</span><span class="top-qty">{{ $p->qty }} pcs</span></li>
            @empty
                <li class="top-item"><span class="top-product-name">No sales yet.</span></li>
            @endforelse
        </ol></section>
    </div>
    <section class="recent-panel"><div class="recent-heading"><h2 class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>Recent Orders</h2><a href="{{ route('admin.orders.index') }}" class="view-all">View all&nbsp; &rarr;</a></div><div class="orders-wrap"><table class="orders-table"><thead><tr><th>Order No.</th><th>Customer</th><th>Order Type</th><th>Status</th><th class="total">Total</th></tr></thead><tbody>
        @forelse ($recent as $o)
            <tr><td><a href="{{ route('admin.orders.show', $o) }}" class="order-no">{{ $o->order_number }}</a></td><td>{{ $o->user->name ?? 'Walk-in' }}</td><td>{{ ucfirst($o->order_type) }}</td><td>@include('admin.partials.badge', ['status' => $o->status])</td><td class="total">?{{ number_format($o->total, 2) }}</td></tr>
        @empty
            <tr><td colspan="5" class="empty-cell">No orders yet.</td></tr>
        @endforelse
        </tbody></table></div></section>
</div>
@endsection
