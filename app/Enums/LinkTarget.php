<?php

namespace App\Enums;

enum LinkTarget: string
{
    case SameWindow = '_self';
    case NewWindow = '_blank';

    public function label(): string
    {
        return match ($this) {
            self::SameWindow => 'Same window',
            self::NewWindow => 'New window',
        };
    }
}
