<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Post;
use App\Services\PostAnalyticsService;
use Filament\Resources\Pages\Page;

class ViewPostAnalytics extends Page
{
    protected static string $resource = PostResource::class;

    protected static string $view = 'filament.resources.post-resource.pages.view-post-analytics';

    public Post $record;

    public array $stats = [];

    public function mount(Post $record): void
    {
        // Filament/Livewire already resolves {record} via the resource's
        // own route-key-aware binding (respecting Post::getRouteKeyName())
        // before mount() runs — calling resolveRecordRouteBinding() again
        // here would double-resolve against Livewire's serialized payload
        // instead of the raw route key and fail.
        $this->record = $record;

        // Custom Filament pages (unlike Edit/View resource pages) don't
        // auto-authorize — piggyback on the "update" ability so analytics
        // visibility follows the same author-owns-their-own-posts rule as
        // editing (PostPolicy::ownsOrElevated).
        abort_unless(auth()->user()?->can('update', $this->record), 403);

        $this->stats = app(PostAnalyticsService::class)->forPost($this->record);
    }

    public function getTitle(): string
    {
        return "Analytics — {$this->record->title}";
    }
}
