@props([
    'title' => config('app.name'),
    'description' => null,
    'canonical' => url()->current(),
    'robotsIndex' => true,
    'robotsFollow' => true,
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'twitterCard' => 'summary_large_image',
    'jsonLd' => null,
])

<title>{{ $title }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $robotsIndex ? 'index' : 'noindex' }},{{ $robotsFollow ? 'follow' : 'nofollow' }}">

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $ogTitle ?? $title }}">
@if ($ogDescription ?? $description)
    <meta property="og:description" content="{{ $ogDescription ?? $description }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif

<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $ogTitle ?? $title }}">
@if ($ogDescription ?? $description)
    <meta name="twitter:description" content="{{ $ogDescription ?? $description }}">
@endif
@if ($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif

@if ($jsonLd)
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
