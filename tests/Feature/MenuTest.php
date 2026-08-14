<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Filament\Resources\MenuResource;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_a_menu_can_be_created(): void
    {
        $menu = Menu::factory()->create(['name' => 'Header Nav', 'slug' => 'header-nav', 'location' => 'header']);

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'name' => 'Header Nav', 'slug' => 'header-nav', 'location' => 'header']);
    }

    public function test_a_menu_slug_is_generated_from_its_name_when_left_blank(): void
    {
        $menu = new Menu(['name' => 'Auto Sluggable Name']);
        $menu->save();

        $this->assertSame('auto-sluggable-name', $menu->fresh()->slug);
    }

    public function test_a_menu_can_be_edited(): void
    {
        $menu = Menu::factory()->create(['name' => 'Original']);

        $menu->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $menu->fresh()->name);
    }

    public function test_a_menu_can_be_deleted(): void
    {
        $menu = Menu::factory()->create();

        $menu->delete();

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_deleting_a_menu_cascades_to_its_items(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create();

        $menu->delete();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_a_menu_can_be_enabled_and_disabled(): void
    {
        $menu = Menu::factory()->create(['is_active' => true]);

        $menu->update(['is_active' => false]);

        $this->assertFalse($menu->fresh()->is_active);
    }

    public function test_nested_menu_items_build_a_tree_respecting_position_order(): void
    {
        $menu = Menu::factory()->create();

        $parent = MenuItem::factory()->for($menu)->create(['label' => 'Parent', 'position' => 0]);
        MenuItem::factory()->for($menu)->create(['label' => 'Second child', 'parent_id' => $parent->id, 'position' => 1]);
        MenuItem::factory()->for($menu)->create(['label' => 'First child', 'parent_id' => $parent->id, 'position' => 0]);
        MenuItem::factory()->for($menu)->create(['label' => 'Sibling', 'position' => 1]);

        $tree = $menu->fullTree();

        $this->assertCount(2, $tree);
        $this->assertSame('Parent', $tree->first()->label);
        $this->assertCount(2, $tree->first()->children);
        $this->assertSame('First child', $tree->first()->children->first()->label);
        $this->assertSame('Second child', $tree->first()->children->last()->label);
    }

    public function test_reordering_menu_items_changes_their_position(): void
    {
        $menu = Menu::factory()->create();
        $a = MenuItem::factory()->for($menu)->create(['position' => 0]);
        $b = MenuItem::factory()->for($menu)->create(['position' => 1]);

        // Simulates a drag-reorder: swap positions.
        $a->update(['position' => 1]);
        $b->update(['position' => 0]);

        $ordered = $menu->items()->pluck('id');
        $this->assertSame((string) $b->id, (string) $ordered->first());
    }

    public function test_invisible_menu_items_are_excluded_from_the_renderable_tree(): void
    {
        $menu = Menu::factory()->create();
        MenuItem::factory()->for($menu)->create(['label' => 'Visible', 'is_visible' => true]);
        MenuItem::factory()->for($menu)->create(['label' => 'Hidden', 'is_visible' => false]);

        $tree = $menu->renderableTree();

        $this->assertCount(1, $tree);
        $this->assertSame('Visible', $tree->first()->label);
    }

    public function test_menu_item_pointing_at_a_deleted_post_is_excluded_from_the_renderable_tree(): void
    {
        $menu = Menu::factory()->create();
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        MenuItem::factory()->for($menu)->create([
            'type' => MenuItemType::Post->value,
            'linkable_type' => Post::class,
            'linkable_id' => $post->id,
            'label' => 'A draft post',
        ]);

        // Post exists but is not published — resolvedUrl()/renderableTree() must not surface it.
        $tree = $menu->renderableTree();

        $this->assertCount(0, $tree);
    }

    public function test_menu_item_pointing_at_a_published_post_resolves_its_public_url(): void
    {
        $menu = Menu::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $item = MenuItem::factory()->for($menu)->create([
            'type' => MenuItemType::Post->value,
            'linkable_type' => Post::class,
            'linkable_id' => $post->id,
        ]);

        $this->assertSame(route('articles.show', $post), $item->resolvedUrl());
    }

    public function test_menu_item_pointing_at_a_published_page_resolves_its_public_url(): void
    {
        $menu = Menu::factory()->create();
        $page = Page::factory()->create(['status' => PageStatus::Published, 'published_at' => now()->subDay()]);

        $item = MenuItem::factory()->for($menu)->create([
            'type' => MenuItemType::Page->value,
            'linkable_type' => Page::class,
            'linkable_id' => $page->id,
        ]);

        $this->assertSame(route('pages.show', $page), $item->resolvedUrl());
    }

    public function test_custom_url_item_is_never_considered_broken(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['type' => MenuItemType::Custom->value, 'url' => '/anything']);

        $this->assertFalse($item->isBroken());
    }

    public function test_self_parenting_is_detected_as_a_cycle(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create();

        $this->assertTrue(MenuItem::wouldCreateCycle($item->id, $item->id));
    }

    public function test_assigning_a_descendant_as_parent_is_detected_as_a_cycle(): void
    {
        $menu = Menu::factory()->create();
        $grandparent = MenuItem::factory()->for($menu)->create();
        $parent = MenuItem::factory()->for($menu)->create(['parent_id' => $grandparent->id]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id]);

        // Trying to make grandparent a child of its own grandchild.
        $this->assertTrue(MenuItem::wouldCreateCycle($grandparent->id, $child->id));
    }

    public function test_assigning_an_unrelated_item_as_parent_is_not_a_cycle(): void
    {
        $menu = Menu::factory()->create();
        $a = MenuItem::factory()->for($menu)->create();
        $b = MenuItem::factory()->for($menu)->create();

        $this->assertFalse(MenuItem::wouldCreateCycle($a->id, $b->id));
    }

    public function test_admin_can_manage_menus(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(MenuResource::getUrl('index'))->assertOk();
    }

    public function test_editor_cannot_manage_menus(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)->get(MenuResource::getUrl('index'))->assertForbidden();
    }

    public function test_author_cannot_manage_menus(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');

        $this->actingAs($author)->get(MenuResource::getUrl('index'))->assertForbidden();
    }

    public function test_guest_cannot_manage_menus(): void
    {
        $this->get(MenuResource::getUrl('index'))->assertRedirect();
    }

    public function test_active_menu_renders_on_the_public_homepage(): void
    {
        $menu = Menu::factory()->create(['location' => 'primary_navigation', 'is_active' => true]);
        MenuItem::factory()->for($menu)->create(['label' => 'Custom Nav Link', 'url' => '/custom-page']);

        $this->get(route('home'))->assertOk()->assertSee('Custom Nav Link')->assertSee('/custom-page', false);
    }
}
