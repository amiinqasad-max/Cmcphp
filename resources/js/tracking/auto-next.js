/**
 * Polls for server-confirmed article completion and drives the "✓ Article
 * completed → opening next article…" UX (§44). Completion itself is
 * computed asynchronously by the queued tracking pipeline, so this can't
 * just react to a local event — it asks the server.
 */
const POLL_INTERVAL_MS = 4000;
const DISABLE_STORAGE_KEY = 'cmcphp_auto_next_disabled';

function isAutoNextDisabledByUser() {
    return localStorage.getItem(DISABLE_STORAGE_KEY) === '1';
}

function renderBanner(banner, { nextArticle, autoNextEnabled, redirectDelayMs }) {
    const willAutoNext = autoNextEnabled && nextArticle && !isAutoNextDisabledByUser();

    banner.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-2 font-medium text-emerald-700">
                <span aria-hidden="true">✓</span> Article completed
            </p>
            ${nextArticle ? `
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" id="cmcphp-auto-next-toggle" ${willAutoNext ? 'checked' : ''}>
                    Auto-play next article
                </label>
            ` : ''}
        </div>
        ${nextArticle ? `<p id="cmcphp-next-status" class="mt-2 text-sm text-gray-600"></p>` : ''}
    `;
    banner.classList.remove('hidden');

    const status = banner.querySelector('#cmcphp-next-status');
    const toggle = banner.querySelector('#cmcphp-auto-next-toggle');

    if (toggle) {
        toggle.addEventListener('change', () => {
            localStorage.setItem(DISABLE_STORAGE_KEY, toggle.checked ? '0' : '1');
            if (status) {
                status.textContent = toggle.checked ? `Opening "${nextArticle.title}" shortly…` : '';
            }
        });
    }

    if (willAutoNext && status) {
        status.textContent = `Opening "${nextArticle.title}" shortly…`;
        setTimeout(() => {
            if (!isAutoNextDisabledByUser()) {
                window.location.href = nextArticle.url;
            }
        }, redirectDelayMs);
    }
}

export function initAutoNext() {
    const root = document.querySelector('[data-track-post-id]');
    const banner = document.getElementById('cmcphp-completion-banner');

    if (!root || !banner) {
        return;
    }

    const postId = root.dataset.trackPostId;
    let stopped = false;

    const poll = () => {
        if (stopped) {
            return;
        }

        fetch(`/api/track/completion?post_id=${encodeURIComponent(postId)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) {
                    return;
                }

                if (data.completed) {
                    stopped = true;
                    renderBanner(banner, data);
                    return;
                }

                setTimeout(poll, POLL_INTERVAL_MS);
            })
            .catch(() => setTimeout(poll, POLL_INTERVAL_MS));
    };

    setTimeout(poll, POLL_INTERVAL_MS);
}
