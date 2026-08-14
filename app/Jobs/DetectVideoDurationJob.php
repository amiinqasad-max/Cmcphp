<?php

namespace App\Jobs;

use App\Models\PostVideo;
use App\Services\MediaProbeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Fallback duration detection for a post's video when the linked Media
 * record didn't already have `duration_seconds` probed at upload time
 * (see MediaResource, which probes synchronously from the browser upload —
 * this job exists for the rarer case where that failed, e.g. a MIME the
 * client didn't set, or a video attached via direct DB import).
 */
class DetectVideoDurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $postVideoId) {}

    public function handle(MediaProbeService $probe): void
    {
        $postVideo = PostVideo::with('media')->find($this->postVideoId);

        if (! $postVideo || ! $postVideo->media || $postVideo->duration_seconds !== null) {
            return;
        }

        $media = $postVideo->media;
        $tempPath = tempnam(sys_get_temp_dir(), 'cmcphp_video_');

        try {
            $contents = Storage::disk($media->disk)->get($media->path);

            if ($contents === null) {
                return;
            }

            file_put_contents($tempPath, $contents);
            $duration = $probe->probeVideoDurationSeconds($tempPath);

            if ($duration !== null) {
                $postVideo->update(['duration_seconds' => $duration]);
                $media->update(['duration_seconds' => $duration]);
            }
        } finally {
            @unlink($tempPath);
        }
    }
}
