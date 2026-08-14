@props(['post'])

<article class="group flex flex-col overflow-hidden rounded-lg border border-gray-100">
    <a href="{{ route('articles.show', $post) }}" class="block aspect-video overflow-hidden bg-gray-100">
        @if ($post->featuredImage)
            <img
                src="{{ $post->featuredImage->url }}"
                alt="{{ $post->featuredImage->alt_text ?? $post->title }}"
                loading="lazy"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
            >
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($post->category)
            <a href="{{ route('categories.show', $post->category) }}" class="text-xs font-semibold uppercase tracking-wide text-amber-600 hover:underline">
                {{ $post->category->name }}
            </a>
        @endif
        <h3 class="text-lg font-semibold leading-snug text-gray-900">
            <a href="{{ route('articles.show', $post) }}" class="hover:underline">
                {{ $post->title }}
            </a>
        </h3>
        @if ($post->excerpt)
            <p class="line-clamp-2 text-sm text-gray-600">{{ $post->excerpt }}</p>
        @endif
        <div class="mt-auto flex items-center gap-2 pt-2 text-xs text-gray-500">
            @if ($post->author)
                <span>{{ $post->author->name }}</span>
                <span aria-hidden="true">&middot;</span>
            @endif
            <time datetime="{{ $post->published_at?->toIso8601String() }}">
                {{ $post->published_at?->format('M j, Y') }}
            </time>
            @if ($post->reading_time_minutes)
                <span aria-hidden="true">&middot;</span>
                <span>{{ $post->reading_time_minutes }} min read</span>
            @endif
        </div>
    </div>
</article>
