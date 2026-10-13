@extends('layouts.staff')
@section('title', 'Menu / Sold out')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Menu · Availability</h1>
    <input id="q" placeholder="Search item..." class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-64 bg-white">
</div>
<p class="text-sm text-espresso/60 mb-5">When an item is marked as <b>Sold out</b>, it will no longer appear in the Customer App and POS.</p>

@foreach ($categories as $c)
    @if ($c->products->isNotEmpty())
        <h2 class="font-semibold mt-6 mb-2">{{ $c->name }}</h2>
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3">
        @foreach ($c->products as $p)
            <div class="item bg-white rounded-xl shadow-sm p-3 flex items-center gap-3" data-name="{{ strtolower($p->name) }}">
                @if ($p->image) <img src="{{ asset('storage/' . $p->image) }}" class="w-12 h-12 rounded-lg object-cover">
                @else <div class="w-12 h-12 rounded-lg bg-cream flex items-center justify-center">☕</div> @endif
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate">{{ $p->name }}</p>
                    <p class="text-xs text-espresso/60">₱{{ number_format($p->base_price, 2) }}</p>
                </div>
                <form method="POST" action="{{ route('staff.menu.toggle', $p) }}">@csrf
                    <button class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $p->is_available ? 'bg-success/10 text-success' : 'bg-red-100 text-red-700' }}">
                        {{ $p->is_available ? '● Available' : '○ Sold out' }}
                    </button>
                </form>
            </div>
        @endforeach
        </div>
    @endif
@endforeach
@endsection

@push('scripts')
<script>
document.getElementById('q').addEventListener('input', e => {
  const q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('.item').forEach(el => el.classList.toggle('hidden', q && !el.dataset.name.includes(q)));
});
</script>
@endpush