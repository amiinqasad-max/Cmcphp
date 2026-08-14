<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Flips scheduled posts to published once their publish time arrives (§4).
 * Uses Eloquent (not a mass UPDATE) so PostObserver still fires and logs
 * "post.published" + flushes the public listing cache for each one.
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled posts whose publish time has arrived.';

    public function handle(): int
    {
        $due = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->update(['status' => PostStatus::Published]);
        }

        $this->info("Published {$due->count()} scheduled post(s).");

        return self::SUCCESS;
    }
}
