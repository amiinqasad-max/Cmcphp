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
        static::saved(fn (self $setting) => Cache::forget("settings.{$setting->group}.{$setting->key}"));
        static::deleted(fn (self $setting) => Cache::forget("settings.{$setting->group}.{$setting->key}"));
    }
}
