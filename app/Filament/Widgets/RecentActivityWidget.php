<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Recently published/updated articles — a lightweight stand-in for a full
 * admin activity log, which is Phase 9 scope (activity_logs table). Swap
 * this widget's query for that table once it lands.
 */
class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(Post::query()->latest('updated_at')->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('title')->limit(50),
                Tables\Columns\TextColumn::make('author.name'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PostStatus $state) => $state->label())
                    ->color(fn (PostStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Last updated'),
            ])
            ->paginated(false);
    }
}
