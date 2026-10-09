@extends('layouts.staff')
@section('title', 'Orders')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Order Queue</h1>
    <div class="flex items-center gap-3 text-sm">
        <span class="text-espresso/60">Nag-a-update tuwing 8 segundo <span class="inline-block w-2 h-2 rounded-full bg-success ml-1"></span></span>
        <button id="sound-btn" type="button" class="px-3 py-1.5 rounded-lg border border-espresso/20 bg-white">🔕 I-enable ang tunog</button>
    </div>
</div>

<div id="queue">
    @include('staff.orders._list')
</div>
@endsection

@push('scripts')
<script>
const URL_PARTIAL = @json(route('staff.orders.partial'));
const box = document.getElementById('queue');
const soundBtn = document.getElementById('sound-btn');
let lastPending = {{ $pendingCount }};
let soundOn = localStorage.getItem('staff_sound') === '1';
let ctx = null;

function paintBtn() { soundBtn.textContent = soundOn ? '🔔 Naka-on ang tunog' : '🔕 I-enable ang tunog'; }
function beep() {
  if (!soundOn) return;
  try {
    ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
    [880, 1175, 880].forEach((f, i) => {
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = f; g.gain.value = 0.25;
      o.connect(g); g.connect(ctx.destination);
      o.start(ctx.currentTime + i * 0.28); o.stop(ctx.currentTime + i * 0.28 + 0.2);
    });
  } catch (e) {}
}
soundBtn.addEventListener('click', () => {
  soundOn = !soundOn;
  localStorage.setItem('staff_sound', soundOn ? '1' : '0');
  if (soundOn) { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); beep(); }
  paintBtn();
});
paintBtn();

function setBadge(n) {
  const b = document.getElementById('nav-pending');
  if (b) { b.textContent = n; b.classList.toggle('hidden', n === 0); }
  document.title = (n > 0 ? '(' + n + ') ' : '') + 'Orders | Coffee Shop';
}
setBadge(lastPending);

async function refresh() {
  // Huwag mag-refresh habang nagta-type ang staff (hal. dahilan ng reject)
  const a = document.activeElement;
  if (a && box.contains(a) && ['INPUT', 'SELECT', 'TEXTAREA'].includes(a.tagName)) return;
  try {
    const res = await fetch(URL_PARTIAL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) return;
    const pending = parseInt(res.headers.get('X-Pending') || '0', 10);
    box.innerHTML = await res.text();
    if (pending > lastPending) beep();
    lastPending = pending;
    setBadge(pending);
  } catch (e) {}
}
setInterval(refresh, 8000);
</script>
@endpush
