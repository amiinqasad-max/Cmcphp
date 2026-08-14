<?php

namespace Tests\Feature\Public;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_article_is_publicly_visible(): void
    {
        $post = Post::factory()->published()->create([
            'title' => 'How to Save Money',
            'author_id' => User::factory()->create()->id,
        ]);

        $response = $this->get(route('articles.show', $post));

        $response->assertOk();
        $response->assertSee('How to Save Money');
    }

    public function test_draft_article_returns_404_publicly(): void
    {
        $post = Post::factory()->create([
            'status' => PostStatus::Draft,
            'author_id' => User::factory()->create()->id,
        ]);

        $this->get(route('articles.show', $post))->assertNotFound();
    }

    public function test_future_scheduled_article_returns_404_publicly(): void
    {
        $post = Post::factory()->scheduled()->create([
            'author_id' => User::factory()->create()->id,
        ]);

        $this->get(route('articles.show', $post))->assertNotFound();
    }

    public function test_article_index_only_lists_published_articles(): void
    {
        $author = User::factory()->create();
        $published = Post::factory()->published()->create(['author_id' => $author->id]);
        Post::factory()->create(['author_id' => $author->id, 'status' => PostStatus::Draft]);

        $response = $this->get(route('articles.index'));

        $response->assertOk();
        $response->assertSee($published->title);
    }

    public function test_category_page_only_lists_published_articles_in_that_category(): void
    {
        $author = User::factory()->create();
        $category = Category::factory()->create();
        $other = Category::factory()->create();

        $inCategory = Post::factory()->published()->create(['author_id' => $author->id, 'category_id' => $category->id]);
        $inOtherCategory = Post::factory()->published()->create(['author_id' => $author->id, 'category_id' => $other->id]);

        $response = $this->get(route('categories.show', $category));

        $response->assertOk();
        $response->assertSee($inCategory->title);
        $response->assertDontSee($inOtherCategory->title);
    }
}
