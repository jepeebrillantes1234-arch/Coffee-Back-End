@extends('layouts.staff')
@section('title','Orders')
@section('content')
<div class="grid gap-3">
@forelse($orders as $order)
<article class="rounded-xl bg-white p-4 shadow-sm"><div class="flex flex-wrap justify-between gap-2"><div><h2 class="font-semibold">{{ $order->order_number }} · {{ strtoupper($order->status) }}</h2><p class="text-sm text-espresso/70">{{ $order->user->name ?? 'Walk-in' }} · {{ strtoupper($order->order_type) }} · ₱{{ number_format($order->total,2) }}</p><p class="text-sm">{{ $order->items->map(fn($i)=>$i->quantity.' × '.$i->product_name)->join(', ') }}</p></div>
<div class="flex flex-wrap gap-2">
@if($order->status==='ready' && $order->order_type==='delivery' && !$order->delivery?->rider_id)<form method="POST" action="{{ route('staff.orders.assign-available',$order) }}">@csrf<button class="rounded bg-caramel px-3 py-2 text-white">Assign online rider</button></form>@endif
@if($order->delivery?->rider)<p class="text-sm text-success">Rider: {{ $order->delivery->rider->name }} · {{ ucfirst(str_replace('_',' ',$order->delivery->status)) }}</p>@endif
@if($order->status==='pending')<form method="POST" action="{{ route('staff.orders.advance',$order) }}">@csrf<input type="hidden" name="action" value="confirm"><button class="rounded bg-espresso px-3 py-2 text-white">Confirm</button></form>@endif
@if($order->status==='confirmed')<form method="POST" action="{{ route('staff.orders.advance',$order) }}">@csrf<input type="hidden" name="action" value="preparing"><button class="rounded bg-espresso px-3 py-2 text-white">Preparing</button></form>@endif
@if($order->status==='preparing')<form method="POST" action="{{ route('staff.orders.advance',$order) }}">@csrf<input type="hidden" name="action" value="ready"><button class="rounded bg-espresso px-3 py-2 text-white">Ready</button></form>@endif
@if($order->status==='ready' && $order->order_type!=='delivery')<form method="POST" action="{{ route('staff.orders.advance',$order) }}">@csrf<input type="hidden" name="action" value="complete"><button class="rounded bg-success px-3 py-2 text-white">Complete</button></form>@endif
@if($order->status==='delivered' && $order->delivery)<form method="POST" action="{{ route('staff.deliveries.remit',$order->delivery) }}">@csrf<button class="rounded bg-success px-3 py-2 text-white">COD received / Complete</button></form>@endif
</div></div></article>
@empty<p class="rounded-xl bg-white p-6 text-espresso/60">Walang active na order.</p>@endforelse</div>
@endsection
