import './bootstrap';

import Alpine from 'alpinejs';
import { initAdTracker } from './tracking/ad-tracker';
import { initAutoNext } from './tracking/auto-next';
import { initReadingTracker } from './tracking/reading-tracker';
import { initVideoTracker } from './tracking/video-tracker';

window.Alpine = Alpine;

Alpine.start();

// All four no-op immediately if their target elements aren't present, so
// this is safe to run on every page without a public/admin bundle split.
document.addEventListener('DOMContentLoaded', () => {
    initReadingTracker();
    initVideoTracker();
    initAutoNext();
    initAdTracker();
});
