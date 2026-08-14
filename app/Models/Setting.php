<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasUuids;

    protected $fillable = ['group', 'key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Statement-bodied, not `fn ($setting) => Cache::forget(...)`:
        // forget() returns a boolean (false when the key was never
        // cached), and an arrow function leaks that value through as this
        // listener's response — Illuminate's event dispatcher halts every
        // *other* listener registered for the same event the instant one
        // returns exactly `false`. Silently harmless today only because
        // nothing else currently listens to Setting's saved/deleted, but
        // it would silently break the next observer added here.
        static::saved(function (self $setting) {
            Cache::forget("settings.{$setting->group}.{$setting->key}");
        });
        static::deleted(function (self $setting) {
            Cache::forget("settings.{$setting->group}.{$setting->key}");
        });
    }
}
