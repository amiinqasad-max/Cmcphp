<?php

namespace App\Filament\Resources;

use App\Enums\PageStatus;
use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\MediaSelectField;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make()
                ->columnSpan(2)
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->live(onBlur: true)
                                ->maxLength(255)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Forms\Components\RichEditor::make('content')
                                ->required()
                                ->fileAttachmentsDisk(config('filesystems.default'))
                                ->fileAttachmentsDirectory('pages')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Section::make('SEO')
                        ->collapsible()
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')->maxLength(255),
                            Forms\Components\TextInput::make('seo_description')->maxLength(255),
                            Forms\Components\TextInput::make('canonical_url')->url()->maxLength(255),
                        ]),
                ]),
            Forms\Components\Group::make()
                ->columnSpan(1)
                ->schema([
                    Forms\Components\Section::make('Publish')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options(collect(PageStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                                ->default(PageStatus::Draft->value)
                                ->required()
                                ->live(),
                            Forms\Components\DateTimePicker::make('published_at')
                                ->visible(fn (Forms\Get $get) => $get('status') === PageStatus::Published->value),
                        ]),
                    Forms\Components\Section::make('Featured Image')
                        ->schema([
                            MediaSelectField::make('featured_image_media_id', 'featuredImage', ''),
                        ]),
                ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PageStatus $state) => $state->label())
                    ->color(fn (PageStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PageStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
