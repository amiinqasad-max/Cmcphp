<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdPlacementResolver;
use App\Services\ArticleContentRenderer;
use App\Services\SettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_a_setting_can_be_created(): void
    {
        app(SettingsService::class)->set('general', 'site_name', 'My Test Site');

        $this->assertDatabaseHas('settings', ['group' => 'general', 'key' => 'site_name']);
    }

    public function test_a_setting_can_be_updated(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('general', 'site_name', 'Original Name');
        $settings->set('general', 'site_name', 'Updated Name');

        $this->assertSame('Updated Name', $settings->get('general', 'site_name'));
        $this->assertSame(1, Setting::where('group', 'general')->where('key', 'site_name')->count());
    }

    public function test_site_name_falls_back_to_the_configured_app_name_by_default(): void
    {
        $this->assertSame(config('app.name'), app(SettingsService::class)->siteName());
    }

    public function test_seo_default_robots_flags_default_to_true(): void
    {
        $settings = app(SettingsService::class);

        $this->assertTrue($settings->seoDefaultRobotsIndex());
        $this->assertTrue($settings->seoDefaultRobotsFollow());
    }

    public function test_social_links_default_to_an_empty_array(): void
    {
        $this->assertSame([], app(SettingsService::class)->socialLinks());
    }

    public function test_social_links_can_hold_an_arbitrary_platform_without_a_migration(): void
    {
        app(SettingsService::class)->set('social', 'links', ['mastodon' => 'https://mastodon.social/@example']);

        $this->assertSame(['mastodon' => 'https://mastodon.social/@example'], app(SettingsService::class)->socialLinks());
    }

    public function test_settings_cache_is_invalidated_when_a_setting_changes(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('general', 'site_name', 'First');
        $this->assertSame('First', $settings->get('general', 'site_name'));

        $settings->set('general', 'site_name', 'Second');

        $this->assertSame('Second', $settings->get('general', 'site_name'));
    }

    public function test_admin_can_access_the_settings_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(ManageSettings::getUrl())->assertOk();
    }

    public function test_editor_cannot_access_the_settings_page(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)->get(ManageSettings::getUrl())->assertForbidden();
    }

    public function test_author_cannot_access_the_settings_page(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');

        $this->actingAs($author)->get(ManageSettings::getUrl())->assertForbidden();
    }

    public function test_guest_cannot_access_the_settings_page(): void
    {
        $this->get(ManageSettings::getUrl())->assertRedirect();
    }

    public function test_adsense_client_id_is_not_exposed_as_a_settings_form_field(): void
    {
        // §14/§7: the one real secret in this area stays an env var
        // (config('services.adsense.client_id')), never a normal admin
        // form field — see docs/SECURITY.md. The class docblock is allowed
        // to explain that decision in prose; what must never appear is an
        // actual form field bound to it.
        $this->assertStringNotContainsString(
            "make('adsense",
            strtolower(file_get_contents(app_path('Filament/Pages/ManageSettings.php')))
        );
    }

    /**
     * Priority 2 gap fix: ads.* settings (existed on SettingsService since
     * Phase 7) previously had no admin UI at all — only reachable via
     * SettingsService::set() directly.
     */
    public function test_ad_placement_settings_can_be_saved_through_the_settings_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $category = Category::factory()->create();

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'ads' => [
                    'auto_placement_enabled' => false,
                    'max_ads_per_article' => 3,
                    'min_paragraph_spacing' => 6,
                    'min_paragraphs_required' => 2,
                    'excluded_category_ids' => [$category->id],
                    'excluded_post_ids' => [],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsService::class);
        $this->assertFalse($settings->adsAutoPlacementEnabled());
        $this->assertSame(3, $settings->maxAdsPerArticle());
        $this->assertSame(6, $settings->minParagraphSpacing());
        $this->assertSame(2, $settings->minParagraphsRequiredForAds());
        $this->assertSame([$category->id], $settings->excludedAdCategoryIds());
    }

    public function test_excluding_a_category_via_settings_actually_stops_ads_on_its_articles(): void
    {
        $category = Category::factory()->create();
        app(SettingsService::class)->set('ads', 'excluded_category_ids', [$category->id]);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'category_id' => $category->id,
            'content' => collect(range(1, 6))->map(fn ($i) => "<p>Paragraph {$i}.</p>")->implode(''),
        ]);

        $resolver = app(AdPlacementResolver::class);
        $blocks = app(ArticleContentRenderer::class)->blocks($post);

        $result = $resolver->interleave($post, $blocks);

        $this->assertFalse($result->contains(fn ($block) => $block['type'] === 'ad'));
    }
}
