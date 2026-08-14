<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_public_pages_get_a_cache_control_header(): void
    {
        $response = $this->get(route('home'));

        // Symfony's HeaderBag normalizes Cache-Control directive order
        // (e.g. to "max-age=60, public"), so assert on content, not order.
        $header = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $header);
        $this->assertStringContainsString('max-age=60', $header);
    }

    public function test_authenticated_requests_are_not_cached(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $this->assertStringNotContainsString('public, max-age', $response->headers->get('Cache-Control') ?? '');
    }

    public function test_admin_panel_does_not_get_the_public_cache_header(): void
    {
        $response = $this->get('/admin/login');

        $this->assertStringNotContainsString('public, max-age=60', $response->headers->get('Cache-Control') ?? '');
    }
}
