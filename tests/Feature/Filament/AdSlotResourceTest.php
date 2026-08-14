<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdSlotResource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdSlotResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_editor_can_manage_ad_slots(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)
            ->get(AdSlotResource::getUrl('index'))
            ->assertOk();
    }

    public function test_author_cannot_manage_ad_slots(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');

        $this->actingAs($author)
            ->get(AdSlotResource::getUrl('index'))
            ->assertForbidden();
    }
}
