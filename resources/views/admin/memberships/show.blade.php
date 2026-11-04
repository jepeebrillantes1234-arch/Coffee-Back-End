@extends('layouts.admin')
@section('title', 'Membership Details')
@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-4"><div><a href="{{ route('admin.memberships.index') }}" class="text-sm text-caramel">← Memberships</a><h1 class="mt-2 text-2xl font-bold">{{ $membership->user?->name }}</h1><p>{{ $membership->user?->email }} · {{ $membership->user?->phone }}</p><p class="font-mono">{{ $membership->member_id }}</p></div><form method="POST" action="{{ route('admin.memberships.toggle',$membership) }}">@csrf<button class="rounded-lg bg-espresso px-4 py-2 text-white">{{ $membership->status==='active'?'Deactivate':'Activate' }}</button></form></div>
<div class="grid gap-4 md:grid-cols-3 mb-5"><div class="rounded-xl bg-white p-4"><small>Status</small><h2 class="text-xl font-bold">{{ ucfirst($membership->status) }}</h2></div><div class="rounded-xl bg-white p-4"><small>Current points</small><h2 class="text-xl font-bold">{{ number_format($membership->points_balance) }}</h2></div><div class="rounded-xl bg-white p-4"><small>Registered · Expiry</small><p>{{ $membership->created_at?->format('M j, Y') }} · {{ $membership->expires_at?->format('M j, Y') ?? 'No expiry' }}</p></div></div>
<section class="mb-5 rounded-xl bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold">Membership Card</h2>
            <p class="text-sm text-espresso/60">Personalized using the same member ID and QR identity as the customer app.</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $cardReady ? ($cardPrintCount > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900') : 'bg-gray-100 text-gray-600' }}">
            @if (!$cardReady)
                {{ $membership->expires_at?->isPast() ? 'Expired — Not Ready to Print' : 'Not Ready to Print' }}
            @elseif ($cardPrintCount > 0)
                Printed
            @else
                Ready to Print
            @endif
        </span>
    </div>

    @if ($cardReady)
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" onclick="document.getElementById('membership-card-preview').scrollIntoView({behavior:'smooth'})" class="rounded-lg border border-espresso/20 px-4 py-2 font-semibold text-espresso">Preview Card</button>
            <button type="button" onclick="window.print()" class="rounded-lg bg-espresso px-4 py-2 font-semibold text-white">Print Card</button>
            @if ($cardPrintCount === 0)
                <form method="POST" action="{{ route('admin.memberships.card.printed', $membership) }}" onsubmit="return confirm('Mark this membership card as printed?')">
                    @csrf
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">Mark as Printed</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.memberships.card.reprint', $membership) }}" onsubmit="window.print(); return confirm('Did the card reprint successfully? Record this reprint?')">
                    @csrf
                    <button class="rounded-lg border border-espresso/20 px-4 py-2 font-semibold text-espresso">Reprint Card</button>
                </form>
            @endif
        </div>

        <div id="membership-card-preview" class="mt-5 grid gap-6 lg:grid-cols-2">
            <div class="membership-print-side">
                <h3 class="mb-2 font-semibold print:hidden">Front</h3>
                <div class="membership-card-artboard">
                    <img class="membership-card-artwork" src="{{ $frontCardUrl }}" alt="Membership card front artwork">
                    <div class="membership-card-status">{{ $membership->isActive() ? 'ACTIVE MEMBER' : strtoupper($membership->status) }}</div>
                    <div class="membership-card-info">
                        <div class="membership-card-name">{{ $membership->user?->name ?? 'Customer' }}</div>
                        <div class="membership-card-id">MEMBER ID&nbsp; {{ $membership->member_id }}</div>
                        <div class="membership-card-points">{{ number_format($membership->points_balance) }} POINTS</div>
                        <div class="membership-card-since">MEMBER SINCE&nbsp; {{ ($membership->activated_at ?? $membership->created_at)?->format('m/y') ?? '—' }}</div>
                    </div>
                    <div class="membership-card-qr" data-membership-qr="{{ $membership->member_id }}" role="img" aria-label="Membership QR code"></div>
                </div>
            </div>
            <div class="membership-print-side">
                <h3 class="mb-2 font-semibold print:hidden">Back</h3>
                <div class="membership-card-artboard">
                    <img class="membership-card-artwork" src="{{ $backCardUrl }}" alt="Membership card back artwork">
                </div>
            </div>
        </div>

        @if ($cardPrintCount > 0)
            <div class="mt-5 overflow-x-auto">
                <h3 class="mb-2 font-semibold">Print History</h3>
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="p-2">Copy</th><th class="p-2">Printed by</th><th class="p-2">Date</th></tr></thead>
                    <tbody>
                    @foreach ($membership->cardPrints as $cardPrint)
                        <tr class="border-b">
                            <td class="p-2">{{ (int) $cardPrint->print_number === 1 ? 'Original' : 'Reprint ' . ($cardPrint->print_number - 1) }}</td>
                            <td class="p-2">{{ $cardPrint->admin?->name ?? 'Admin account removed' }}</td>
                            <td class="p-2">{{ $cardPrint->printed_at?->format('M j, Y g:i A') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <p class="mt-3 text-sm text-espresso/70">A personalized card is available after the membership is active and a payment is verified as paid.</p>
    @endif
</section>

<style>
.membership-card-artboard{container-type:inline-size;position:relative;width:100%;max-width:720px;aspect-ratio:1832/858;overflow:hidden;border-radius:14px;background:#fff;box-shadow:0 5px 18px #27180e22}
.membership-card-artwork{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.membership-card-status{position:absolute;left:65%;top:27.5%;width:30%;height:13%;display:flex;align-items:center;justify-content:center;box-sizing:border-box;padding:0 1.2%;border:1.2px solid #fff;border-radius:999px;background:#111;color:#fff;font-size:clamp(7.92px,2.62cqw,16.5px);font-weight:900;letter-spacing:.08em;white-space:nowrap}
.membership-card-info{position:absolute;left:5.5%;top:51%;width:47%;height:45%;display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-start;color:#fff;font-weight:700;line-height:1.25}
.membership-card-name{font-size:clamp(12.24px,4.05cqw,25.5px);font-weight:900;text-shadow:0 1px 4px #0008;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.membership-card-id{font-size:clamp(7.2px,2.38cqw,15px);white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
.membership-card-points{font-size:clamp(9.36px,3.1cqw,19.5px);font-weight:900}
.membership-card-since{font-size:clamp(6.48px,2.14cqw,13.5px);white-space:nowrap}
.membership-card-qr{position:absolute;left:55%;top:48.5%;width:18.5%;height:42.5%;padding:.9%;box-sizing:border-box;border-radius:8px;background:#fff}
.membership-card-qr canvas{display:block;width:100%;height:100%;image-rendering:pixelated}
@media print{
 @page{size:85.60mm 53.98mm;margin:0}
 body *{visibility:hidden!important}
 #membership-card-preview,#membership-card-preview *{visibility:visible!important}
 #membership-card-preview{position:fixed!important;inset:0!important;display:block!important;margin:0!important;padding:0!important;width:85.60mm!important}
 .membership-print-side{position:relative!important;display:block!important;width:85.60mm!important;height:53.98mm!important;margin:0!important;padding:0!important;overflow:hidden!important;break-after:page;page-break-after:always}
 .membership-print-side:last-child{break-after:auto;page-break-after:auto}
 .membership-print-side h3{display:none!important}
 .membership-card-artboard{position:absolute!important;left:0!important;top:50%!important;transform:translateY(-50%)!important;width:85.60mm!important;height:auto!important;aspect-ratio:1832/858!important;max-width:none!important;border-radius:0!important;box-shadow:none!important}
}
</style>
<section class="mb-5 rounded-xl bg-white p-4"><h2 class="mb-3 font-bold">Membership Payment Transactions</h2><div class="overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead><tr><th class="p-2">Date</th><th class="p-2">Reference</th><th class="p-2">Provider</th><th class="p-2">Amount</th><th class="p-2">Status</th></tr></thead><tbody>@forelse($membership->payments as $payment)<tr class="border-t"><td class="p-2">{{ $payment->created_at?->format('M j, Y g:i A') }}</td><td class="p-2 font-mono">{{ $payment->payment_reference }}</td><td class="p-2">{{ $payment->provider }}</td><td class="p-2">₱{{ number_format((float)$payment->amount,2) }}</td><td class="p-2">{{ ucfirst($payment->status) }}</td></tr>@empty<tr><td colspan="5" class="p-2">No payment attempts.</td></tr>@endforelse</tbody></table></div></section>
<section class="mb-5 rounded-xl bg-white p-4"><h2 class="mb-3 font-bold">Points History</h2><div class="overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead><tr><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Points</th><th class="p-2">Order</th><th class="p-2">Description</th></tr></thead><tbody>@forelse($membership->transactions as $tx)<tr class="border-t"><td class="p-2">{{ $tx->created_at?->format('M j, Y g:i A') }}</td><td class="p-2">{{ ucfirst($tx->type) }}</td><td class="p-2">{{ $tx->points>0?'+':'' }}{{ $tx->points }}</td><td class="p-2">{{ $tx->order?->order_number ?? '—' }}</td><td class="p-2">{{ $tx->description }}</td></tr>@empty<tr><td colspan="5" class="p-2">No point activity.</td></tr>@endforelse</tbody></table></div></section>
<section class="rounded-xl bg-white p-4"><h2 class="mb-3 font-bold">Reward Redemption History</h2><div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead><tr><th class="p-2">Reward</th><th class="p-2">Code</th><th class="p-2">Points</th><th class="p-2">Status</th><th class="p-2">Action</th></tr></thead><tbody>@forelse($membership->redemptions as $row)<tr class="border-t"><td class="p-2">{{ $row->reward_name }}</td><td class="p-2 font-mono">{{ $row->code }}</td><td class="p-2">{{ $row->points_spent }}</td><td class="p-2">{{ ucfirst($row->status) }}</td><td class="p-2">@if($row->status==='pending')<form method="POST" action="{{ route('admin.redemptions.claim',$row) }}">@csrf<button class="text-caramel">Mark claimed</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="p-2">No reward redemptions.</td></tr>@endforelse</tbody></table></div></section>
@vite('resources/js/admin-membership-card.js')
@endsection