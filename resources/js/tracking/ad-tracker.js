/**
 * Internal ad analytics only (§26) — explicitly not official Google AdSense
 * reporting. Attaches to every `[data-ad-tracker]` slot, requests the ad,
 * and observes AdSense's own `data-ad-status` attribute (which it sets to
 * "filled"/"unfilled" once it has processed the slot) to distinguish a
 * request from an actual render.
 */
import { adEventBuffer } from './ad-event-buffer';

function pushAdEvent(eventType, el) {
    adEventBuffer.push(eventType, el.dataset.adSlotId, el.dataset.adPlacementId || null, el.dataset.postId || null);
}

function observeViewability(el, ins) {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                    pushAdEvent('ad_viewable', el);
                    observer.disconnect();
                }
            });
        },
        { threshold: [0.5] }
    );

    observer.observe(ins);
}

function attach(el) {
    const ins = el.querySelector('ins.adsbygoogle');
    if (!ins) {
        return;
    }

    pushAdEvent('ad_requested', el);

    try {
        (window.adsbygoogle = window.adsbygoogle || []).push({});
    } catch {
        // AdSense script not loaded (e.g. blocked by an ad blocker) — the
        // request event above still gives us a denominator for render rate.
        return;
    }

    let loadedFired = false;
    const observer = new MutationObserver(() => {
        const status = ins.getAttribute('data-ad-status');
        if (!status) {
            return;
        }

        if (!loadedFired) {
            loadedFired = true;
            pushAdEvent('ad_loaded', el);
        }

        if (status === 'filled') {
            pushAdEvent('ad_rendered', el);
            observeViewability(el, ins);
            observer.disconnect();
        }
    });

    observer.observe(ins, { attributes: true, attributeFilter: ['data-ad-status'] });
}

export function initAdTracker() {
    document.querySelectorAll('[data-ad-tracker]').forEach(attach);
}
