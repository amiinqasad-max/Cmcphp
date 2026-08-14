<?php

namespace App\Enums;

/**
 * Milestone-based engagement events for articles and videos.
 * These are the only event types the tracking ingestion API accepts —
 * never a raw "completed=true" assertion from the client (see ArticleCompletionService).
 */
enum EventType: string
{
    // Article reading events
    case ArticleOpen = 'article_open';
    case Article25 = 'article_25';
    case Article50 = 'article_50';
    case Article75 = 'article_75';
    case Article90 = 'article_90';
    case ArticleBottom = 'article_bottom';
    case ArticleComplete = 'article_complete';

    // Video engagement events
    case VideoPlay = 'video_play';
    case VideoPause = 'video_pause';
    case VideoResume = 'video_resume';
    case VideoProgress = 'video_progress';
    case Video25 = 'video_25';
    case Video50 = 'video_50';
    case Video75 = 'video_75';
    case Video90 = 'video_90';
    case VideoComplete = 'video_complete';
    case VideoEnd = 'video_end';

    public function isVideoEvent(): bool
    {
        return str_starts_with($this->value, 'video_');
    }

    public function isArticleEvent(): bool
    {
        return str_starts_with($this->value, 'article_');
    }
}
