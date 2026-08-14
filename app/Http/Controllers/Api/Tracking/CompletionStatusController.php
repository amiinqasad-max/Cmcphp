<?php

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Models\ArticleCompletion;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight polling endpoint the article page uses to learn when the
 * server has recorded completion (§44). Completion itself is computed
 * asynchronously by the queued tracking pipeline (Phase 5/6), so the
 * client can't know it happened just from the ingestion response — it
 * polls this instead, which only ever reflects what's actually persisted.
 */
class CompletionStatusController extends Controller
{
    public function show(Request $request, SettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => ['required', 'uuid'],
        ]);

        $sessionId = $request->attributes->get('anonymous_session_id');

        $completion = ArticleCompletion::query()
            ->with('nextPost')
            ->where('post_id', $validated['post_id'])
            ->where('session_id', $sessionId)
            ->first();

        if (! $completion) {
            return response()->json(['completed' => false]);
        }

        return response()->json([
            'completed' => true,
            'completed_at' => $completion->completed_at->toIso8601String(),
            'next_article' => $completion->nextPost ? [
                'title' => $completion->nextPost->title,
                'url' => route('articles.show', $completion->nextPost),
            ] : null,
            'auto_next_enabled' => $settings->autoNextEnabled(),
            'redirect_delay_ms' => (int) round($settings->autoNextDelaySeconds() * 1000),
        ]);
    }
}
