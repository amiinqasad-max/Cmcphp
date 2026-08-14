<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\ActivityLogger;

class PostObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Post $post): void
    {
        $this->logger->log('post.created', $post, ['title' => $post->title]);
    }

    public function updated(Post $post): void
    {
        if ($post->wasChanged('status') && $post->status === PostStatus::Published) {
            $this->logger->log('post.published', $post, ['title' => $post->title]);

            return;
        }

        if ($post->wasChanged()) {
            $this->logger->log('post.updated', $post, [
                'title' => $post->title,
                'changed' => array_keys($post->getChanges()),
            ]);
        }
    }

    public function deleted(Post $post): void
    {
        $this->logger->log('post.deleted', $post, ['title' => $post->title]);
    }
}
