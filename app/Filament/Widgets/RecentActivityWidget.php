<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query()->with('user')->latest('created_at')->limit(15))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->since()->label('When'),
                Tables\Columns\TextColumn::make('user.name')->label('User')->default('System'),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
            ])
            ->paginated(false);
    }
}
