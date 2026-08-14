<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdSlotResource;
use App\Filament\Resources\AdSlotResource\Pages\CreateAdSlot;
use App\Models\AdSlot;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    /**
     * §14/§34: ad_client is no longer an admin-entered field (it's not in
     * this form-fill at all) — CreateAdSlot::mutateFormDataBeforeCreate()
     * must still populate the NOT NULL column from ADSENSE_CLIENT_ID so
     * creating a slot doesn't require typing a redundant publisher ID.
     */
    public function test_creating_an_ad_slot_auto_fills_ad_client_from_config(): void
    {
        config(['services.adsense.client_id' => 'ca-pub-2222222222222222']);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $this->actingAs($editor);

        Livewire::test(CreateAdSlot::class)
            ->fillForm([
                'name' => 'Article Top',
                'ad_slot_code' => '1234567890',
                'format' => 'auto',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $slot = AdSlot::where('name', 'Article Top')->firstOrFail();
        $this->assertSame('ca-pub-2222222222222222', $slot->ad_client);
    }
}
