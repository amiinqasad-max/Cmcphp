<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO metadata (§9/§10): App\Services\SeoService already resolved every
    fallback the controller needs — these are just passed straight through
    to the render layer, nothing here re-implements fallback logic. --}}
    <x-seo-head
        :title="$title ?? config('app.name')"
        :description="$description ?? null"
        :canonical="$canonical ?? url()->current()"
        :robots-index="$robotsIndex ?? true"
        :robots-follow="$robotsFollow ?? true"
        :og-title="$ogTitle ?? null"
        :og-description="$ogDescription ?? null"
        :og-image="$ogImage ?? null"
        :og-type="$ogType ?? 'website'"
        :twitter-card="$twitterCard ?? 'summary_large_image'"
        :twitter-title="$twitterTitle ?? null"
        :twitter-description="$twitterDescription ?? null"
        :twitter-image="$twitterImage ?? null"
        :json-ld="$jsonLd ?? null"
    />

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

    {{--
        §1: nav is driven by the "primary_navigation" / "mobile_navigation"
        Menu locations (App\View\Components\Menu — cached, admin-managed).
        A fresh install with no menus configured yet still gets a working
        nav from the hard-coded fallback below (MenuSeeder creates sensible
        defaults on `db:seed`, but the fallback keeps this resilient even
        without it — e.g. a test hitting the layout directly).

        Fetched once and reused for both the desktop and mobile renders
        below (rather than instantiating <x-menu> per breakpoint) — a cache
        hit is cheap, but there's no reason to pay for it three times on
        one page load.
    --}}
    @php
        $primaryMenuItems = (new \App\View\Components\Menu('primary_navigation'))->items;
    @endphp

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

            @if ($primaryMenuItems && $primaryMenuItems->isNotEmpty())
                <ul class="hidden items-center gap-6 text-sm font-medium text-gray-700 md:flex">
                    @foreach ($primaryMenuItems as $item)
                        <x-menu-item :item="$item" />
                    @endforeach
                </ul>
            @else
                <ul class="hidden items-center gap-6 text-sm font-medium text-gray-700 md:flex">
                    <li><a href="{{ url('/') }}" class="hover:text-amber-600">Home</a></li>
                    <li><a href="{{ url('/articles') }}" class="hover:text-amber-600">Articles</a></li>
                </ul>
            @endif
        </nav>

        <div id="mobile-nav" x-ref="mobileNav" class="hidden border-t border-gray-100 px-4 py-3 md:hidden">
            @if ($primaryMenuItems && $primaryMenuItems->isNotEmpty())
                <ul class="space-y-1">
                    @foreach ($primaryMenuItems as $item)
                        <x-menu-item :item="$item" :mobile="true" />
                    @endforeach
                </ul>
            @else
                <ul class="space-y-1">
                    <li><a href="{{ url('/') }}" class="block rounded px-3 py-2 text-gray-700 hover:bg-gray-50">Home</a></li>
                    <li><a href="{{ url('/articles') }}" class="block rounded px-3 py-2 text-gray-700 hover:bg-gray-50">Articles</a></li>
                </ul>
            @endif
        </div>
    </header>

    <main id="main-content">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-gray-100 bg-gray-50">
        <div class="mx-auto max-w-5xl px-4 py-10 text-sm text-gray-500 sm:px-6 lg:px-8">
            <x-menu location="footer" class="mb-6 flex-wrap gap-x-6 gap-y-2 text-gray-600" />

            @if (($links = app(\App\Services\SettingsService::class)->socialLinks()) !== [])
                <ul class="mb-6 flex flex-wrap gap-4">
                    @foreach ($links as $platform => $url)
                        <li><a href="{{ $url }}" class="capitalize hover:text-amber-600" rel="noopener noreferrer" target="_blank">{{ $platform }}</a></li>
                    @endforeach
                </ul>
            @endif

            <p>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
