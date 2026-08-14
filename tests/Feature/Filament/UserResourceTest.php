<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_manage_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertOk();
    }

    public function test_editor_cannot_manage_users(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)->get(UserResource::getUrl('index'))->assertForbidden();
    }

    public function test_author_cannot_manage_users(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');

        $this->actingAs($author)->get(UserResource::getUrl('index'))->assertForbidden();
    }
}
