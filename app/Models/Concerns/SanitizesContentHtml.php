<?php

namespace App\Models\Concerns;

use Mews\Purifier\Facades\Purifier;

/**
 * Server-side HTML sanitization for `content`, applied on every save
 * regardless of author role (§34 XSS defense-in-depth). This is
 * deliberately independent of whatever the RichEditor's own JS restricts
 * client-side — a direct POST to the create/update endpoint, or a lower-
 * trust "author" account, bypasses client-side restrictions entirely.
 */
trait SanitizesContentHtml
{
    protected static function bootSanitizesContentHtml(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('content') && filled($model->content)) {
                $model->content = Purifier::clean($model->content, 'articles');
            }
        });
    }
}
