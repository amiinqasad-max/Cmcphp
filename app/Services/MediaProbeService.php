<?php

namespace App\Services;

use getID3;

/**
 * Extracts technical metadata (image dimensions, video duration) from an
 * uploaded file's real local path — used both by the Media Library upload
 * form (Phase 2) and the three-video duration-detection job (Phase 4).
 *
 * Deliberately pure-PHP (getID3) rather than shelling out to ffprobe: it
 * keeps duration detection working on any VPS regardless of whether ffmpeg
 * is installed, per docs/ARCHITECTURE.md's "no hard-coded infra assumptions"
 * principle.
 */
class MediaProbeService
{
    /**
     * @return array{width: ?int, height: ?int}
     */
    public function probeImageDimensions(string $realPath): array
    {
        $info = @getimagesize($realPath);

        return [
            'width' => $info[0] ?? null,
            'height' => $info[1] ?? null,
        ];
    }

    public function probeVideoDurationSeconds(string $realPath): ?int
    {
        if (! class_exists(getID3::class)) {
            return null;
        }

        $getID3 = new getID3;
        $info = $getID3->analyze($realPath);

        $seconds = $info['playtime_seconds'] ?? null;

        return $seconds !== null ? (int) round($seconds) : null;
    }
}
