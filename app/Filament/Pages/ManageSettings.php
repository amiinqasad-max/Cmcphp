<?php

namespace App\Filament\Pages;

use App\Filament\Support\MediaSelectField;
use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * §7: a single settings page, not a resource — there's exactly one row of
 * "current settings" to edit, not a list of records. Backed by the same
 * generic `settings` key/value table every other phase already reads
 * through SettingsService (§12 — "a structured key/value settings
 * architecture is acceptable if it fits the existing codebase"), so
 * nothing here introduces a parallel config store.
 *
 * Deliberately excludes an "Analytics / Tracking" tab: the CMS's own
 * first-party analytics (Phase 8) has no site-wide toggle to configure —
 * it's always-on server-side event tracking, not an embeddable script —
 * and the one real secret in this area (ADSENSE_CLIENT_ID) stays an env
 * var per docs/SECURITY.md, not a form field ("do not expose sensitive
 * secrets in normal admin forms", §14).
 *
 * The "Advertising" tab *is* included — App\Services\SettingsService's
 * ads.* accessors (max ads per article, paragraph spacing, excluded
 * categories/posts) have existed since Phase 7, but had no admin UI at
 * all until this audit pass; they were only settable via
 * SettingsService::set() directly. AdSlot's own AdSense credentials stay
 * env-only (see AdSlotResource) — this tab only covers *placement rules*.
 */
class ManageSettings extends Page implements HasForms
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'General Settings';

    protected static string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Shield's page-permission convention: "{page prefix}_{ClassName}" — see config/filament-shield.php. */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_ManageSettings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->currentValues());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Site Identity')
                            ->schema([
                                Forms\Components\TextInput::make('general.site_name')
                                    ->label('Site name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('general.site_tagline')
                                    ->label('Tagline')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('general.site_description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                MediaSelectField::plain('general.logo_media_id', 'Logo'),
                                MediaSelectField::plain('general.favicon_media_id', 'Favicon'),
                                MediaSelectField::plain('general.default_social_image_media_id', 'Default social share image')
                                    ->helperText('Used when a post/page has no featured image and no OG image of its own.'),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Contact')
                            ->schema([
                                Forms\Components\TextInput::make('contact.email')
                                    ->label('Contact email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('contact.support_email')
                                    ->label('Support email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('contact.phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(50),
                                Forms\Components\Textarea::make('contact.address')
                                    ->label('Address')
                                    ->rows(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Social Links')
                            ->schema([
                                Forms\Components\KeyValue::make('social.links')
                                    ->hiddenLabel()
                                    ->keyLabel('Platform')
                                    ->valueLabel('URL')
                                    ->addActionLabel('Add social link')
                                    ->helperText('Any key works (facebook, twitter, instagram, youtube, telegram, linkedin, ...) — the public site loops over whatever you add here, so a new platform never needs a code change.')
                                    ->reorderable(),
                            ]),

                        Forms\Components\Tabs\Tab::make('SEO Defaults')
                            ->schema([
                                Forms\Components\TextInput::make('seo.default_title')
                                    ->label('Default SEO title')
                                    ->maxLength(255)
                                    ->helperText('Used when a post/page/category has no SEO title of its own.'),
                                Forms\Components\Textarea::make('seo.default_description')
                                    ->label('Default meta description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('seo.default_twitter_card')
                                    ->label('Default Twitter/X card type')
                                    ->options(['summary' => 'Summary', 'summary_large_image' => 'Summary with large image'])
                                    ->default('summary_large_image'),
                                MediaSelectField::plain('seo.default_og_image_media_id', 'Default OG image'),
                                Forms\Components\Toggle::make('seo.default_robots_index')
                                    ->label('Indexable by default (robots: index)')
                                    ->default(true),
                                Forms\Components\Toggle::make('seo.default_robots_follow')
                                    ->label('Follow links by default (robots: follow)')
                                    ->default(true),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Advertising')
                            ->schema([
                                Forms\Components\Toggle::make('ads.auto_placement_enabled')
                                    ->label('Automatic ad placement')
                                    ->helperText('When on, remaining ad slots (up to the maximum below) are filled automatically per the spacing rule. Manually placed ads on a post always take priority.')
                                    ->default(true)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('ads.max_ads_per_article')
                                    ->label('Maximum ads per article')
                                    ->numeric()->minValue(0)->maxValue(20)
                                    ->default(5)
                                    ->required(),
                                Forms\Components\TextInput::make('ads.min_paragraph_spacing')
                                    ->label('Minimum paragraphs between automatic ads')
                                    ->numeric()->minValue(1)->maxValue(20)
                                    ->default(4)
                                    ->required(),
                                Forms\Components\TextInput::make('ads.min_paragraphs_required')
                                    ->label('Minimum article length (paragraphs) to carry ads at all')
                                    ->numeric()->minValue(0)->maxValue(20)
                                    ->default(4)
                                    ->required(),
                                Forms\Components\Select::make('ads.excluded_category_ids')
                                    ->label('Categories excluded from ads')
                                    ->multiple()
                                    ->searchable()
                                    ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('ads.excluded_post_ids')
                                    ->label('Articles excluded from ads')
                                    ->multiple()
                                    ->searchable()
                                    ->options(fn () => Post::query()->orderBy('title')->limit(200)->pluck('title', 'id'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        foreach ($state as $group => $fields) {
            foreach ((array) $fields as $key => $value) {
                app(SettingsService::class)->set($group, $key, $value);
            }
        }

        // §33: settings affect the whole site, so a change here is exactly
        // the kind of admin action the audit trail exists for (see
        // ActivityLogger's own docblock, which explicitly promises
        // "settings changes" coverage). One entry per save — not per field
        // — naming which groups were touched, consistent with how
        // menu.items_updated logs once per menu save rather than per item.
        app(ActivityLogger::class)->log('settings.updated', null, ['groups' => array_keys($state)]);

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function currentValues(): array
    {
        $rows = Setting::query()->get(['group', 'key', 'value']);

        $values = [
            'general' => ['site_name' => config('app.name')],
            'seo' => ['default_twitter_card' => 'summary_large_image', 'default_robots_index' => true, 'default_robots_follow' => true],
            'social' => ['links' => []],
            'ads' => [
                'auto_placement_enabled' => true,
                'max_ads_per_article' => 5,
                'min_paragraph_spacing' => 4,
                'min_paragraphs_required' => 4,
                'excluded_category_ids' => [],
                'excluded_post_ids' => [],
            ],
        ];

        foreach ($rows as $row) {
            $values[$row->group][$row->key] = $row->value;
        }

        return $values;
    }
}
