/**
 * Same batching/retry/idempotency approach as event-buffer.js, but for ad
 * events — a distinct endpoint and payload shape (ad_slot_id/ad_placement_id
 * rather than post_video_id), since these are Internal Ad Analytics (§26),
 * not reading/video engagement events.
 */
const STORAGE_KEY = 'cmcphp_pending_ad_events';
const ENDPOINT = '/api/track/ad-events';
const FLUSH_INTERVAL_MS = 3000;

function uuid() {
    if (window.crypto && window.crypto.randomUUID) {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

function loadQueue() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function saveQueue(queue) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
    } catch {
        // ignore
    }
}

class AdEventBuffer {
    constructor() {
        this.queue = loadQueue();
        this.flushing = false;
        setInterval(() => this.flush(), FLUSH_INTERVAL_MS);
        window.addEventListener('pagehide', () => this.flush(true));
    }

    push(eventType, adSlotId, adPlacementId, postId) {
        this.queue.push({
            event_type: eventType,
            ad_slot_id: adSlotId,
            ad_placement_id: adPlacementId,
            post_id: postId,
            event_uuid: uuid(),
        });
        saveQueue(this.queue);
    }

    flush(useBeacon = false) {
        if (this.queue.length === 0 || (this.flushing && !useBeacon)) {
            return;
        }

        const batch = this.queue.slice(0, 20);

        if (useBeacon && navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify({ events: batch })], { type: 'application/json' });
            if (navigator.sendBeacon(ENDPOINT, blob)) {
                this.removeSent(batch);
            }
            return;
        }

        this.flushing = true;
        fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ events: batch }),
        })
            .then((response) => {
                if (response.ok || response.status === 202) {
                    this.removeSent(batch);
                }
            })
            .catch(() => {})
            .finally(() => {
                this.flushing = false;
            });
    }

    removeSent(sentBatch) {
        const sentIds = new Set(sentBatch.map((e) => e.event_uuid));
        this.queue = this.queue.filter((e) => !sentIds.has(e.event_uuid));
        saveQueue(this.queue);
    }
}

export const adEventBuffer = new AdEventBuffer();
