@extends('layouts.staff')
@section('title', 'Orders')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-2xl font-semibold">Order Queue</h2>
        <p class="mt-1 text-sm text-espresso/60">Review and update active customer orders.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span id="orders-live-status" class="inline-flex items-center gap-2 rounded-lg border border-espresso/15 bg-white px-3 py-1.5 text-sm text-espresso/70" role="status" aria-live="polite">
            <span id="orders-live-dot" class="h-2 w-2 rounded-full bg-amber-500"></span>
            <span id="orders-live-label">Connecting</span>
        </span>
        <button id="notifications-btn" type="button" class="rounded-lg border border-espresso/20 bg-white px-3 py-1.5 text-sm">
            Enable notifications
        </button>
    </div>
</div>

<div id="orders-toast" class="mb-4 hidden rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status" aria-live="polite"></div>

<div id="queue" aria-live="polite">
    @include('staff.orders._queue')
</div>

<dialog id="order-view-dialog" class="m-auto max-h-[90vh] w-[min(56rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl border-0 bg-cream p-0 text-espresso shadow-2xl backdrop:bg-black/60">
    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-espresso/10 bg-white px-5 py-4">
        <div>
            <h2 id="order-view-title" class="text-lg font-semibold">Order details</h2>
            <p class="text-sm text-espresso/60">Review the customer's items and order information.</p>
        </div>
        <button id="order-view-close" type="button" class="rounded-lg border border-espresso/15 bg-white px-3 py-2 text-sm font-medium hover:bg-cream" aria-label="Close order details">Close <span class="ml-1 text-espresso/55">(Esc)</span></button>
    </div>
    <div id="order-view-content" class="p-5"></div>
</dialog>
@endsection

@push('scripts')
<script>
(() => {
    const partialUrl = @json(route('staff.orders.partial'));
    const queue = document.getElementById('queue');
    const liveLabel = document.getElementById('orders-live-label');
    const liveDot = document.getElementById('orders-live-dot');
    const toast = document.getElementById('orders-toast');
    const notificationsButton = document.getElementById('notifications-btn');
    const orderDialog = document.getElementById('order-view-dialog');
    const orderDialogTitle = document.getElementById('order-view-title');
    const orderDialogContent = document.getElementById('order-view-content');
    const orderDialogClose = document.getElementById('order-view-close');

    let pendingCount = {{ (int) $pendingCount }};
    let liveConnected = false;
    let isRefreshing = false;
    let echoChannel = null;
    let toastTimer;

    function updateLiveStatus(connected) {
        liveConnected = connected;
        liveLabel.textContent = connected ? 'Live updates connected' : 'Live offline · auto-checking';
        liveDot.classList.toggle('bg-emerald-500', connected);
        liveDot.classList.toggle('bg-amber-500', !connected);
    }

    function showToast(message) {
        toast.textContent = message;
        toast.classList.remove('hidden');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.add('hidden'), 7000);
    }

    function setPendingCount(count) {
        const badge = document.getElementById('nav-pending');
        if (badge) {
            badge.textContent = String(count);
            badge.classList.toggle('hidden', count < 1);
        }
        document.title = (count > 0 ? `(${count}) ` : '') + 'Orders | KJM Coffee Shop';
    }

    function notifyNewOrders(count) {
        const message = `${count} new customer order${count === 1 ? '' : 's'} received.`;
        showToast(message);
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('New customer order', {
                body: count === 1 ? 'A new order is waiting in the staff order queue.' : `${count} orders are waiting in the staff order queue.`,
            });
        }
    }

    // Keep the order detail view outside the periodically refreshed queue so it
    // stays open while live updates replace the cards behind it.
    queue.addEventListener('click', event => {
        const summary = event.target.closest('details > summary');
        if (!summary) return;
        event.preventDefault();
        const card = summary.closest('article');
        const details = summary.nextElementSibling;
        if (!card || !details) return;
        orderDialogTitle.textContent = `Order ${card.querySelector('h2')?.textContent?.trim() || 'details'}`;
        const detailView = details.cloneNode(true);
        detailView.open = true;
        orderDialogContent.replaceChildren(detailView);
        if (!orderDialog.open) orderDialog.showModal();
    });

    orderDialogClose.addEventListener('click', () => orderDialog.close());
    orderDialog.addEventListener('click', event => {
        if (event.target === orderDialog) orderDialog.close();
    });
    orderDialog.addEventListener('cancel', event => {
        event.preventDefault();
        orderDialog.close();
    });

    async function refreshOrders() {
        if (isRefreshing || document.hidden) return;
        const active = document.activeElement;
        if (active && queue.contains(active) && ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName)) return;

        isRefreshing = true;
        try {
            const response = await fetch(partialUrl, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;

            const nextPendingCount = Number(response.headers.get('X-Pending') || 0);
            queue.innerHTML = await response.text();
            if (nextPendingCount > pendingCount) {
                notifyNewOrders(nextPendingCount - pendingCount);
            }
            pendingCount = nextPendingCount;
            setPendingCount(pendingCount);
        } catch (_) {
            updateLiveStatus(false);
        } finally {
            isRefreshing = false;
        }
    }

    function handleLiveUpdate(event) {
        const kind = event?.kind ?? event?.detail?.kind;
        if (!['order', 'delivery', 'payment'].includes(kind)) return;
        refreshOrders();
    }

    function connectEcho() {
        if (!window.Echo || echoChannel) return;
        try {
            echoChannel = window.Echo.private('staff.updates');
            echoChannel.subscribed(() => {
                updateLiveStatus(true);
                refreshOrders();
            });
            echoChannel.error(() => updateLiveStatus(false));
            echoChannel.listen('.live.updated', handleLiveUpdate);
        } catch (_) {
            updateLiveStatus(false);
        }
    }

    notificationsButton.addEventListener('click', async () => {
        if (!('Notification' in window)) {
            showToast('Browser notifications are not supported here.');
            return;
        }
        const permission = await Notification.requestPermission();
        notificationsButton.textContent = permission === 'granted' ? 'Notifications on' : 'Enable notifications';
    });
    if ('Notification' in window && Notification.permission === 'granted') {
        notificationsButton.textContent = 'Notifications on';
    }

    // Support both the shared app bootstrap event and a direct Echo subscription.
    window.addEventListener('live-update', handleLiveUpdate);
    window.addEventListener('live-connection', event => updateLiveStatus(event.detail?.connected === true));
    connectEcho();

    // WebSocket events update the queue immediately. Keep a lightweight
    // reconciliation request as a watchdog in case a broadcast is lost.
    // This updates only the order fragment and never reloads the page.
    window.setInterval(() => {
        connectEcho();
        refreshOrders();
    }, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshOrders();
    });
    setPendingCount(pendingCount);
    refreshOrders();
})();
</script>
@endpush
