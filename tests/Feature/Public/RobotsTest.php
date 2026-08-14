<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_route_exists_and_returns_plain_text(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_public_content_is_allowed(): void
    {
        $this->get('/robots.txt')->assertSee('Allow: /', false);
    }

    public function test_admin_panel_is_disallowed(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /admin', false);
    }

    public function test_auth_routes_are_disallowed(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /register', false);
    }

    public function test_tracking_api_is_disallowed(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /api/', false);
    }

    public function test_sitemap_url_is_present(): void
    {
        $this->get('/robots.txt')->assertSee('Sitemap: '.route('sitemap'), false);
    }
}
