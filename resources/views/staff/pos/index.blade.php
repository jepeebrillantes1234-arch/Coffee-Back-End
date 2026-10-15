@extends('layouts.staff')
@section('title', 'Walk-in POS')
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
    {{-- ============ MENU ============ --}}
    <div class="lg:col-span-2">
        <div class="flex flex-wrap gap-3 mb-3">
            <input id="search" placeholder="Search item..." class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-64 bg-white">
            <div id="cats" class="flex flex-wrap gap-2"></div>
        </div>
        <div id="grid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3"></div>
    </div>

    {{-- ============ CURRENT ORDER ============ --}}
    <div class="bg-white rounded-2xl shadow-sm p-5 h-fit lg:sticky lg:top-4">
        <p class="font-semibold text-lg mb-3">🧾 Current Order</p>
        <div id="cart" class="space-y-3 max-h-80 overflow-y-auto text-sm"></div>

        <div class="border-t border-espresso/10 mt-4 pt-4 space-y-3">
            <div class="flex justify-between text-lg font-semibold"><span>Total</span><span id="total">₱0.00</span></div>

            <input id="cust" maxlength="60" placeholder="Customer name (optional)" class="w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm">

            <div>
                <label class="text-sm font-medium">Cash received (₱)</label>
                <input id="cash" type="number" min="0" step="0.01" class="w-full rounded-lg border border-espresso/20 px-3 py-2 text-lg">
                <div id="quick" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <div class="flex justify-between text-sm"><span>Change</span><span id="change" class="font-semibold">₱0.00</span></div>
            <p id="msg" class="text-sm text-red-600"></p>

            <button id="pay" disabled class="w-full bg-espresso text-cream py-3 rounded-xl font-medium disabled:opacity-40">💵 Pay and Send to Kitchen</button>
            <button id="clear" type="button" class="w-full text-sm text-espresso/60">Clear order</button>
        </div>

        <div id="done" class="hidden mt-4 rounded-xl bg-success/10 border border-success/30 p-3 text-sm"></div>
    </div>
</div>

{{-- ============ CUSTOMIZATION MODAL ============ --}}
<div id="modal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
        <div class="flex justify-between items-start mb-3">
            <h3 id="m-name" class="text-xl font-semibold"></h3>
            <button id="m-close" class="text-2xl leading-none text-espresso/50">×</button>
        </div>
        <div id="m-groups" class="space-y-4"></div>
        <input id="m-notes" placeholder="Note (optional)" class="mt-4 w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <div class="flex items-center justify-between mt-4">
            <div class="flex items-center gap-3">
                <button id="m-minus" class="w-9 h-9 rounded-full border border-espresso/20 text-lg">−</button>
                <span id="m-qty" class="w-6 text-center font-semibold">1</span>
                <button id="m-plus" class="w-9 h-9 rounded-full border border-espresso/20 text-lg">+</button>
            </div>
            <button id="m-add" class="bg-espresso text-cream px-5 py-2.5 rounded-xl font-medium"></button>
        </div>
        <p id="m-err" class="text-sm text-red-600 mt-2"></p>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CATALOG = @json($catalog);
const PAY_URL = @json(route('staff.pos.store'));
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const $ = id => document.getElementById(id);
const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

let cart = [];            // { key, productId, name, unit, qty, optionIds, optionNames, notes }
let activeCat = null;
let current = null;       // product in modal
let mQty = 1;

