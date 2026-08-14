<?php

namespace App\Filament\Resources;

use App\Enums\NextArticleMode;
use App\Enums\PostStatus;
use App\Filament\Resources\PostResource\Pages;
use App\Filament\Support\MediaSelectField;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

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
                            Forms\Components\Textarea::make('excerpt')
                                ->rows(2)
                                ->maxLength(500)
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('content')
                                ->required()
                                ->fileAttachmentsDisk(config('filesystems.default'))
                                ->fileAttachmentsDirectory('posts')
                                ->columnSpanFull(),
                        ]),
                ]),
            Forms\Components\Group::make()
                ->columnSpan(1)
                ->schema([
                    Forms\Components\Section::make('Publish')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options(collect(PostStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                                ->default(PostStatus::Draft->value)
                                ->required()
                                ->live(),
                            Forms\Components\DateTimePicker::make('published_at')
                                ->label(fn (Forms\Get $get) => $get('status') === PostStatus::Scheduled->value ? 'Publish at' : 'Published at')
                                ->visible(fn (Forms\Get $get) => in_array($get('status'), [PostStatus::Published->value, PostStatus::Scheduled->value], true))
                                ->required(fn (Forms\Get $get) => in_array($get('status'), [PostStatus::Published->value, PostStatus::Scheduled->value], true))
                                ->default(now()),
                            Forms\Components\Select::make('author_id')
                                ->relationship('author', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(fn () => Auth::id()),
                        ]),
                    Forms\Components\Section::make('Organize')
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('tags')
                                ->relationship('tags', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload(),
                        ]),
                    Forms\Components\Section::make('Featured Image')
                        ->schema([
                            MediaSelectField::make('featured_image_media_id', 'featuredImage', ''),
                        ]),
                    Forms\Components\Section::make('Next Article')
                        ->description('What readers see after completing this article.')
                        ->schema([
                            Forms\Components\Select::make('next_article_mode')
                                ->options(collect(NextArticleMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                                ->default(NextArticleMode::Auto->value)
                                ->live()
                                ->required(),
                            Forms\Components\Select::make('next_article_id')
                                ->label('Article')
                                ->relationship('nextArticle', 'title', fn (Builder $query, ?Post $record) => $record ? $query->whereKeyNot($record->id) : $query)
                                ->searchable()
                                ->preload()
                                ->visible(fn (Forms\Get $get) => $get('next_article_mode') === NextArticleMode::Manual->value),
                        ]),
                ]),
            Forms\Components\Group::make()
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Section::make('SEO')
                        ->collapsible()
                        ->collapsed()
                        ->relationship('seo')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')->maxLength(255)->columnSpanFull(),
                            Forms\Components\TextInput::make('meta_description')->maxLength(255)->columnSpanFull(),
                            Forms\Components\TextInput::make('canonical_url')->url()->maxLength(255),
                            Forms\Components\Select::make('schema_type')
                                ->options(['article' => 'Article', 'website' => 'Website', 'organization' => 'Organization', 'none' => 'None'])
                                ->default('article'),
                            Forms\Components\Toggle::make('robots_index')->label('Indexable (robots: index)')->default(true),
                            Forms\Components\Toggle::make('robots_follow')->label('Follow links (robots: follow)')->default(true),
                            Forms\Components\TextInput::make('og_title')->label('OG title')->maxLength(255),
                            Forms\Components\TextInput::make('og_description')->label('OG description')->maxLength(255),
                            MediaSelectField::make('og_image_media_id', 'ogImage', 'OG image'),
                        ]),
                ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featuredImage.url')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(60),
                Tables\Columns\TextColumn::make('category.name')->sortable(),
                Tables\Columns\TextColumn::make('author.name')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PostStatus $state) => $state->label())
                    ->color(fn (PostStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('reading_time_minutes')->label('Read time')->formatStateUsing(fn (?int $state) => $state ? "{$state} min" : '—'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PostStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Post $record) => route('articles.show', $record), shouldOpenInNewTab: true),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (Post $record) {
                        $copy = $record->replicate(['slug', 'published_at']);
                        $copy->title = $record->title.' (Copy)';
                        $copy->slug = Post::uniqueSlugFor(str($copy->title)->slug());
                        $copy->status = PostStatus::Draft;
                        $copy->published_at = null;
                        $copy->save();
                        $copy->tags()->sync($record->tags->pluck('id'));
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('publish')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn (Post $post) => $post->update([
                            'status' => PostStatus::Published,
                            'published_at' => $post->published_at ?? now(),
                        ]))),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn (Post $post) => $post->update(['status' => PostStatus::Unpublished]))),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
