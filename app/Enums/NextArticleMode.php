<?php

namespace App\Enums;

enum NextArticleMode: string
{
    case Manual = 'manual';
    case SameCategory = 'same_category';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manually selected article',
            self::SameCategory => 'Same-category article',
            self::Auto => 'Automatic (next published article)',
        };
    }
}
