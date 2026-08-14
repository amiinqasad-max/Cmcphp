<?php

namespace App\Services;

use App\Models\ArticleCompletion;
use App\Models\ArticleSession;
use App\Models\Post;
use App\Models\VideoProgress;

/**
 * The single authority on "did this reader complete this article" (§10).
 * Never takes a client's word for it — reads back what EngagementTracking-
 * Service has already persisted (reading progress + per-video completion,
 * each independently server-verified) and only then decides.
 */
class ArticleCompletionService
{
    public function __construct(private readonly NextArticleResolver $nextArticleResolver) {}

    /**
     * Idempotent: if this (post, session) already completed, returns the
     * existing record without re-evaluating. Otherwise checks the current
     * state and creates the record if both conditions are now met.
     */
    public function evaluate(Post $post, string $sessionId, ?string $userId): ?ArticleCompletion
    {
        $existing = ArticleCompletion::query()
            ->where('post_id', $post->id)
            ->where('session_id', $sessionId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $session = ArticleSession::query()
            ->where('post_id', $post->id)
            ->where('session_id', $sessionId)
            ->first();

        if (! $session || ! $session->is_completed) {
            return null;
        }

        $requiredVideoIds = $post->requiredVideos()->pluck('id');
        $requiredCount = $post->completion_required_videos ?? $requiredVideoIds->count();

        $completedCount = $requiredVideoIds->isEmpty() ? 0 : VideoProgress::query()
            ->where('session_id', $sessionId)
            ->whereIn('post_video_id', $requiredVideoIds)
            ->where('is_completed', true)
            ->count();

        if ($completedCount < $requiredCount) {
            return null;
        }

        $nextPost = $this->nextArticleResolver->resolve($post);

        return ArticleCompletion::create([
            'post_id' => $post->id,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'reading_progress_percent' => $session->progress_percent,
            'videos_completed_count' => $completedCount,
            'videos_required_count' => $requiredCount,
            'completed_at' => now(),
            'next_post_id' => $nextPost?->id,
        ]);
    }
}
