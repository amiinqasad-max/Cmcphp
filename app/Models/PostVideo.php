<?php

namespace App\Models;

use App\Enums\VideoStatus;
use App\Jobs\DetectVideoDurationJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostVideo extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'media_id',
        'position',
        'duration_seconds',
        'is_required',
        'completion_threshold',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_seconds' => 'integer',
            'is_required' => 'boolean',
            'completion_threshold' => 'integer',
            'status' => VideoStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Duration is never hard-coded (§6): mirror it from the Media
        // Library entry (already probed on upload — see MediaResource) and,
        // if that's somehow still missing, fall back to an async re-probe.
        static::saving(function (self $video) {
            if ($video->duration_seconds === null && $video->media_id) {
                $video->duration_seconds = Media::find($video->media_id)?->duration_seconds;
            }
        });

        static::saved(function (self $video) {
            if ($video->duration_seconds === null) {
                DetectVideoDurationJob::dispatch($video->id);
            }
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function isActive(): bool
    {
        return $this->status === VideoStatus::Active;
    }

    /** The marker token authors type into the RichEditor to place this video — e.g. [[VIDEO_1]]. */
    public function marker(): string
    {
        return "[[VIDEO_{$this->position}]]";
    }
}
