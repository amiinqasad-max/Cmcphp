<x-layouts.public :title="$title" :description="$description">
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Articles</h1>

        @if ($posts->isEmpty())
            <p class="mt-8 text-gray-500">No articles published yet.</p>
        @else
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-article-card :post="$post" />
                @endforeach
            </div>

            <nav class="mt-10" aria-label="Pagination">
                {{ $posts->links() }}
            </nav>
        @endif
    </div>
</x-layouts.public>
