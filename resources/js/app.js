import './bootstrap';

import Alpine from 'alpinejs';
import { initReadingTracker } from './tracking/reading-tracker';
import { initVideoTracker } from './tracking/video-tracker';

window.Alpine = Alpine;

Alpine.start();

// Both trackers no-op immediately if their target elements aren't present,
// so this is safe to run on every page without a public/admin bundle split.
document.addEventListener('DOMContentLoaded', () => {
    initReadingTracker();
    initVideoTracker();
});
