/**
 * Video engagement tracking (§7). Attaches to every `[data-video-tracker]`
 * element rendered by the video-player Blade component. Milestone-only:
 * percent thresholds fire once each, and the coarse `video_progress` signal
 * is throttled rather than fired on every `timeupdate` tick.
 */
import { eventBuffer } from './event-buffer';

const MILESTONES = [25, 50, 75, 90];
const PROGRESS_THROTTLE_MS = 5000;

function attach(container) {
    const video = container.querySelector('video');
    if (!video) {
        return;
    }

    const postId = container.dataset.postId;
    const postVideoId = container.dataset.postVideoId;
    const fired = new Set();
    let hasPlayedBefore = false;
    let lastProgressEmitAt = 0;

    const percentOf = () => {
        if (!video.duration || Number.isNaN(video.duration)) {
            return 0;
        }
        return Math.min(100, Math.round((video.currentTime / video.duration) * 100));
    };

    const emitOnce = (type, extra = {}) => {
        if (fired.has(type)) {
            return;
        }
        fired.add(type);
        eventBuffer.push(type, postId, { percent: percentOf(), watched_seconds: video.currentTime, ...extra }, postVideoId);
    };

    video.addEventListener('play', () => {
        if (hasPlayedBefore) {
            // Each pause/resume cycle is its own signal (unlike the
            // once-per-session milestones), matching play_count/pause_count
            // tracking server-side.
            eventBuffer.push('video_resume', postId, { percent: percentOf(), watched_seconds: video.currentTime }, postVideoId);
        } else {
            emitOnce('video_play');
            hasPlayedBefore = true;
        }
    });

    video.addEventListener('pause', () => {
        if (video.ended) {
            return;
        }
        eventBuffer.push('video_pause', postId, { percent: percentOf(), watched_seconds: video.currentTime }, postVideoId);
    });

    video.addEventListener('timeupdate', () => {
        const percent = percentOf();

        MILESTONES.forEach((milestone) => {
            if (percent >= milestone) {
                emitOnce(`video_${milestone}`);
            }
        });

        const now = Date.now();
        if (now - lastProgressEmitAt >= PROGRESS_THROTTLE_MS) {
            lastProgressEmitAt = now;
            eventBuffer.push('video_progress', postId, { percent, watched_seconds: video.currentTime }, postVideoId);
        }
    });

    video.addEventListener('ended', () => {
        emitOnce('video_complete');
        eventBuffer.push('video_end', postId, { percent: 100, watched_seconds: video.currentTime }, postVideoId);
    });
}

export function initVideoTracker() {
    document.querySelectorAll('[data-video-tracker]').forEach(attach);
}
