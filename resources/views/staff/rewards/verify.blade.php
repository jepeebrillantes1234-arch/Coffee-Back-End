@extends('layouts.staff')
@section('title','Verify Rewards')
@section('content')
<div class="mx-auto max-w-2xl px-4 py-8"><h1 class="mb-2 text-2xl font-bold">Verify Reward Redemption</h1><p class="mb-6 text-sm text-espresso/60">Enter the redemption code shown by the customer to validate and claim the reward.</p>
<form method="POST" action="{{ route('staff.rewards.claim') }}" class="mb-6 flex flex-wrap gap-3 rounded-xl bg-white p-5 shadow-sm">@csrf<input required name="code" value="{{ old('code', $redemption?->code) }}" placeholder="KJMR-..." class="min-w-0 flex-1 rounded-lg border px-4 py-3 font-mono uppercase"><button class="rounded-lg bg-espresso px-5 py-3 text-white">Verify &amp; Claim</button></form>
@if($redemption)<section class="rounded-xl bg-white p-5 shadow-sm"><h2 class="text-lg font-bold">{{ $redemption->reward_name }}</h2><p>Customer: {{ $redemption->user?->name }} · {{ $redemption->user?->email }}</p><p>Member: {{ $redemption->membership?->member_id }}</p><p>Points used: {{ $redemption->points_spent }}</p><p>Code: <span class="font-mono font-bold">{{ $redemption->code }}</span></p><p class="mt-2">Status: <strong>{{ strtoupper($redemption->status) }}</strong></p>@if($redemption->claimed_at)<p>Claimed {{ $redemption->claimed_at->format('M j, Y g:i A') }}</p>@endif</section>@endif
</div>
@endsection