// ---------- MENU ----------
function renderCats() {
  const mk = (id, name) => `<button data-cat="${id ?? ''}" class="px-3 py-1.5 rounded-full text-sm border ${activeCat === id ? 'bg-espresso text-cream border-espresso' : 'bg-white border-espresso/20'}">${esc(name)}</button>`;
  $('cats').innerHTML = mk(null, 'All') + CATALOG.categories.map(c => mk(c.id, c.name)).join('');$('cats').querySelectorAll('button').forEach(b => b.onclick = () => { activeCat = b.dataset.cat === '' ? null : Number(b.dataset.cat); renderCats(); renderGrid(); });
}
function renderGrid() {
  const q = $('search').value.trim().toLowerCase();
  const list = CATALOG.products.filter(p => (activeCat === null || p.category_id === activeCat) && (!q || p.name.toLowerCase().includes(q)));
  $('grid').innerHTML = list.length ? list.map(p => `
    <button data-id="${p.id}" class="bg-white rounded-2xl shadow-sm overflow-hidden text-left hover:ring-2 hover:ring-caramel">
      ${p.image ? `<img src="${esc(p.image)}" class="w-full h-24 object-cover">` : `<div class="w-full h-24 bg-cream flex items-center justify-center text-3xl">☕</div>`}
      <div class="p-3"><p class="font-medium text-sm leading-tight">${esc(p.name)}</p><p class="text-caramel font-semibold text-sm mt-1">${peso(p.price)}</p></div>
    </button>`).join('') : '<p class="col-span-full text-espresso/50">No items found.</p>';
  $('grid').querySelectorAll('button').forEach(b => b.onclick = () => openProduct(Number(b.dataset.id)));
}
$('search').addEventListener('input', renderGrid);

// ---------- MODAL ----------
function openProduct(id) {
  current = CATALOG.products.find(p => p.id === id);
  if (!current.groups.length) { addToCart(current, [], [], '', 1); return; }
  mQty = 1; $('m-qty').textContent = 1; $('m-notes').value = ''; $('m-err').textContent = '';$('m-name').textContent = current.name;
  $('m-groups').innerHTML = current.groups.map(g => `
    <div><p class="font-medium text-sm mb-1">${esc(g.name)} <span class="text-xs ${g.required ? 'text-caramel' : 'text-espresso/40'}">${g.required ? 'Required' : 'Optional'}${g.multiple ? ' · up to ' + g.max : ''}</span></p>
      <div class="flex flex-wrap gap-2">${g.options.map((o, i) => `
        <label class="cursor-pointer"><input type="${g.multiple ? 'checkbox' : 'radio'}" name="g${g.id}" value="${o.id}" data-price="${o.price}" data-name="${esc(o.name)}" data-g="${g.id}" class="peer hidden" ${(g.required && !g.multiple && i === 0) ? 'checked' : ''}>
          <span class="inline-block px-3 py-1.5 rounded-full border border-espresso/20 text-sm peer-checked:bg-caramel peer-checked:text-white peer-checked:border-caramel">${esc(o.name)}${o.price > 0 ? ' +₱' + o.price : ''}</span></label>`).join('')}
      </div></div>`).join('');
  $('m-groups').querySelectorAll('input').forEach(i => i.onchange = () => { enforceMax(i); updateModal(); });
  updateModal();
  $('modal').classList.remove('hidden');$('modal').classList.add('flex');
}
function enforceMax(input) {
  const g = current.groups.find(x => x.id === Number(input.dataset.g));
  if (!g.multiple) return;
  const checked = $('m-groups').querySelectorAll(`input[data-g="${g.id}"]:checked`);
  if (checked.length > g.max) { input.checked = false; $('m-err').textContent = `Maximum of ${g.max} for ${g.name}.`; } else $('m-err').textContent = '';
}
function modalSelection() {
  const picked = [...$('m-groups').querySelectorAll('input:checked')];
  return { ids: picked.map(i => Number(i.value)), names: picked.map(i => i.dataset.name), extra: picked.reduce((s, i) => s + Number(i.dataset.price), 0) };
}
function updateModal() {
  const s = modalSelection();
  $('m-add').textContent = 'Add · ' + peso((current.price + s.extra) * mQty);
}
$('m-minus').onclick = () => { if (mQty > 1) { mQty--; $('m-qty').textContent = mQty; updateModal(); } };$('m-plus').onclick = () => { mQty++; $('m-qty').textContent = mQty; updateModal(); };$('m-close').onclick = closeModal;
function closeModal() { $('modal').classList.add('hidden'); $('modal').classList.remove('flex'); }$('m-add').onclick = () => {
  for (const g of current.groups) {
    if (g.required && !$('m-groups').querySelector(`input[data-g="${g.id}"]:checked`)) { $('m-err').textContent = 'Please select ' + g.name + '.'; return; }
  }
  const s = modalSelection();
  addToCart(current, s.ids, s.names, $('m-notes').value.trim(), mQty, current.price + s.extra);
  closeModal();
};

