<?php

namespace App\Filament\Resources;

use App\Enums\AdSlotFormat;
use App\Enums\AdSlotStatus;
use App\Filament\Resources\AdSlotResource\Pages;
use App\Models\AdSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdSlotResource extends Resource
{
    protected static ?string $model = AdSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Ad Slots';

    protected static ?string $navigationGroup = 'Advertisements';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Internal label, e.g. "Article Top" or "After Paragraph 3".'),
                    Forms\Components\TextInput::make('ad_client')
                        ->label('AdSense client (publisher ID)')
                        ->required()
                        ->placeholder('ca-pub-XXXXXXXXXXXXXXXX')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('ad_slot_code')
                        ->label('AdSense slot ID')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('format')
                        ->options(collect(AdSlotFormat::cases())->mapWithKeys(fn ($f) => [$f->value => ucfirst($f->value)]))
                        ->default(AdSlotFormat::Auto->value)
                        ->required(),
                    Forms\Components\Toggle::make('is_responsive')->label('Responsive')->default(true),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Use for automatic placements')
                        ->helperText('When automatic ad placement needs a slot and no specific one is configured, this is the one it uses.'),
                    Forms\Components\Select::make('status')
                        ->options(collect(AdSlotStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)]))
                        ->default(AdSlotStatus::Active->value)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ad_client')->label('Client')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ad_slot_code')->label('Slot ID')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('format')->badge(),
                Tables\Columns\IconColumn::make('is_responsive')->boolean(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (AdSlotStatus $state) => $state === AdSlotStatus::Active ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('placements_count')->counts('placements')->label('Used in'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AdSlotStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
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
            'index' => Pages\ListAdSlots::route('/'),
            'create' => Pages\CreateAdSlot::route('/create'),
            'edit' => Pages\EditAdSlot::route('/{record}/edit'),
        ];
    }
}
