<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicContentCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_query_is_served_from_cache_on_second_request(): void
    {
        Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->get(route('home'))->assertOk();

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The cached second request shouldn't re-run the posts listing query.
        $this->assertFalse(
            collect($queries)->contains(fn ($q) => str_contains($q['query'], 'from "posts"')),
            'Expected the second homepage request to be served from cache, not re-query posts.'
        );
    }

    public function test_homepage_cache_is_invalidated_when_a_post_is_published(): void
    {
        $author = User::factory()->create();
        $this->get(route('home'))->assertOk(); // primes the cache with 0 posts

        $post = Post::factory()->published()->create(['author_id' => $author->id, 'title' => 'Brand New Article']);

        $response = $this->get(route('home'));
        $response->assertSee('Brand New Article');
    }

    public function test_category_page_cache_is_invalidated_when_a_post_is_added(): void
    {
        $category = Category::factory()->create();
        $this->get(route('categories.show', $category))->assertOk(); // primes cache

        Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'category_id' => $category->id,
            'title' => 'Freshly Categorized',
        ]);

        $this->get(route('categories.show', $category))->assertSee('Freshly Categorized');
    }
}
