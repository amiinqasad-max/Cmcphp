<?php

namespace App\Enums;

enum AdPlacementType: string
{
    case Top = 'top';
    case AfterParagraph = 'after_paragraph';
    case BeforeVideo = 'before_video';
    case AfterVideo = 'after_video';
    case Middle = 'middle';
    case BeforeConclusion = 'before_conclusion';
    case Bottom = 'bottom';

    public function label(): string
    {
        return match ($this) {
            self::Top => 'Article Top',
            self::AfterParagraph => 'After Paragraph',
            self::BeforeVideo => 'Before Video',
            self::AfterVideo => 'After Video',
            self::Middle => 'Middle',
            self::BeforeConclusion => 'Before Conclusion',
            self::Bottom => 'Article Bottom',
        };
    }
}
