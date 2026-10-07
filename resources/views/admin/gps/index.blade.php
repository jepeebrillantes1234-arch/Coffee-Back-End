@extends('layouts.admin')
@section('title', 'GPS Monitor')
@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">GPS Monitor</h1>
    <span class="text-xs text-espresso/60">Nag-a-update tuwing 5 segundo <span id="pulse" class="inline-block w-2 h-2 rounded-full bg-success ml-1"></span></span>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
        <div id="map" class="w-full h-[520px] bg-cream"></div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <p class="font-medium mb-3">Mga Rider</p>
        <ul id="rider-list" class="space-y-3 text-sm"><li class="text-espresso/50">Naglo-load...</li></ul>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const SHOP = @json($shop);
const DATA_URL = @json(route('admin.gps.data'));
let map = null, markers = {}, fitted = false;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function initMap() {
  map = L.map('map').setView([SHOP.lat, SHOP.lng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  L.marker([SHOP.lat, SHOP.lng]).addTo(map).bindPopup('Coffee Shop');
}

function renderList(riders) {
  const ul = document.getElementById('rider-list');
  if (!riders.length) { ul.innerHTML = '<li class="text-espresso/50">Walang rider na may GPS.</li>'; return; }
  ul.innerHTML = riders.map(r => `
    <li class="border border-espresso/10 rounded-lg p-3">
      <div class="flex justify-between"><span class="font-medium">${esc(r.name)}</span><span>${r.available ? '🟢' : '⚪'}</span></div>
      <div class="text-xs text-espresso/60">${r.order ? 'Order ' + esc(r.order) + ' · ' + esc(r.status) : 'Walang aktibong delivery'}</div>
      <div class="text-xs text-espresso/40">${esc(r.updated || '')}</div>
    </li>`).join('');
}

function renderMarkers(riders) {
  if (!map) return;
  const seen = new Set();
  riders.forEach(r => {
    seen.add(r.id);
    const pos = [r.lat, r.lng];
    if (markers[r.id]) markers[r.id].setLatLng(pos);
    else markers[r.id] = L.marker(pos).addTo(map).bindPopup(esc(r.name));
  });
  Object.keys(markers).forEach(id => { if (!seen.has(Number(id))) { map.removeLayer(markers[id]); delete markers[id]; } });
  if (!fitted && riders.length) {
    const points = [[SHOP.lat, SHOP.lng], ...riders.map(r => [r.lat, r.lng])];
    map.fitBounds(L.latLngBounds(points), { padding: [30, 30], maxZoom: 15 }); fitted = true;
  }
}

async function refresh() {
  try {
    const res = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' } });
    const json = await res.json();
    renderList(json.riders); renderMarkers(json.riders);
  } catch (e) { console.error(e); }
}

initMap();
refresh(); setInterval(refresh, 5000);
</script>
@endpush
