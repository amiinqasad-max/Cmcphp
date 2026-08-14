<?php

namespace App\Filament\Resources;

use App\Enums\RedirectStatus;
use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-turn-down-right';

    protected static ?string $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('source_path')
                        ->label('Source path')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/old-article-url')
                        ->helperText('The path visitors are hitting now. A leading slash is added automatically; query strings are ignored.')
                        ->unique(ignoreRecord: true)
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                // §6 "prevent obviously invalid redirects": reserved app
                                // prefixes can never actually be reached via the redirect
                                // fallback (a real route always wins first), so rejecting
                                // them here avoids admins ending up with dead config that
                                // silently never fires.
                                $normalized = Redirect::normalizePath((string) $value);
                                $reserved = ['/admin', '/login', '/register', '/api', '/dashboard', '/profile', '/sitemap.xml', '/robots.txt'];

                                foreach ($reserved as $prefix) {
                                    if ($normalized === $prefix || str_starts_with($normalized, "{$prefix}/")) {
                                        $fail("\"{$normalized}\" is a reserved application path and can never be reached by a redirect.");

                                        return;
                                    }
                                }
                            },
                        ]),
                    Forms\Components\TextInput::make('destination')
                        ->required()
                        ->maxLength(2048)
                        ->placeholder('/new-article-url or https://example.com/...')
                        ->helperText('An internal path (starting with "/") or a full external URL.')
                        ->rules([
                            fn (Get $get, ?Redirect $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                $value = (string) $value;

                                if (! Redirect::isInternalPath($value) && ! filter_var($value, FILTER_VALIDATE_URL)) {
                                    $fail('Enter an internal path starting with "/" or a full, valid URL.');

                                    return;
                                }

                                $source = (string) $get('source_path');

                                if (filled($source) && Redirect::wouldCreateLoop($source, $value, $record?->id)) {
                                    $fail('This would create a redirect loop (it eventually points back to its own source).');
                                }
                            },
                        ]),
                    Forms\Components\Select::make('status_code')
                        ->label('HTTP status')
                        ->options(RedirectStatus::options())
                        ->default(RedirectStatus::Permanent->value)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive redirects are kept but never executed.'),
                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull()
                        ->rows(2)
                        ->helperText('Optional — why this redirect exists, for other admins.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source_path')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('destination')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('status_code')
                    ->badge()
                    ->formatStateUsing(fn (RedirectStatus $state) => $state->label())
                    ->color(fn (RedirectStatus $state) => $state === RedirectStatus::Permanent || $state === RedirectStatus::PermanentRedirect ? 'success' : 'warning'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('hit_count')->label('Hits')->sortable(),
                Tables\Columns\TextColumn::make('last_hit_at')->dateTime()->sortable()->placeholder('Never'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\SelectFilter::make('status_code')->options(RedirectStatus::options()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
