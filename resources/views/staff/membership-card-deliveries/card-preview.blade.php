@php
    $cardMembership = $d->membership;
    $cardReady = $cardMembership
        && $cardMembership->isActive()
        && $cardMembership->payments->contains(fn ($payment) => $payment->status === 'paid');
    $cardModalId = 'membership-card-delivery-preview-' . $d->id;
@endphp

@if ($cardReady)
    <button type="button" class="rounded-lg border border-stone-300 px-4 py-2 font-semibold text-stone-800" data-mcd-card-open="{{ $cardModalId }}">View Card</button>
    <dialog id="{{ $cardModalId }}" class="mcd-card-dialog">
        <div class="mcd-card-dialog-head">
            <div><h2>Membership Card</h2><p>{{ $cardMembership->user?->name ?? $d->customer_name_snapshot }} · {{ $cardMembership->member_id }}</p></div>
            <form method="dialog"><button type="submit" class="mcd-card-close" aria-label="Close card preview">&times;</button></form>
        </div>
        <div class="mcd-card-grid">
            <section><h3>Front</h3>
                <div class="mcd-card-artboard">
                    <img class="mcd-card-artwork" src="{{ $frontCardUrl }}" alt="Membership card front artwork">
                    <div class="mcd-card-status">{{ $cardMembership->isActive() ? 'ACTIVE MEMBER' : strtoupper($cardMembership->status) }}</div>
                    <div class="mcd-card-info">
                        <div class="mcd-card-name">{{ $cardMembership->user?->name ?? $d->customer_name_snapshot }}</div>
                        <div class="mcd-card-id">MEMBER ID&nbsp; {{ $cardMembership->member_id }}</div>
                        <div class="mcd-card-points">{{ number_format($cardMembership->points_balance) }} POINTS</div>
                        <div class="mcd-card-since">MEMBER SINCE&nbsp; {{ ($cardMembership->activated_at ?? $cardMembership->created_at)?->format('m/y') ?? '—' }}</div>
                    </div>
                    <div class="mcd-card-qr" data-membership-qr="{{ $cardMembership->member_id }}" role="img" aria-label="Membership QR code"></div>
                </div>
            </section>
            <section><h3>Back</h3><div class="mcd-card-artboard"><img class="mcd-card-artwork" src="{{ $backCardUrl }}" alt="Membership card back artwork"></div></section>
        </div>
    </dialog>
@else
    <span class="text-sm text-gray-500" title="The membership must be active and have a verified paid payment.">Card unavailable</span>
@endif

@once
    <style>
        .mcd-card-dialog{width:min(1100px,calc(100vw - 24px));max-width:none;max-height:calc(100dvh - 24px);padding:clamp(14px,3vw,24px);border:0;border-radius:18px;color:#2e241f;background:#fffdfb;box-shadow:0 20px 70px #0005}
        .mcd-card-dialog::backdrop{background:#160d0bcc}
        .mcd-card-dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px}.mcd-card-dialog-head h2{margin:0;font-size:20px;font-weight:800}.mcd-card-dialog-head p{margin:5px 0 0;color:#76685e;font-size:13px;overflow-wrap:anywhere}
        .mcd-card-close{width:38px;height:38px;border:0;border-radius:50%;background:#f2ece6;font-size:26px;line-height:1;cursor:pointer}
        .mcd-card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}.mcd-card-grid h3{margin:0 0 9px;font-size:14px;font-weight:700}
        .mcd-card-artboard{container-type:inline-size;position:relative;width:100%;aspect-ratio:1832/858;overflow:hidden;border-radius:14px;background:#fff;box-shadow:0 5px 18px #27180e22}
        .mcd-card-artwork{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
        .mcd-card-status{position:absolute;left:65%;top:27.5%;width:30%;height:13%;display:flex;align-items:center;justify-content:center;box-sizing:border-box;padding:0 1.2%;border:1.2px solid #fff;border-radius:999px;background:#111;color:#fff;font-size:clamp(7.92px,2.62cqw,16.5px);font-weight:900;letter-spacing:.08em;white-space:nowrap}
        .mcd-card-info{position:absolute;left:5.5%;top:51%;width:47%;height:45%;display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-start;color:#fff;font-weight:700;line-height:1.25}
        .mcd-card-name{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:clamp(12.24px,4.05cqw,25.5px);font-weight:900;text-shadow:0 1px 4px #0008}
        .mcd-card-id{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:clamp(7.2px,2.38cqw,15px)}.mcd-card-points{font-size:clamp(9.36px,3.1cqw,19.5px);font-weight:900}.mcd-card-since{white-space:nowrap;font-size:clamp(6.48px,2.14cqw,13.5px)}
        .mcd-card-qr{position:absolute;left:55%;top:48.5%;width:18.5%;height:42.5%;padding:.9%;box-sizing:border-box;border-radius:8px;background:#fff}.mcd-card-qr canvas{display:block;width:100%;height:100%;image-rendering:pixelated}
        @media(max-width:700px){.mcd-card-grid{grid-template-columns:1fr;gap:16px}.mcd-card-dialog{overflow:auto}.mcd-card-dialog-head h2{font-size:18px}}
    </style>
    <script>
        (() => {
            if (window.membershipCardPreviewBound) return;
            window.membershipCardPreviewBound = true;
            document.addEventListener('click', event => {
                const openButton = event.target.closest('[data-mcd-card-open]');
                if (openButton) document.getElementById(openButton.dataset.mcdCardOpen)?.showModal();
            });
            document.addEventListener('click', event => {
                document.querySelectorAll('.mcd-card-dialog[open]').forEach(dialog => {
                    if (event.target === dialog) dialog.close();
                });
            });
        })();
    </script>
@endonce
