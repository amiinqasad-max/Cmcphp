<?php

namespace App\Http\Controllers\Public;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCommentRequest;
use App\Models\Comment;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, SettingsService $settings): RedirectResponse
    {
        abort_unless($settings->commentsEnabled(), 403, 'Comments are currently disabled.');

        $validated = $request->validated();

        Comment::create([
            'post_id' => $validated['post_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $request->user()?->id,
            'author_name' => $request->user()?->name ?? $validated['author_name'] ?? null,
            'author_email' => $request->user()?->email ?? $validated['author_email'] ?? null,
            'body' => $validated['body'],
            'status' => $settings->commentsRequireApproval() ? CommentStatus::Pending : CommentStatus::Approved,
        ]);

        return back()->with('status', $settings->commentsRequireApproval()
            ? 'Your comment was submitted and is awaiting approval.'
            : 'Your comment was posted.');
    }
}
