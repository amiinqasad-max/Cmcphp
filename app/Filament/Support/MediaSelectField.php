<?php

namespace App\Filament\Support;

use App\Enums\MediaType;
use App\Models\Media;
use App\Services\MediaProbeService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Illuminate\Http\UploadedFile;

/**
 * A reusable "pick from the Media Library, or upload a new image inline"
 * field. Every image reference in the CMS (post featured image, category
 * image, OG image, ...) goes through this so uploads always create a proper
 * `media` row rather than a bare file path — see docs/ARCHITECTURE.md §13.
 */
class MediaSelectField
{
    public static function make(string $fieldName, string $relationshipName, string $label = 'Image', bool $imagesOnly = true): Select
    {
        return Select::make($fieldName)
            ->label($label)
            ->relationship($relationshipName, 'title')
            ->searchable()
            ->preload()
            ->getOptionLabelFromRecordUsing(fn (Media $media) => $media->title ?: basename($media->path))
            ->createOptionForm([
                FileUpload::make('upload')
                    ->label('File')
                    ->image($imagesOnly)
                    ->disk(config('filesystems.default'))
                    ->directory('media')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (! $state instanceof UploadedFile) {
                            return;
                        }

                        $mime = $state->getMimeType();
                        $set('mime_type', $mime);
                        $set('size_bytes', $state->getSize());

                        if (str_starts_with((string) $mime, 'image/')) {
                            $dimensions = app(MediaProbeService::class)->probeImageDimensions($state->getRealPath());
                            $set('width', $dimensions['width']);
                            $set('height', $dimensions['height']);
                        }
                    }),
                TextInput::make('title')->maxLength(255),
                TextInput::make('alt_text')->label('Alt text')->maxLength(255),
                TextInput::make('caption')->maxLength(255),
                Textarea::make('description')->rows(2),
                Hidden::make('mime_type'),
                Hidden::make('size_bytes'),
                Hidden::make('width'),
                Hidden::make('height'),
            ])
            ->createOptionUsing(function (array $data) {
                $mime = $data['mime_type'] ?? 'application/octet-stream';

                $media = Media::create([
                    'disk' => config('filesystems.default'),
                    'path' => $data['upload'],
                    'type' => MediaType::fromMime($mime)->value,
                    'mime_type' => $mime,
                    'size_bytes' => $data['size_bytes'] ?? 0,
                    'width' => $data['width'] ?? null,
                    'height' => $data['height'] ?? null,
                    'title' => $data['title'] ?? null,
                    'alt_text' => $data['alt_text'] ?? null,
                    'caption' => $data['caption'] ?? null,
                    'description' => $data['description'] ?? null,
                    'uploaded_by' => auth()->id(),
                ]);

                return $media->getKey();
            });
    }
}
