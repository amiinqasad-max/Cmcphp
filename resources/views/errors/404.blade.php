<x-layouts.public title="Page not found">
    <div class="mx-auto flex max-w-3xl flex-col items-center px-4 py-24 text-center sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">404</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Page not found</h1>
        <p class="mt-4 max-w-md text-gray-600">
            Sorry, we couldn't find the page you were looking for.
        </p>
        <a href="{{ route('home') }}" class="mt-8 inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Back to homepage
        </a>
    </div>
</x-layouts.public>
