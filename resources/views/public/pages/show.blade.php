<x-layouts.public :title="$title" :description="$description" :canonical="$canonical">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ $page->title }}</h1>

        @if ($page->featuredImage)
            <img
                src="{{ $page->featuredImage->url }}"
                alt="{{ $page->featuredImage->alt_text ?? $page->title }}"
                class="mt-8 aspect-video w-full rounded-lg object-cover"
            >
        @endif

        {{-- Same trust model as articles: content is authored by admin/editor roles only. --}}
        <div class="prose prose-gray mt-8 max-w-none">
            {!! $page->content !!}
        </div>
    </article>
</x-layouts.public>
