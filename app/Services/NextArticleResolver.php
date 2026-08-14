<?php

namespace App\Services;

use App\Enums\NextArticleMode;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves what to open after a reader completes an article (§12).
 * Cascades manual -> same-category -> automatic fallback; every branch is
 * scoped through Post::published() so a draft, unpublished, or soft-deleted
 * article can never be selected.
 */
class NextArticleResolver
{
    public function resolve(Post $post): ?Post
    {
        return $this->manual($post) ?? $this->sameCategory($post) ?? $this->auto($post);
    }

    private function manual(Post $post): ?Post
    {
        if ($post->next_article_mode !== NextArticleMode::Manual || ! $post->next_article_id) {
            return null;
        }

        return Post::published()->whereKey($post->next_article_id)->first();
    }

    private function sameCategory(Post $post): ?Post
    {
        if (! $post->category_id) {
            return null;
        }

        return $this->nextInQuery(
            Post::published()->where('category_id', $post->category_id),
            $post
        );
    }

    private function auto(Post $post): ?Post
    {
        return $this->nextInQuery(Post::published(), $post);
    }

    /**
     * "Next" means the article immediately following this one in the
     * public reverse-chronological feed; falls back to the most recent
     * other article in the same query if this one is already the oldest.
     */
    private function nextInQuery(Builder $query, Post $post): ?Post
    {
        $olderClone = (clone $query)
            ->whereKeyNot($post->id)
            ->where('published_at', '<', $post->published_at)
            ->orderByDesc('published_at')
            ->first();

        return $olderClone ?? (clone $query)
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->first();
    }
}
