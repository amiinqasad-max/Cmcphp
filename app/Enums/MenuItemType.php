<?php

namespace App\Enums;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;

enum MenuItemType: string
{
    case Page = 'page';
    case Post = 'post';
    case Category = 'category';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Internal page',
            self::Post => 'Internal post',
            self::Category => 'Category',
            self::Custom => 'Custom URL',
        };
    }

    /** The model class an internal reference of this type points at, or null for Custom. */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::Page => Page::class,
            self::Post => Post::class,
            self::Category => Category::class,
            self::Custom => null,
        };
    }

    public function isInternal(): bool
    {
        return $this !== self::Custom;
    }
}
