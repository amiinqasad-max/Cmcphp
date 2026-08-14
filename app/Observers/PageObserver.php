<?php

namespace App\Observers;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Services\ActivityLogger;

class PageObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Page $page): void
    {
        $this->logger->log('page.created', $page, ['title' => $page->title]);
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
    }
}
