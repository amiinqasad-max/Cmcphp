<?php

namespace App\Observers;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Services\ActivityLogger;
use App\Services\PublicContentCache;

class PageObserver
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly PublicContentCache $cache,
    ) {}

    public function created(Page $page): void
    {
        $this->logger->log('page.created', $page, ['title' => $page->title]);
    }

    /** Fires on both create and update — the one place the pages cache gets invalidated. */
    public function saved(Page $page): void
    {
        $this->cache->flushPages();
    }

    public function updated(Page $page): void
    {
        if ($page->wasChanged('status') && $page->status === PageStatus::Published) {
            $this->logger->log('page.published', $page, ['title' => $page->title]);

            return;
        }

        if ($page->wasChanged()) {
            $this->logger->log('page.updated', $page, [
                'title' => $page->title,
                'changed' => array_keys($page->getChanges()),
            ]);
        }
    }

    public function deleted(Page $page): void
    {
        $this->logger->log('page.deleted', $page, ['title' => $page->title]);
        $this->cache->flushPages();
    }
}
