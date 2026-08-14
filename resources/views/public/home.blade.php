<x-layouts.public :title="$title" :description="$description">
    <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            {{ config('app.name') }}
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">
            The publishing platform is being built out phase by phase — see
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm">docs/ARCHITECTURE.md</code>
            for the full roadmap. Article listings, categories, and search land in Phase 2.
        </p>
    </section>
</x-layouts.public>
