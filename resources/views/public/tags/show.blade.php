<x-layouts.public :title="$title" :description="$description">
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-4 text-sm text-gray-500">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">Home</a></li>
                <li aria-hidden="true">/</li>
                <li>#{{ $tag->name }}</li>
            </ol>
        </nav>

        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">#{{ $tag->name }}</h1>

        @if ($posts->isEmpty())
            <p class="mt-8 text-gray-500">No articles tagged #{{ $tag->name }} yet.</p>
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
