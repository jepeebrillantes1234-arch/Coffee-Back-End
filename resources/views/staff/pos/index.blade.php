@extends('layouts.staff')
@section('title', 'Walk-in POS')
@section('content')
<style>
    .pos-page{--pos-ink:#342017;--pos-brown:#5a2e18;--pos-caramel:#a85b2a;--pos-cream:#fbf6ef;color:var(--pos-ink)}
    .pos-page .pos-heading-icon{display:grid;place-items:center;width:58px;height:58px;border-radius:50%;background:linear-gradient(145deg,#914d25,#542b18);color:#fff;font-size:28px;box-shadow:0 8px 20px #5a2e1826}
    .pos-page .pos-toolbar,.pos-page .pos-order{background:#fff;border:1px solid #eee5dc;border-radius:20px;box-shadow:0 7px 24px #5a2e180b}
    .pos-page .pos-cat{display:inline-flex;align-items:center;gap:8px;white-space:nowrap;border:1px solid #eee3d8;border-radius:999px;background:#f8f2ec;padding:10px 17px;font-size:13px;font-weight:600;color:#493226;transition:.16s}
    .pos-page .pos-cat:hover{border-color:#c68a5b;transform:translateY(-1px)}
    .pos-page .pos-cat-image{width:28px;height:28px;flex:0 0 28px;border-radius:50%;object-fit:cover;background:#fff}
    .pos-page .pos-cat-fallback{display:grid;place-items:center;width:28px;height:28px;flex:0 0 28px;border-radius:50%;background:#fff8f0}
    .pos-page .pos-cat.active{background:linear-gradient(135deg,#78401f,#512916);border-color:#69391d;color:#fff;box-shadow:0 4px 12px #60351f24}
    .pos-page .pos-card{position:relative;overflow:hidden;border:1px solid #eee6df;border-radius:17px;background:white;box-shadow:0 5px 15px #3f27140d;transition:transform .18s,box-shadow .18s}
    .pos-page .pos-card:hover{transform:translateY(-3px);box-shadow:0 10px 24px #3f27141c}
    .pos-page .pos-card-image{width:100%;height:clamp(105px,11vw,150px);object-fit:cover;background:#f4ebe2}
    .pos-page .pos-card-add{display:grid;place-items:center;width:30px;height:30px;border:0;border-radius:50%;background:#914d25;color:#fff;font-size:21px;line-height:1;box-shadow:0 3px 8px #5a2e1830}
    .pos-page .pos-cart-list{max-height:min(43vh,440px);overflow:auto;scrollbar-width:thin}
    .pos-page .pos-cart-item+.pos-cart-item{border-top:1px solid #f0e9e2}
    .pos-page .pos-qty button{display:grid;place-items:center;width:27px;height:27px;border:1px solid #eee4db;border-radius:9px;background:#fff;color:#55301c;font-weight:700}
    .pos-page .pos-field{width:100%;border:1px solid #e9dfd5;border-radius:11px;background:#fff;padding:10px 12px;font-size:13px}
    .pos-page .pos-primary{border-radius:12px;background:linear-gradient(110deg,#82451f,#5a2d17);color:#fff;box-shadow:0 6px 14px #6b391f24}
    @media(max-width:1023px){.pos-page .pos-cart-list{max-height:360px}}
    @media(max-width:640px){.pos-page .pos-card-image{height:110px}.pos-page .pos-cat{padding:9px 13px}.pos-page .pos-order{position:static!important}}
</style>
<div class="pos-page">
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_350px]">
        <section class="min-w-0">
            <div class="mb-5 flex flex-wrap items-center gap-4 px-1">
        <span class="pos-heading-icon" aria-hidden="true">☕</span>
        <div><h1 class="!mb-1 !text-[23px] !text-[#542c18]">{{ now()->format('H') < 12 ? 'Good Morning' : (now()->format('H') < 18 ? 'Good Afternoon' : 'Good Evening') }}, {{ auth()->user()->name }}!</h1><p class="text-sm text-espresso/60">Take orders, serve with a smile <span class="text-[#d58a6d]">♥</span></p></div>
    </div>

            <div class="pos-toolbar mb-4 flex flex-wrap items-center gap-2 p-3">
                <div class="flex min-w-0 flex-1 flex-wrap gap-2" id="cats" aria-label="Filter products by category"></div>
                <label class="relative min-w-[190px] flex-1 sm:max-w-[240px]">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-caramel">⌕</span>
                    <input id="search" type="search" placeholder="Search menu item..." class="pos-field !rounded-full !bg-[#faf6f1] !pl-10">
                </label>
            </div>
            <div id="grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-4" aria-live="polite"></div>
        </section>

        <aside class="pos-order h-fit p-4 sm:p-5 xl:sticky xl:top-4" aria-label="Current order">
            <div class="mb-4 flex items-start gap-3 border-b border-[#f0e9e2] pb-4">
                <span class="text-2xl text-caramel" aria-hidden="true">▤</span>
                <div><h2 class="text-lg font-semibold text-[#30231d]">Current Order</h2><p class="mt-1 text-xs text-espresso/55">Add items from the menu to create an order.</p></div>
            </div>
            <div id="cart" class="pos-cart-list"></div>
            <div class="mt-3 border-t border-[#f0e9e2] pt-3">
                <label for="cust" class="mb-1 block text-xs font-medium text-espresso/70">Customer name (optional)</label>
                <input id="cust" maxlength="60" placeholder="Enter customer name" class="pos-field">
            </div>
            <div class="mt-3 flex justify-between text-sm"><span>Subtotal</span><span id="subtotal" class="font-medium">₱0.00</span></div>
            <div class="mt-2 flex justify-between text-lg font-bold"><span>Total</span><span id="total">₱0.00</span></div>
            <div class="mt-3">
                <label for="cash" class="mb-1 block text-xs font-medium text-espresso/70">Cash received (₱)</label>
                <input id="cash" type="number" min="0" step="0.01" inputmode="decimal" class="pos-field !text-base">
                <div id="quick" class="mt-2 flex flex-wrap gap-2"></div>
            </div>
            <div class="mt-3 flex justify-between text-sm"><span>Change</span><span id="change" class="font-semibold">₱0.00</span></div>
            <p id="msg" class="mt-2 text-sm text-red-600" role="alert"></p>
            <button id="pay" disabled class="pos-primary mt-3 w-full px-4 py-3 font-semibold disabled:cursor-not-allowed disabled:opacity-40">▣ &nbsp; Pay and Send to Kitchen <span aria-hidden="true">→</span></button>
            <button id="clear" type="button" class="mt-2 w-full rounded-xl border border-[#b98768] px-4 py-2.5 text-sm font-medium text-[#75401f] hover:bg-[#fbf5ef]">♲ &nbsp; Clear Order</button>
            <div class="mt-4 flex items-center gap-3 rounded-xl bg-[#f8f0e6] p-3 text-sm"><span class="text-2xl">♨</span><div><strong class="block">Preparing great food...</strong><span class="text-xs text-espresso/60">Fresh ingredients. Better moments.</span></div></div>
            <div id="done" class="hidden mt-4 rounded-xl border border-success/30 bg-success/10 p-3 text-sm" role="status"></div>
        </aside>
    </div>
</div>

<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/55 p-4">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
        <div class="mb-3 flex items-start justify-between"><h3 id="m-name" class="text-xl font-semibold"></h3><button id="m-close" type="button" class="text-2xl leading-none text-espresso/50" aria-label="Close">×</button></div>
        <div id="m-groups" class="space-y-4"></div>
        <input id="m-notes" maxlength="255" placeholder="Note (optional)" class="pos-field mt-4">
        <div class="mt-4 flex items-center justify-between"><div class="flex items-center gap-3"><button id="m-minus" type="button" class="h-9 w-9 rounded-full border border-espresso/20">−</button><span id="m-qty" class="w-6 text-center font-semibold">1</span><button id="m-plus" type="button" class="h-9 w-9 rounded-full border border-espresso/20">+</button></div><button id="m-add" type="button" class="rounded-xl bg-espresso px-5 py-2.5 font-medium text-cream"></button></div>
        <p id="m-err" class="mt-2 text-sm text-red-600" role="alert"></p>
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
let cart = [];
let activeCat = null;
let current = null;
let mQty = 1;

function categoryIcon(name) {
  const value = String(name).toLowerCase();
  if (value.includes('hot')) return '☕';
  if (value.includes('iced') || value.includes('cold')) return '🥤';
  if (value.includes('non')) return '♨';
  if (value.includes('pastry') || value.includes('food') || value.includes('bread')) return '♧';
  return '▦';
}
function renderCats() {
  const button = (id, name, image = null) => `<button type="button" data-cat="${id ?? ''}" class="pos-cat ${activeCat === id ? 'active' : ''}">${id === null ? '<span class="pos-cat-fallback">▦</span>' : (image ? `<img class="pos-cat-image" src="${esc(image)}" alt="" loading="lazy">` : `<span class="pos-cat-fallback">${categoryIcon(name)}</span>`)}${esc(name)}</button>`;
  $('cats').innerHTML = button(null, 'All') + CATALOG.categories.map(c => button(Number(c.id), c.name, c.image)).join('');
  $('cats').querySelectorAll('[data-cat]').forEach(b => b.addEventListener('click', () => { activeCat = b.dataset.cat === '' ? null : Number(b.dataset.cat); renderCats(); renderGrid(); }));
}
function openProduct(id) {
  current = CATALOG.products.find(p => Number(p.id) === id);
  if (!current) return;
  if (!current.groups?.length) { addToCart(current, [], [], '', 1); return; }
  mQty = 1; $('m-qty').textContent = '1'; $('m-notes').value = ''; $('m-err').textContent = ''; $('m-name').textContent = current.name;
  $('m-groups').innerHTML = current.groups.map(g => `<div><p class="mb-1 text-sm font-medium">${esc(g.name)} <span class="text-xs ${g.required ? 'text-caramel' : 'text-espresso/40'}">${g.required ? 'Required' : 'Optional'}${g.multiple ? ' · up to ' + g.max : ''}</span></p><div class="flex flex-wrap gap-2">${g.options.map((o, i) => `<label class="cursor-pointer"><input type="${g.multiple ? 'checkbox' : 'radio'}" name="g${g.id}" value="${o.id}" data-price="${o.price}" data-name="${esc(o.name)}" data-g="${g.id}" class="peer hidden" ${(g.required && !g.multiple && i === 0) ? 'checked' : ''}><span class="inline-block rounded-full border border-espresso/20 px-3 py-1.5 text-sm peer-checked:border-caramel peer-checked:bg-caramel peer-checked:text-white">${esc(o.name)}${o.price > 0 ? ' +' + peso(o.price) : ''}</span></label>`).join('')}</div></div>`).join('');
  $('m-groups').querySelectorAll('input').forEach(i => i.addEventListener('change', () => { enforceMax(i); updateModal(); }));
  updateModal(); $('modal').classList.remove('hidden'); $('modal').classList.add('flex');
}
function enforceMax(input) {
  const group = current.groups.find(x => Number(x.id) === Number(input.dataset.g));
  if (!group?.multiple) return;
  const checked = $('m-groups').querySelectorAll(`input[data-g="${group.id}"]:checked`);
  if (checked.length > group.max) { input.checked = false; $('m-err').textContent = `Maximum of ${group.max} for ${group.name}.`; } else $('m-err').textContent = '';
}
function modalSelection() {
  const picked = [...$('m-groups').querySelectorAll('input:checked')];
  return { ids: picked.map(i => Number(i.value)), names: picked.map(i => i.dataset.name), extra: picked.reduce((sum, i) => sum + Number(i.dataset.price), 0) };
}
function updateModal() { const selection = modalSelection(); $('m-add').textContent = 'Add · ' + peso((current.price + selection.extra) * mQty); }
function closeModal() { $('modal').classList.add('hidden'); $('modal').classList.remove('flex'); }
$('m-minus').onclick = () => { if (mQty > 1) { mQty--; $('m-qty').textContent = mQty; updateModal(); } };
$('m-plus').onclick = () => { mQty++; $('m-qty').textContent = mQty; updateModal(); };
$('m-close').onclick = closeModal;
$('modal').addEventListener('click', event => { if (event.target === $('modal')) closeModal(); });
$('m-add').onclick = () => {
  for (const group of current.groups) if (group.required && !$('m-groups').querySelector(`input[data-g="${group.id}"]:checked`)) { $('m-err').textContent = 'Please select ' + group.name + '.'; return; }
  const selection = modalSelection(); addToCart(current, selection.ids, selection.names, $('m-notes').value.trim(), mQty, current.price + selection.extra); closeModal();
};
function renderGrid() {
  const query = $('search').value.trim().toLowerCase();
  const products = CATALOG.products.filter(p => (activeCat === null || Number(p.category_id) === activeCat) && (!query || p.name.toLowerCase().includes(query)));
  $('grid').innerHTML = products.length ? products.map(p => `<article class="pos-card"><button type="button" data-id="${p.id}" class="block w-full text-left" aria-label="Add ${esc(p.name)} to order">${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.name)}" class="pos-card-image" loading="lazy">` : '<div class="pos-card-image flex items-center justify-center text-4xl text-caramel">☕</div>'}<span class="flex min-h-[68px] items-center justify-between gap-2 p-3"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-[#38261c]">${esc(p.name)}</span><span class="mt-1 block text-sm font-bold text-caramel">${peso(p.price)}</span></span><span class="pos-card-add shrink-0" aria-hidden="true">+</span></span></button></article>`).join('') : '<p class="col-span-full rounded-xl bg-white p-6 text-sm text-espresso/55">No menu items match your search.</p>';
  $('grid').querySelectorAll('[data-id]').forEach(b => b.addEventListener('click', () => openProduct(Number(b.dataset.id))));
}
$('search').addEventListener('input', renderGrid);
function addToCart(p, ids, names, notes, qty, unit) {
  const price = unit ?? p.price;
  const key = p.id + '|' + [...ids].sort().join(',') + '|' + notes;
  const found = cart.find(line => line.key === key);
  if (found) found.qty += qty; else cart.push({ key, productId: p.id, name: p.name, unit: price, qty, optionIds: ids, optionNames: names, notes });
  renderCart();
}
function total() { return cart.reduce((sum, line) => sum + line.unit * line.qty, 0); }
function renderCart() {
  $('cart').innerHTML = cart.length ? cart.map((line, index) => {
    const product = CATALOG.products.find(p => Number(p.id) === Number(line.productId));
    return `<div class="pos-cart-item flex gap-3 py-3"><div class="h-[58px] w-[58px] shrink-0 overflow-hidden rounded-xl bg-[#f5ece3]">${product?.image ? `<img src="${esc(product.image)}" alt="${esc(line.name)}" class="h-full w-full object-cover">` : '<div class="flex h-full items-center justify-center text-xl">☕</div>'}</div><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">${esc(line.name)}</p><p class="mt-1 text-sm font-bold text-caramel">${peso(line.unit)}</p>${line.optionNames.length ? `<p class="mt-1 text-xs text-espresso/60">${esc(line.optionNames.join(', '))}</p>` : ''}${line.notes ? `<p class="mt-1 text-xs text-espresso/55">Note: ${esc(line.notes)}</p>` : ''}</div><div class="flex shrink-0 flex-col items-end justify-between"><button type="button" data-remove="${index}" class="text-red-500" aria-label="Remove ${esc(line.name)}">▤</button><div class="pos-qty flex items-center gap-1.5"><button type="button" data-i="${index}" data-d="-1" aria-label="Decrease quantity">−</button><span class="min-w-[16px] text-center text-sm">${line.qty}</span><button type="button" data-i="${index}" data-d="1" aria-label="Increase quantity">+</button></div></div></div>`;
  }).join('') : '<p class="py-3 text-sm text-espresso/50">No items yet. Select from menu.</p>';
  $('cart').querySelectorAll('[data-i]').forEach(button => button.addEventListener('click', () => { const i = Number(button.dataset.i); cart[i].qty += Number(button.dataset.d); if (cart[i].qty <= 0) cart.splice(i, 1); renderCart(); }));
  $('cart').querySelectorAll('[data-remove]').forEach(button => button.addEventListener('click', () => { cart.splice(Number(button.dataset.remove), 1); renderCart(); }));
  $('subtotal').textContent = peso(total()); $('total').textContent = peso(total()); renderQuick(); updatePay();
}
function renderQuick() {
  const amount = total();
  const options = [...new Set([Math.ceil(amount), 100, 200, 500, 1000].filter(value => value >= amount && amount > 0))].sort((a,b) => a-b).slice(0,5);
  $('quick').innerHTML = options.map(value => `<button type="button" data-v="${value}" class="rounded-lg border border-[#e8ddd2] px-2.5 py-1 text-xs text-[#75401f] hover:bg-[#fbf3ea]">${value === Math.ceil(amount) ? 'Exact ' : ''}${peso(value)}</button>`).join('');
  $('quick').querySelectorAll('[data-v]').forEach(button => button.addEventListener('click', () => { $('cash').value = button.dataset.v; updatePay(); }));
}
function updatePay() {
  const amount = total(), cash = Number.parseFloat($('cash').value || '0');
  $('change').textContent = peso(Math.max(0, cash - amount)); $('pay').disabled = !(cart.length && cash + 0.001 >= amount);
}
$('cash').addEventListener('input', updatePay);
$('clear').onclick = () => { if (!cart.length || window.confirm('Clear all items from this order?')) { cart = []; $('cash').value = ''; $('cust').value = ''; $('msg').textContent = ''; renderCart(); } };
$('pay').onclick = async () => {
  $('msg').textContent = ''; $('pay').disabled = true;
  try {
    const response = await fetch(PAY_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ cash: Number.parseFloat($('cash').value), customer_name: $('cust').value.trim(), items: cart.map(line => ({ product_id: line.productId, quantity: line.qty, option_ids: line.optionIds, notes: line.notes || null })) }) });
    const json = await response.json();
    if (!response.ok) { const first = json.errors ? Object.values(json.errors)[0] : null; throw new Error((Array.isArray(first) ? first[0] : first) || json.message || 'Could not process this order.'); }
    const data = json.data; $('done').classList.remove('hidden'); $('done').innerHTML = `✓ <b>${esc(data.order_number)}</b> · Total ${peso(data.total)} · Change <b>${peso(data.change)}</b><br><a href="${esc(data.receipt_url)}" target="_blank" rel="noopener" class="mt-2 inline-block rounded-lg bg-espresso px-3 py-1.5 text-cream">▤ Print receipt</a>`;
    cart = []; $('cash').value = ''; $('cust').value = ''; renderCart();
  } catch (error) { $('msg').textContent = error.message; updatePay(); }
};
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });
renderCats(); renderGrid(); renderCart();
</script>
@endpush
