<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO metadata: pass title/description/canonical/og/jsonLd from the controller/view --}}
    <x-seo-head :title="$title ?? config('app.name')" :description="$description ?? null" :canonical="$canonical ?? url()->current()"
        :og-image="$ogImage ?? null" :json-ld="$jsonLd ?? null" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Public site never loads Filament/admin panel assets. --}}
    @stack('head')

    @if (config('services.adsense.client_id'))
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client_id') }}" crossorigin="anonymous"></script>
    @endif
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased font-sans">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-amber-500 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <header class="border-b border-gray-100">
        <nav aria-label="Primary" class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-tight text-gray-900">
                {{ config('app.name') }}
            </a>

            <button type="button" x-data @click="$refs.mobileNav.classList.toggle('hidden')"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 md:hidden"
                aria-controls="mobile-nav" aria-expanded="false" aria-label="Toggle navigation menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <ul class="hidden items-center gap-6 text-sm font-medium text-gray-700 md:flex">
                <li><a href="{{ url('/') }}" class="hover:text-amber-600">Home</a></li>
                <li><a href="{{ url('/articles') }}" class="hover:text-amber-600">Articles</a></li>
                <li><a href="{{ url('/search') }}" class="hover:text-amber-600" aria-label="Search">Search</a></li>
            </ul>
        </nav>

        <ul id="mobile-nav" x-ref="mobileNav" class="hidden space-y-1 border-t border-gray-100 px-4 py-3 md:hidden">
            <li><a href="{{ url('/') }}" class="block rounded px-3 py-2 text-gray-700 hover:bg-gray-50">Home</a></li>
            <li><a href="{{ url('/articles') }}" class="block rounded px-3 py-2 text-gray-700 hover:bg-gray-50">Articles</a></li>
            <li><a href="{{ url('/search') }}" class="block rounded px-3 py-2 text-gray-700 hover:bg-gray-50">Search</a></li>
        </ul>
    </header>

    <main id="main-content">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-gray-100 bg-gray-50">
        <div class="mx-auto max-w-5xl px-4 py-10 text-sm text-gray-500 sm:px-6 lg:px-8">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
