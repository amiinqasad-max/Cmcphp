<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\DailyRollup;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class TopCategoriesWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top categories (last 30 days)';

    public function table(Table $table): Table
    {
        $since = Carbon::now()->subDays(30)->toDateString();

        $pageviewsSubquery = DailyRollup::query()
            ->join('posts', 'posts.id', '=', 'daily_rollups.post_id')
            ->selectRaw('COALESCE(SUM(daily_rollups.pageviews), 0)')
            ->whereColumn('posts.category_id', 'categories.id')
            ->where('daily_rollups.date', '>=', $since);

        return $table
            ->query(
                Category::query()
                    ->addSelect(['total_pageviews' => $pageviewsSubquery])
                    ->withCasts(['total_pageviews' => 'integer'])
                    ->withCount('posts')
                    ->orderByDesc('total_pageviews')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('posts_count')->label('Articles'),
                Tables\Columns\TextColumn::make('total_pageviews')->label('Pageviews (30d)')->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('total_pageviews', 'desc');
    }
}
