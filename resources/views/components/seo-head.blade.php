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
    'twitterTitle' => null,
    'twitterDescription' => null,
    'twitterImage' => null,
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
<meta name="twitter:title" content="{{ $twitterTitle ?? $ogTitle ?? $title }}">
@if ($twitterDescription ?? $ogDescription ?? $description)
    <meta name="twitter:description" content="{{ $twitterDescription ?? $ogDescription ?? $description }}">
@endif
@if ($twitterImage ?? $ogImage)
    <meta name="twitter:image" content="{{ $twitterImage ?? $ogImage }}">
@endif

{{--
    $jsonLd may be a single node (an assoc array with "@type"/"@context")
    or a list of nodes (e.g. [Article, BreadcrumbList] — SeoService::forPost()
    returns both) — array_is_list() tells them apart so either shape emits
    one <script> tag per node without callers needing to know which case
    they're in.
--}}
@if ($jsonLd)
    @foreach (array_is_list($jsonLd) ? $jsonLd : [$jsonLd] as $node)
        <script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach
@endif
