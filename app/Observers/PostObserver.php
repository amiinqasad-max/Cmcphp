<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\ActivityLogger;
use App\Services\PublicContentCache;
use App\Services\SitemapGenerator;
use Illuminate\Support\Facades\Cache;

class PostObserver
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly PublicContentCache $cache,
    ) {}

    public function created(Post $post): void
    {
        $this->logger->log('post.created', $post, ['title' => $post->title]);
    }

    /** Fires on both create and update — the one place listing caches get invalidated. */
    public function saved(Post $post): void
    {
        $this->cache->flushPosts();
        Cache::tags([SitemapGenerator::CACHE_TAG])->flush();
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
        $this->cache->flushPosts();
        Cache::tags([SitemapGenerator::CACHE_TAG])->flush();
    }
}
