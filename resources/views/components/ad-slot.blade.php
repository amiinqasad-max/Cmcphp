@props(['adSlot', 'postId' => null, 'adPlacementId' => null])

{{--
    Ad placements stay visually and structurally distinct from article
    content at all times (§25) — the "Advertisement" label is never hidden,
    and this renders a real AdSense <ins> tag rather than a hard-coded
    per-article snippet (§22).
--}}
<div
    class="ad-slot my-8"
    data-ad-tracker
    data-ad-slot-id="{{ $adSlot->id }}"
    data-ad-placement-id="{{ $adPlacementId }}"
    data-post-id="{{ $postId }}"
>
    <p class="mb-1 text-center text-[11px] uppercase tracking-wide text-gray-400">Advertisement</p>
    <ins
        class="adsbygoogle block"
        style="display:block"
        data-ad-client="{{ $adSlot->ad_client }}"
        data-ad-slot="{{ $adSlot->ad_slot_code }}"
        data-ad-format="{{ $adSlot->format->value }}"
        data-full-width-responsive="{{ $adSlot->is_responsive ? 'true' : 'false' }}"
    ></ins>
</div>
