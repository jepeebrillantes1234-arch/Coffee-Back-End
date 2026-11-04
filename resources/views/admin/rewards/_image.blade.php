@php($imageUrl = $reward->image_url)
<div class="{{ $size }} relative shrink-0 overflow-hidden rounded-lg bg-cream" data-reward-image>
    @if($imageUrl)
        <img class="h-full w-full object-cover" src="{{ $imageUrl }}" alt="{{ $reward->name }}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.classList.remove('hidden')">
    @endif
    <div class="{{ $imageUrl ? 'hidden' : '' }} absolute inset-0 flex items-center justify-center text-espresso/45" aria-label="Reward image unavailable" role="img">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V8.5A2.5 2.5 0 0 1 6.5 6h11A2.5 2.5 0 0 1 20 8.5V19M4 16l4-4 3 3 3-4 6 6M8 3v2m8-2v2M8 21h8" />
        </svg>
    </div>
</div>
