<?php

namespace App\Filament\Resources;

use App\Enums\CommentStatus;
use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use App\Services\ActivityLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', CommentStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('post')
                ->content(fn (?Comment $record) => $record?->post?->title),
            Forms\Components\Placeholder::make('author')
                ->content(fn (?Comment $record) => $record?->authorDisplayName()),
            Forms\Components\Textarea::make('body')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options(collect(CommentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('post.title')->limit(30),
                Tables\Columns\TextColumn::make('body')
                    ->formatStateUsing(fn (Comment $record) => $record->authorDisplayName())
                    ->label('Author'),
                Tables\Columns\TextColumn::make('body')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (CommentStatus $state) => $state->label())
                    ->color(fn (CommentStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CommentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Comment $record) => $record->status !== CommentStatus::Approved)
                    ->action(function (Comment $record) {
                        $record->update(['status' => CommentStatus::Approved]);
                        app(ActivityLogger::class)->log('comment.approved', $record);
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Comment $record) => $record->status !== CommentStatus::Rejected)
                    ->action(function (Comment $record) {
                        $record->update(['status' => CommentStatus::Rejected]);
                        app(ActivityLogger::class)->log('comment.rejected', $record);
                    }),
                Tables\Actions\Action::make('spam')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('gray')
                    ->visible(fn (Comment $record) => $record->status !== CommentStatus::Spam)
                    ->action(function (Comment $record) {
                        $record->update(['status' => CommentStatus::Spam]);
                        app(ActivityLogger::class)->log('comment.marked_spam', $record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['status' => CommentStatus::Approved])),
                    Tables\Actions\BulkAction::make('markSpam')
                        ->label('Mark as spam')
                        ->icon('heroicon-o-shield-exclamation')
                        ->action(fn ($records) => $records->each->update(['status' => CommentStatus::Spam])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