// ---------- CART ----------
function addToCart(p, ids, names, notes, qty, unit) {
  const u = unit ?? p.price;
  const key = p.id + '|' + [...ids].sort().join(',') + '|' + notes;
  const found = cart.find(l => l.key === key);
  if (found) found.qty += qty; else cart.push({ key, productId: p.id, name: p.name, unit: u, qty, optionIds: ids, optionNames: names, notes });
  renderCart();
}
function total() { return cart.reduce((s, l) => s + l.unit * l.qty, 0); }
function renderCart() {
  $('cart').innerHTML = cart.length ? cart.map((l, i) => `
    <div class="flex justify-between gap-2">
      <div class="flex-1"><p class="font-medium">${esc(l.name)}</p>
        ${l.optionNames.length ? `<p class="text-xs text-espresso/60">${esc(l.optionNames.join(', '))}</p>` : ''}
        ${l.notes ? `<p class="text-xs text-espresso/50">📝 ${esc(l.notes)}</p>` : ''}
        <div class="flex items-center gap-2 mt-1">
          <button data-i="${i}" data-d="-1" class="w-6 h-6 rounded-full border border-espresso/20">−</button>
          <span>${l.qty}</span>
          <button data-i="${i}" data-d="1" class="w-6 h-6 rounded-full border border-espresso/20">+</button>
        </div></div>
      <div class="text-right font-medium">${peso(l.unit * l.qty)}</div>
    </div>`).join('') : '<p class="text-espresso/50">No items yet. Select from menu.</p>';
  $('cart').querySelectorAll('button').forEach(b => b.onclick = () => { const i = Number(b.dataset.i); cart[i].qty += Number(b.dataset.d); if (cart[i].qty <= 0) cart.splice(i, 1); renderCart(); });$('total').textContent = peso(total());
  renderQuick(); updatePay();
}
function renderQuick() {
  const t = total();
  const opts = [...new Set([Math.ceil(t), 100, 200, 500, 1000].filter(v => v >= t && t > 0))].sort((a, b) => a - b).slice(0, 5);
  $('quick').innerHTML = opts.map(v => `<button data-v="${v}" class="px-3 py-1 rounded-lg border border-espresso/20 text-sm">${v === Math.ceil(t) ? 'Exact ' : ''}₱${v}</button>`).join('');
  $('quick').querySelectorAll('button').forEach(b => b.onclick = () => {$('cash').value = b.dataset.v; updatePay(); });
}
function updatePay() {
  const t = total(), c = parseFloat($('cash').value || '0');
  $('change').textContent = peso(Math.max(0, c - t));$('pay').disabled = !(cart.length && c + 0.001 >= t);
}
$('cash').addEventListener('input', updatePay);$('clear').onclick = () => { cart = []; $('cash').value = '';$('cust').value = ''; renderCart(); };

// ---------- PAYMENT ----------
$('pay').onclick = async () => {
  $('msg').textContent = '';$('pay').disabled = true;
  try {
    const res = await fetch(PAY_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({
        cash: parseFloat($('cash').value),
        customer_name: $('cust').value.trim(),
        items: cart.map(l => ({ product_id: l.productId, quantity: l.qty, option_ids: l.optionIds, notes: l.notes || null })),
      }),
    });
    const json = await res.json();
    if (!res.ok) {
      const first = json.errors ? Object.values(json.errors)[0] : null;
      throw new Error((Array.isArray(first) ? first[0] : first) || json.message || 'An error occurred.');
    }
    const d = json.data;
    $('done').classList.remove('hidden');$('done').innerHTML = `✅ <b>${esc(d.order_number)}</b> · Total ${peso(d.total)} · Change <b>${peso(d.change)}</b><br>
      <a href="${esc(d.receipt_url)}" target="_blank" class="inline-block mt-2 px-3 py-1.5 rounded-lg bg-espresso text-cream">🖨 Print receipt</a>`;
    cart = []; $('cash').value = '';$('cust').value = ''; renderCart();
  } catch (e) {
    $('msg').textContent = e.message; updatePay();
  }
};

renderCats(); renderGrid(); renderCart();
</script>
@endpush