<x-layouts.public :title="$title" :description="$description" :canonical="$canonical" :og-image="$ogImage" :json-ld="$jsonLd">
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

    {{-- Automatic next-article navigation lands in Phase 6. --}}
</x-layouts.public>
