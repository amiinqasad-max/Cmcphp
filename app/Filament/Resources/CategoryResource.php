<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Support\MediaSelectField;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Content';

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
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent category')
                        ->relationship('parent', 'name', fn ($query, $record) => $record ? $query->whereKeyNot($record->id) : $query)
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('position')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Textarea::make('description')
                        ->columnSpanFull()
                        ->rows(3),
                    MediaSelectField::make('image_media_id', 'image', 'Category Image'),
                ]),
            Forms\Components\Section::make('SEO')
                ->collapsible()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('seo_title')->maxLength(255),
                    Forms\Components\TextInput::make('seo_description')->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image.url')->label('')->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->sortable(),
                Tables\Columns\TextColumn::make('posts_count')->counts('posts')->label('Posts'),
                Tables\Columns\TextColumn::make('position')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent category')
                    ->relationship('parent', 'name'),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
