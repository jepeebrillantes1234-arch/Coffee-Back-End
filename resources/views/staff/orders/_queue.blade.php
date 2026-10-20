<div class="grid gap-4">
@forelse($orders as $order)
    @php
        $firstItem = $order->items->first();
        $productImage = $firstItem?->product?->image;
        $statusLabel = strtoupper(str_replace('_', ' ', $order->status));
        $statusClasses = match ($order->status) {
            'pending', 'awaiting_payment' => 'bg-amber-100 text-amber-800',
            'confirmed', 'preparing', 'ready' => 'bg-blue-100 text-blue-800',
            'rider_assigned', 'picked_up', 'on_the_way' => 'bg-emerald-100 text-emerald-800',
            'delivered' => 'bg-teal-100 text-teal-800',
            default => 'bg-stone-100 text-stone-700',
        };
        $phone = $order->user?->phone;
    @endphp
    <article class="overflow-hidden rounded-2xl border border-espresso/10 border-l-4 border-l-success bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-cream">
                @if($productImage)
                    <img src="{{ asset('storage/' . ltrim($productImage, '/')) }}" alt="{{ $firstItem?->product_name ?? 'Ordered product' }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-3xl" aria-label="No product image">&#9749;</div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <h2 class="font-semibold text-espresso">{{ $order->order_number }}</h2>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
                </div>
                <p class="text-sm font-medium text-espresso">{{ $order->user?->name ?? 'Walk-in customer' }}</p>
                <p class="mt-1 text-sm text-espresso/70">
                    @if($phone)
                        <a href="tel:{{ $phone }}" class="font-medium text-caramel underline underline-offset-2">{{ $phone }}</a>
                    @else
                        <span class="text-espresso/50">Phone number not provided</span>
                    @endif
                    <span class="px-1">&middot;</span>{{ strtoupper($order->order_type) }}
                    <span class="px-1">&middot;</span>&#8369;{{ number_format($order->total, 2) }}
                </p>
                <p class="mt-1 text-sm text-espresso/80">
                    {{ $order->items->map(fn ($item) => $item->quantity . ' &times; ' . $item->product_name)->join(', ') }}
                </p>
                @if($order->delivery?->rider)
                    <p class="mt-2 text-sm font-medium text-emerald-700">
                        Rider: {{ $order->delivery->rider->name }}
                        <span class="font-normal">&middot; {{ ucfirst(str_replace('_', ' ', $order->delivery->status)) }}</span>
                    </p>
                @endif
            </div>

            <div class="flex shrink-0 flex-wrap gap-2 sm:max-w-56 sm:justify-end">
                @if($order->status === 'awaiting_payment')
                    <span class="rounded-lg bg-amber-100 px-3 py-2 text-sm font-medium text-amber-900">Waiting for customer payment</span>
                @endif
                @if($order->status === 'ready' && $order->order_type === 'delivery' && !$order->delivery?->rider_id)
                    <form method="POST" action="{{ route('staff.orders.assign-available', $order) }}">@csrf<button class="rounded-lg bg-caramel px-3 py-2 text-sm font-semibold text-white">Assign rider</button></form>
                @endif
                @if($order->status === 'pending')
                    <form method="POST" action="{{ route('staff.orders.advance', $order) }}">@csrf<input type="hidden" name="action" value="confirm"><button class="rounded-lg bg-espresso px-3 py-2 text-sm font-semibold text-white">Confirm</button></form>
                @endif
                @if($order->status === 'confirmed')
                    <form method="POST" action="{{ route('staff.orders.advance', $order) }}">@csrf<input type="hidden" name="action" value="preparing"><button class="rounded-lg bg-espresso px-3 py-2 text-sm font-semibold text-white">Start preparing</button></form>
                @endif
                @if($order->status === 'preparing')
                    <form method="POST" action="{{ route('staff.orders.advance', $order) }}">@csrf<input type="hidden" name="action" value="ready"><button class="rounded-lg bg-espresso px-3 py-2 text-sm font-semibold text-white">Mark ready</button></form>
                @endif
                @if($order->status === 'ready' && $order->order_type !== 'delivery')
                    <form method="POST" action="{{ route('staff.orders.advance', $order) }}">@csrf<input type="hidden" name="action" value="complete"><button class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white">Complete</button></form>
                @endif
                @if($order->status === 'delivered' && $order->delivery)
                    <form method="POST" action="{{ route('staff.deliveries.remit', $order->delivery) }}">@csrf<button class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white">COD received / Complete</button></form>
                @endif
                <details class="basis-full sm:basis-auto">
                    <summary class="cursor-pointer list-none rounded-lg border border-espresso/20 px-3 py-2 text-center text-sm font-semibold text-espresso hover:bg-cream">View order</summary>
                    <div class="mt-3 w-full rounded-xl border border-espresso/10 bg-cream/50 p-4 text-left sm:min-w-[28rem]">
                        <h3 class="mb-3 font-semibold text-espresso">Items in this order</h3>
                        <div class="grid gap-3">
                            @foreach($order->items as $item)
                                @php($itemImage = $item->product?->image)
                                <div class="flex gap-3 rounded-lg bg-white p-3">
                                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-cream">
                                        @if($itemImage)
                                            <img src="{{ asset('storage/' . ltrim($itemImage, '/')) }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover" loading="lazy">
                                        @else
                                            <div class="flex h-full items-center justify-center text-2xl" aria-label="Product image unavailable">&#9749;</div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1 text-sm">
                                        <p class="font-semibold text-espresso">{{ $item->product_name }}</p>
                                        <p class="mt-1 text-espresso/70">Quantity: {{ $item->quantity }} &middot; Unit price: &#8369;{{ number_format($item->unit_price, 2) }}</p>
                                        <p class="font-medium text-espresso">Item total: &#8369;{{ number_format($item->total_price, 2) }}</p>
                                        @if($item->options->isNotEmpty())
                                            <p class="mt-1 text-espresso/70">Options: {{ $item->options->map(fn ($option) => $option->option_name . ($option->extra_price > 0 ? ' (+₱' . number_format($option->extra_price, 2) . ')' : ''))->join(', ') }}</p>
                                        @endif
                                        @if($item->notes)
                                            <p class="mt-1 text-espresso/70">Item note: {{ $item->notes }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-espresso/10 pt-3 text-sm">
                            <dt class="text-espresso/65">Subtotal</dt><dd class="text-right">&#8369;{{ number_format($order->subtotal, 2) }}</dd>
                            @if((float) $order->discount > 0)<dt class="text-espresso/65">Discount</dt><dd class="text-right">-&#8369;{{ number_format($order->discount, 2) }}</dd>@endif
                            <dt class="text-espresso/65">Delivery fee</dt><dd class="text-right">&#8369;{{ number_format($order->delivery_fee, 2) }}</dd>
                            <dt class="font-semibold">Total</dt><dd class="text-right font-semibold">&#8369;{{ number_format($order->total, 2) }}</dd>
                            <dt class="text-espresso/65">Payment</dt><dd class="text-right">{{ strtoupper($order->payment_method) }} · {{ ucfirst($order->payment_status) }}</dd>
                            <dt class="text-espresso/65">Placed</dt><dd class="text-right">{{ $order->created_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                        </dl>
                        @if($order->delivery_address)
                            <p class="mt-3 text-sm"><span class="font-medium">Delivery address:</span> {{ $order->delivery_address }}</p>
                        @endif
                        @if($order->notes)
                            <p class="mt-2 text-sm"><span class="font-medium">Order note:</span> {{ $order->notes }}</p>
                        @endif
                        <p class="mt-3 text-sm"><span class="font-medium">Customer:</span> {{ $order->user?->name ?? 'Walk-in customer' }}{{ $phone ? ' · ' . $phone : '' }}</p>
                    </div>
                </details>
            </div>
        </div>
    </article>
@empty
    <p class="rounded-xl bg-white p-6 text-espresso/60">No active orders.</p>
@endforelse
</div>
