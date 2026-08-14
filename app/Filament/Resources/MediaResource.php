<?php

namespace App\Filament\Resources;

use App\Enums\MediaType;
use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use App\Services\MediaProbeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    /**
     * Upload cap in kilobytes. TODO(Phase 3): source from `settings`
     * (content.max_upload_size) instead of a hard-coded default once the
     * Settings service exists.
     */
    private const MAX_UPLOAD_KB = 512_000; // 500MB, generous enough for video

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\FileUpload::make('path')
                        ->label('File')
                        ->disk(fn () => config('filesystems.default'))
                        ->directory('media')
                        ->required()
                        ->acceptedFileTypes([
                            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
                            'video/mp4', 'video/webm', 'video/quicktime',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->maxSize(self::MAX_UPLOAD_KB)
                        ->columnSpanFull()
                        ->disabled(fn (?Media $record) => $record !== null)
                        ->dehydrated(fn (?Media $record) => $record === null)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            if (! $state instanceof UploadedFile) {
                                return;
                            }

                            $mime = (string) $state->getMimeType();
                            $set('mime_type', $mime);
                            $set('size_bytes', $state->getSize());
                            $set('type', MediaType::fromMime($mime)->value);

                            if (blank($get('title'))) {
                                $set('title', pathinfo($state->getClientOriginalName(), PATHINFO_FILENAME));
                            }

                            $probe = app(MediaProbeService::class);

                            if (str_starts_with($mime, 'image/')) {
                                $dimensions = $probe->probeImageDimensions($state->getRealPath());
                                $set('width', $dimensions['width']);
                                $set('height', $dimensions['height']);
                            } elseif (str_starts_with($mime, 'video/')) {
                                $set('duration_seconds', $probe->probeVideoDurationSeconds($state->getRealPath()));
                            }
                        }),

                    Forms\Components\TextInput::make('title')->maxLength(255),
                    Forms\Components\TextInput::make('alt_text')
                        ->label('Alt text')
                        ->helperText('Required for accessibility on images used in content.')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('caption')->maxLength(255),
                    Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),

                    Forms\Components\Hidden::make('mime_type'),
                    Forms\Components\Hidden::make('size_bytes'),
                    Forms\Components\Hidden::make('type'),
                    Forms\Components\Hidden::make('width'),
                    Forms\Components\Hidden::make('height'),
                    Forms\Components\Hidden::make('duration_seconds'),

                    Forms\Components\Placeholder::make('detected_meta')
                        ->label('Detected metadata')
                        ->columnSpanFull()
                        ->content(function (Get $get) {
                            $bits = array_filter([
                                $get('mime_type'),
                                $get('size_bytes') ? number_format($get('size_bytes') / 1_048_576, 2).' MB' : null,
                                $get('width') && $get('height') ? "{$get('width')}×{$get('height')}px" : null,
                                $get('duration_seconds') ? gmdate('i:s', (int) $get('duration_seconds')).' duration' : null,
                            ]);

                            return $bits ? implode(' · ', $bits) : 'Upload a file to detect metadata automatically.';
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('url')
                    ->label('')
                    ->visibility(fn (Media $record) => $record->isImage() ? 'visible' : 'hidden')
                    ->square(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('mime_type')->label('MIME')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (Media $record) => $record->humanFileSize())
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state) => $state ? gmdate('i:s', $state) : '—'),
                Tables\Columns\TextColumn::make('uploadedBy.name')->label('Uploaded by')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(MediaType::cases())->mapWithKeys(fn (MediaType $t) => [$t->value => ucfirst($t->value)])),
            ])
            ->actions([
                Tables\Actions\Action::make('copyUrl')
                    ->label('Copy URL')
                    ->icon('heroicon-o-link')
                    ->action(fn () => null)
                    ->extraAttributes(fn (Media $record) => [
                        'x-on:click' => 'window.navigator.clipboard.writeText('.json_encode($record->url).'); $tooltip(\'Copied!\')',
                    ]),
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
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}
