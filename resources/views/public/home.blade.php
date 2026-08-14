<x-layouts.public :title="$title" :description="$description" :canonical="$canonical ?? null"
    :robots-index="$robotsIndex ?? true" :robots-follow="$robotsFollow ?? true"
    :og-title="$ogTitle ?? null" :og-description="$ogDescription ?? null" :og-image="$ogImage ?? null" :og-type="$ogType ?? 'website'"
    :twitter-card="$twitterCard ?? 'summary_large_image'" :json-ld="$jsonLd ?? null">
    <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            {{ config('app.name') }}
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">
            The latest articles, hand-picked and freshly published.
        </p>

        @if ($posts->isEmpty())
            <p class="mt-12 text-gray-500">No articles published yet — check back soon.</p>
        @else
            <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-article-card :post="$post" />
                @endforeach
            </div>

            <div class="mt-10">
                <a href="{{ route('articles.index') }}" class="text-sm font-semibold text-amber-600 hover:underline">
                    View all articles &rarr;
                </a>
            </div>
        @endif
    </section>
</x-layouts.public>
