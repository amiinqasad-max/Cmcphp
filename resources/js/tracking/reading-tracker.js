/**
 * Article reading progress tracking (§8). Milestone-only, scroll-based —
 * fires each threshold at most once per page view, plus a periodic
 * (not per-second) time-spent update while the tab is active.
 */
import { eventBuffer } from './event-buffer';

const MILESTONES = [25, 50, 75, 90];
const BOTTOM_THRESHOLD_PX = 80;

export function initReadingTracker() {
    const root = document.querySelector('[data-track-post-id]');

    if (!root) {
        return;
    }

    const postId = root.dataset.trackPostId;
    const fired = new Set();
    let activeSeconds = 0;
    let lastTickAt = Date.now();
    let bottomReached = false;

    const tick = () => {
        if (document.visibilityState === 'visible') {
            activeSeconds += (Date.now() - lastTickAt) / 1000;
        }
        lastTickAt = Date.now();
    };

    const scrollPercent = () => {
        const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (scrollableHeight <= 0) {
            return 100;
        }
        return Math.min(100, Math.round((window.scrollY / scrollableHeight) * 100));
    };

    const emitMilestone = (type, percent) => {
        if (fired.has(type)) {
            return;
        }
        fired.add(type);
        eventBuffer.push(type, postId, { percent, time_spent_seconds: Math.round(activeSeconds) });
    };

    eventBuffer.push('article_open', postId, { percent: 0 });

    const onScroll = () => {
        const percent = scrollPercent();

        MILESTONES.forEach((milestone) => {
            if (percent >= milestone) {
                emitMilestone(`article_${milestone}`, percent);
            }
        });

        const nearBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - BOTTOM_THRESHOLD_PX;

        if (nearBottom && !bottomReached) {
            bottomReached = true;
            eventBuffer.push('article_bottom', postId, { percent, time_spent_seconds: Math.round(activeSeconds) });
        }

        if (percent >= 90 && !fired.has('article_complete')) {
            emitMilestone('article_complete', percent);
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    setInterval(tick, 1000);

    // Final time-spent snapshot when the reader leaves, riding on an
    // already-fired-or-not milestone type so it stays within the validated
    // event vocabulary — re-pushing article_open is idempotent (dedup is by
    // event_uuid, and the server takes the *max* of progress/time anyway).
    const finalSnapshot = () => {
        eventBuffer.push('article_open', postId, {
            percent: scrollPercent(),
            time_spent_seconds: Math.round(activeSeconds),
        });
    };
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            finalSnapshot();
        }
    });
    window.addEventListener('pagehide', finalSnapshot);

    onScroll();
}
