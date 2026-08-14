<x-layouts.public :title="$title" :description="$description" :canonical="$canonical"
    :robots-index="$robotsIndex ?? true" :robots-follow="$robotsFollow ?? true"
    :og-title="$ogTitle ?? null" :og-description="$ogDescription ?? null" :og-image="$ogImage" :og-type="$ogType ?? 'article'"
    :twitter-card="$twitterCard ?? 'summary_large_image'" :twitter-title="$twitterTitle ?? null"
    :twitter-description="$twitterDescription ?? null" :twitter-image="$twitterImage ?? null" :json-ld="$jsonLd">
    <article class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8" data-track-post-id="{{ $post->id }}">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm text-gray-500">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">Home</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('articles.index') }}" class="hover:underline">Articles</a></li>
                @if ($post->category)
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('categories.show', $post->category) }}" class="hover:underline">{{ $post->category->name }}</a></li>
                @endif
            </ol>
        </nav>

        <header>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ $post->title }}</h1>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                @if ($post->author)
                    <span class="font-medium text-gray-700">{{ $post->author->name }}</span>
                    <span aria-hidden="true">&middot;</span>
                @endif
                <time datetime="{{ $post->published_at?->toIso8601String() }}">
                    {{ $post->published_at?->format('F j, Y') }}
                </time>
                @if ($post->reading_time_minutes)
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ $post->reading_time_minutes }} min read</span>
                @endif
            </div>
        </header>

        @if ($post->featuredImage)
            <img
                src="{{ $post->featuredImage->url }}"
                alt="{{ $post->featuredImage->alt_text ?? $post->title }}"
                class="mt-8 aspect-video w-full rounded-lg object-cover"
            >
        @endif

        {{-- Article content is authored exclusively by authenticated admin/editor
             roles through the Filament rich text editor, not public user input,
             so each block's HTML is rendered as trusted markup here. Videos are
             spliced in at their [[VIDEO_n]] marker position by ArticleContentRenderer;
             ad placements (Phase 7) are interleaved into this same block sequence. --}}
        <div class="prose prose-gray mt-8 max-w-none prose-headings:font-semibold prose-a:text-amber-600">
            @foreach ($blocks as $block)
                @if ($block['type'] === 'video')
                    <x-video-player :video="$block['video']" />
                @elseif ($block['type'] === 'ad')
                    <x-ad-slot :ad-slot="$block['adSlot']" :post-id="$post->id" :ad-placement-id="$block['adPlacementId']" />
                @elseif (filled($block['html']))
                    {!! $block['html'] !!}
                @endif
            @endforeach
        </div>

        @if ($post->tags->isNotEmpty())
            <div class="mt-10 flex flex-wrap gap-2 border-t border-gray-100 pt-6">
                @foreach ($post->tags as $tag)
                    <a href="{{ route('tags.show', $tag) }}" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Populated by auto-next.js once the server confirms completion — never shown speculatively. --}}
        <div id="cmcphp-completion-banner" class="hidden mt-8 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3" role="status" aria-live="polite"></div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-5xl border-t border-gray-100 px-4 py-12 sm:px-6 lg:px-8" aria-labelledby="related-heading">
            <h2 id="related-heading" class="text-xl font-semibold text-gray-900">Related articles</h2>
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach ($related as $relatedPost)
                    <x-article-card :post="$relatedPost" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($commentsEnabled)
        <section class="mx-auto max-w-3xl border-t border-gray-100 px-4 py-12 sm:px-6 lg:px-8" aria-labelledby="comments-heading">
            <h2 id="comments-heading" class="text-xl font-semibold text-gray-900">
                Comments ({{ $post->approvedTopLevelComments->count() }})
            </h2>

            @if (session('status'))
                <p class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('comments.store') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="post_id" value="{{ $post->id }}">

                @auth
                    <p class="text-sm text-gray-600">Commenting as <span class="font-medium">{{ auth()->user()->name }}</span></p>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="author_name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="author_name" id="author_name" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('author_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="author_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="author_email" id="author_email" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('author_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endauth

                <div>
                    <label for="body" class="block text-sm font-medium text-gray-700">Comment</label>
                    <textarea name="body" id="body" rows="4" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
                    @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    Post comment
                </button>
            </form>

            <ul class="mt-10 space-y-6">
                @forelse ($post->approvedTopLevelComments as $comment)
                    <li>
                        <div class="flex items-baseline gap-2">
                            <span class="font-medium text-gray-900">{{ $comment->authorDisplayName() }}</span>
                            <time class="text-xs text-gray-500" datetime="{{ $comment->created_at->toIso8601String() }}">
                                {{ $comment->created_at->diffForHumans() }}
                            </time>
                        </div>
                        <p class="mt-1 text-gray-700">{{ $comment->body }}</p>

                        @if ($comment->replies->isNotEmpty())
                            <ul class="mt-4 space-y-4 border-l border-gray-100 pl-4">
                                @foreach ($comment->replies as $reply)
                                    <li>
                                        <div class="flex items-baseline gap-2">
                                            <span class="font-medium text-gray-900">{{ $reply->authorDisplayName() }}</span>
                                            <time class="text-xs text-gray-500">{{ $reply->created_at->diffForHumans() }}</time>
                                        </div>
                                        <p class="mt-1 text-gray-700">{{ $reply->body }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No comments yet — be the first to share your thoughts.</li>
                @endforelse
            </ul>
        </section>
    @endif
</x-layouts.public>
