<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $navigationGroup = 'Users & Access';

    protected static ?int $navigationSort = 10;

    // Read-only: activity entries are written by observers/listeners only.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->default('System'),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('properties')
                    ->formatStateUsing(fn (?array $state) => $state ? json_encode($state) : null)
                    ->limit(60)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => ActivityLog::query()->distinct()->pluck('action', 'action')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
