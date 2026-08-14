<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'url',
        'type',
        'mime_type',
        'size_bytes',
        'title',
        'alt_text',
        'caption',
        'description',
        'duration_seconds',
        'thumbnail_media_id',
        'width',
        'height',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(self::class, 'thumbnail_media_id');
    }

    /**
     * Publicly accessible URL for this file, resolved from its disk
     * (works whether the disk is local, S3, or R2).
     */
    public function getUrlAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        return $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }

    public function isImage(): bool
    {
        return $this->type === MediaType::Image;
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::Video;
    }

    public function humanFileSize(): string
    {
        $bytes = $this->size_bytes ?? 0;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}
