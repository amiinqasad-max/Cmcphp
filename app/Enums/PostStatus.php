<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Unpublished = 'unpublished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Unpublished => 'Unpublished',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'warning',
            self::Published => 'success',
            self::Unpublished => 'danger',
        };
    }
}
