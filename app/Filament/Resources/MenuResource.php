<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(255)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('location')
                        ->helperText('A suggested value from the list, or type any location key your theme looks for.')
                        ->datalist(array_keys(config('menus.locations')))
                        ->maxLength(60),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->live()
                        ->helperText('At most one active menu per location is allowed.')
                        ->rules([
                            fn (Get $get, ?Menu $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                $location = $get('location');

                                if (! $value || blank($location)) {
                                    return;
                                }

                                $conflict = Menu::query()
                                    ->where('location', $location)
                                    ->where('is_active', true)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                                    ->exists();

                                if ($conflict) {
                                    $fail("Another active menu is already assigned to \"{$location}\". Deactivate it first, or choose a different location.");
                                }
                            },
                        ]),
                    Forms\Components\Textarea::make('description')
                        ->columnSpanFull()
                        ->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (config("menus.locations.{$state}") ?? $state) : '—'),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
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
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
