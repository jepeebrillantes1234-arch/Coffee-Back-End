@extends('layouts.admin')
@section('title', 'Reviews')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Reviews</h1>
    <form method="GET">
        <select name="rating" onchange="this.form.submit()" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
            <option value="">All ratings</option>
            @for ($i = 5; $i >= 1; $i--) <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} ★</option> @endfor
        </select>
    </form>
</div>
<div class="space-y-4">
@forelse ($reviews as $r)
    <div class="bg-white rounded-2xl shadow-sm p-5 {{ $r->is_visible ? '' : 'opacity-60' }}">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-amber-500">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</p>
                <p class="text-sm mt-1">{{ $r->comment ?: '(no comment)' }}</p>
                <p class="text-xs text-espresso/50 mt-1">{{ $r->user->name ?? '—' }} · <a class="text-caramel" href="{{ route('admin.orders.show', $r->order_id) }}">{{ $r->order->order_number ?? '' }}</a> · {{ $r->created_at->format('M d, Y') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.reviews.toggle', $r) }}">@csrf
                <button class="text-sm {{ $r->is_visible ? 'text-red-600' : 'text-success' }}">{{ $r->is_visible ? 'Hide' : 'Show' }}</button>
            </form>
        </div>
        <form method="POST" action="{{ route('admin.reviews.reply', $r) }}" class="mt-3 flex gap-2">
            @csrf @method('PUT')
            <input name="admin_reply" value="{{ $r->admin_reply }}" placeholder="Shop reply..." class="flex-1 rounded-lg border border-espresso/20 px-3 py-1.5 text-sm">
            <button class="bg-espresso text-cream px-3 py-1.5 rounded-lg text-sm">Save</button>
        </form>
    </div>
@empty
    <div class="bg-white rounded-2xl p-6 text-center text-espresso/50">No reviews yet.</div>
@endforelse
</div>
<div class="mt-4">{{ $reviews->links() }}</div>
@endsection