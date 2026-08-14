<?php

namespace App\Filament\Widgets;

use App\Models\DailyRollup;
use App\Models\Post;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class TopArticlesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top articles (last 30 days)';

    public function table(Table $table): Table
    {
        $since = Carbon::now()->subDays(30)->toDateString();

        $pageviewsSubquery = DailyRollup::query()
            ->selectRaw('COALESCE(SUM(pageviews), 0)')
            ->whereColumn('post_id', 'posts.id')
            ->where('date', '>=', $since);

        $completionsSubquery = DailyRollup::query()
            ->selectRaw('COALESCE(SUM(completions_count), 0)')
            ->whereColumn('post_id', 'posts.id')
            ->where('date', '>=', $since);

        return $table
            ->query(
                Post::query()
                    ->addSelect(['total_pageviews' => $pageviewsSubquery])
                    ->addSelect(['total_completions' => $completionsSubquery])
                    ->withCasts(['total_pageviews' => 'integer', 'total_completions' => 'integer'])
                    ->orderByDesc('total_pageviews')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')->limit(50),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('total_pageviews')->label('Pageviews')->sortable(),
                Tables\Columns\TextColumn::make('total_completions')->label('Completions')->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('total_pageviews', 'desc');
    }
}
