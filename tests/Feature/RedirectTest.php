<?php

namespace Tests\Feature;

use App\Filament\Resources\RedirectResource;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_a_301_redirect_is_followed_with_the_correct_status_and_location(): void
    {
        Redirect::factory()->create(['source_path' => '/old-url', 'destination' => '/new-url', 'status_code' => 301]);

        $response = $this->get('/old-url');

        $response->assertStatus(301);
        $response->assertRedirect('/new-url');
    }

    public function test_a_302_redirect_is_followed_with_the_correct_status(): void
    {
        Redirect::factory()->create(['source_path' => '/temp-a', 'destination' => '/dest-a', 'status_code' => 302]);

        $this->get('/temp-a')->assertStatus(302);
    }

    public function test_a_307_redirect_is_followed_with_the_correct_status(): void
    {
        Redirect::factory()->create(['source_path' => '/temp-b', 'destination' => '/dest-b', 'status_code' => 307]);

        $this->get('/temp-b')->assertStatus(307);
    }

    public function test_a_308_redirect_is_followed_with_the_correct_status(): void
    {
        Redirect::factory()->create(['source_path' => '/perm-b', 'destination' => '/dest-c', 'status_code' => 308]);

        $this->get('/perm-b')->assertStatus(308);
    }

    public function test_a_disabled_redirect_is_not_followed(): void
    {
        Redirect::factory()->create(['source_path' => '/disabled-url', 'destination' => '/wont-happen', 'is_active' => false]);

        $this->get('/disabled-url')->assertNotFound();
    }

    public function test_an_unknown_path_returns_a_normal_404(): void
    {
        $this->get('/this-path-has-no-redirect')->assertNotFound();
    }

    public function test_hitting_a_redirect_increments_its_hit_count(): void
    {
        $redirect = Redirect::factory()->create(['source_path' => '/counted', 'destination' => '/elsewhere']);

        $this->get('/counted');

        $this->assertSame(1, $redirect->fresh()->hit_count);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_redirect_to_an_external_url_works(): void
    {
        Redirect::factory()->create(['source_path' => '/go-external', 'destination' => 'https://example.com/landing']);

        $this->get('/go-external')->assertRedirect('https://example.com/landing');
    }

    public function test_a_direct_self_referencing_redirect_is_detected_as_a_loop(): void
    {
        $this->assertTrue(Redirect::wouldCreateLoop('/a', '/a'));
    }

    public function test_a_chained_redirect_loop_is_detected(): void
    {
        Redirect::factory()->create(['source_path' => '/b', 'destination' => '/c']);
        Redirect::factory()->create(['source_path' => '/c', 'destination' => '/a']);

        // /a -> /b -> /c -> /a would loop.
        $this->assertTrue(Redirect::wouldCreateLoop('/a', '/b'));
    }

    public function test_a_non_looping_chain_is_not_flagged(): void
    {
        Redirect::factory()->create(['source_path' => '/x', 'destination' => '/y']);

        $this->assertFalse(Redirect::wouldCreateLoop('/w', '/x'));
    }

    public function test_an_external_destination_can_never_loop(): void
    {
        $this->assertFalse(Redirect::wouldCreateLoop('/whatever', 'https://example.com'));
    }

    public function test_source_paths_are_normalized_without_query_strings_or_trailing_slashes(): void
    {
        $redirect = Redirect::factory()->create(['source_path' => '/messy-path/?utm=1']);

        $this->assertSame('/messy-path', $redirect->fresh()->source_path);
    }

    public function test_a_redirect_can_be_edited(): void
    {
        $redirect = Redirect::factory()->create(['destination' => '/original']);

        $redirect->update(['destination' => '/updated']);

        $this->assertSame('/updated', $redirect->fresh()->destination);
    }

    public function test_a_redirect_can_be_deleted(): void
    {
        $redirect = Redirect::factory()->create();

        $redirect->delete();

        $this->assertDatabaseMissing('redirects', ['id' => $redirect->id]);
    }

    public function test_admin_can_manage_redirects(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(RedirectResource::getUrl('index'))->assertOk();
    }

    public function test_editor_cannot_manage_redirects(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)->get(RedirectResource::getUrl('index'))->assertForbidden();
    }

    public function test_author_cannot_manage_redirects(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');

        $this->actingAs($author)->get(RedirectResource::getUrl('index'))->assertForbidden();
    }
}
