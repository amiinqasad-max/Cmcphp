/**
 * Client-side event buffer for engagement tracking (§7/§8/§45).
 *
 * - Never sends one request per event: milestone events are queued and
 *   flushed in batches.
 * - Survives refresh/tab-close/network blips: the queue is mirrored to
 *   localStorage and replayed on the next flush.
 * - Idempotent: every event carries a client-generated UUID, deduped
 *   server-side on `event_uuid`, so a retried flush can never double-count.
 */

const STORAGE_KEY = 'cmcphp_pending_events';
const ENDPOINT = '/api/track/events';
const FLUSH_INTERVAL_MS = 5000;
const MAX_BATCH_SIZE = 20;

function uuid() {
    if (window.crypto && window.crypto.randomUUID) {
        return window.crypto.randomUUID();
    }
    // Fallback for older browsers.
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
        // Storage full/unavailable (private browsing) — degrade to in-memory only.
    }
}

class EventBuffer {
    constructor() {
        this.queue = loadQueue();
        this.flushing = false;

        setInterval(() => this.flush(), FLUSH_INTERVAL_MS);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.flush(true);
            }
        });
        window.addEventListener('pagehide', () => this.flush(true));
    }

    /**
     * @param {string} eventType
     * @param {string} postId
     * @param {object} payload
     * @param {?string} postVideoId
     */
    push(eventType, postId, payload = {}, postVideoId = null) {
        this.queue.push({
            event_type: eventType,
            post_id: postId,
            post_video_id: postVideoId,
            event_uuid: uuid(),
            payload,
        });
        saveQueue(this.queue);
    }

    flush(useBeacon = false) {
        if (this.queue.length === 0 || (this.flushing && !useBeacon)) {
            return;
        }

        const batch = this.queue.slice(0, MAX_BATCH_SIZE);

        if (useBeacon && navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify({ events: batch })], { type: 'application/json' });
            const sent = navigator.sendBeacon(ENDPOINT, blob);

            if (sent) {
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
            .catch(() => {
                // Network failure — leave queued for the next flush (§45).
            })
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

export const eventBuffer = new EventBuffer();
