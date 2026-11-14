@php
    $name = config('app.name');
    $pageTitle = request()->routeIs('site.home') ? $title : $title.' | '.$name;
    $canonical = url()->current();
    $image = asset('img/og.png');
    $menu = ['site.features', 'site.uses', 'site.pricing', 'site.faq'];
    $signedIn = auth('web')->check();
    $per = ['monthly' => 'P1M', 'yearly' => 'P1Y'];

    // structured data: who makes it, what it is, what it costs. No ratings or reviews: there are none to quote.
    $graph = [
        ['@type' => 'Organization', '@id' => url('/').'#organization', 'name' => $name, 'url' => url('/'), 'logo' => asset('img/og.png'),
            'parentOrganization' => ['@type' => 'Organization', 'name' => 'Fida Technologies', 'url' => 'https://fida.af']],
        ['@type' => 'WebSite', '@id' => url('/').'#website', 'url' => url('/'), 'name' => $name, 'description' => config('site.description'), 'inLanguage' => 'en', 'publisher' => ['@id' => url('/').'#organization']],
        array_filter([
            '@type' => 'SoftwareApplication', '@id' => url('/').'#software', 'name' => $name, 'url' => url('/'),
            'applicationCategory' => 'BusinessApplication', 'applicationSubCategory' => 'Subscription and renewal management',
            'operatingSystem' => 'Web browser (Android, iOS, Windows, macOS, Linux)', 'description' => config('site.summary'), 'inLanguage' => 'en',
            'featureList' => array_map(fn ($feature) => $feature[1], config('site.features')),
            'publisher' => ['@id' => url('/').'#organization'],
            'offers' => $plans->map(fn ($plan) => array_filter([
                '@type' => 'Offer', 'name' => $plan->name, 'price' => number_format((float) $plan->price, 2, '.', ''), 'priceCurrency' => $plan->currency,
                'url' => route('site.pricing'), 'availability' => 'https://schema.org/InStock',
                'priceSpecification' => isset($per[$plan->interval]) ? ['@type' => 'UnitPriceSpecification', 'price' => number_format((float) $plan->price, 2, '.', ''), 'priceCurrency' => $plan->currency, 'billingDuration' => $per[$plan->interval]] : null,
            ]))->values()->all() ?: null,
        ]),
    ];
    if (! request()->routeIs('site.home')) {
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $name, 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $canonical],
        ]];
    }
    if (! empty($faqSchema)) {
        $graph[] = ['@type' => 'FAQPage', '@id' => $canonical.'#faq', 'mainEntity' => array_map(fn ($faq) => [
            '@type' => 'Question', 'name' => $faq[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ], $faqSchema)];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
<meta name="theme-color" content="#f1f6f2">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $name }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $name }}: {{ config('site.tagline') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
<link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="{{ $name }} for AI assistants">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset_v('css/site.css') }}">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</head>
<body>
<a class="skip" href="#content">Skip to the content</a>

<header class="top" id="top">
    <div class="wrap">
        <a class="brand" href="{{ route('site.home') }}" aria-label="{{ $name }}, home"><span class="brand-mark" aria-hidden="true">D</span>{{ $name }}</a>
        <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="top" onclick="var t=document.getElementById('top');this.setAttribute('aria-expanded',t.classList.toggle('is-open'))">Menu</button>
        <nav class="nav" aria-label="Main">
            @foreach ($menu as $route)
                <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ \App\Http\Controllers\SiteController::PAGES[$route][1] }}</a>
            @endforeach
        </nav>
        <div class="top-actions">
            @if ($signedIn)
                <a class="btn btn-main" href="{{ route('dashboard') }}">Open {{ $name }}</a>
            @else
                <a class="btn btn-plain" href="{{ route('login') }}">Sign in</a>
                @if ($signup)<a class="btn btn-main" href="{{ route('register') }}">Start free</a>@endif
            @endif
        </div>
    </div>
</header>

<main id="content" class="page">
    @yield('content')
</main>

<footer class="foot">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <a class="brand" href="{{ route('site.home') }}"><span class="brand-mark" aria-hidden="true">D</span>{{ $name }}</a>
                <p style="margin:14px 0 0;max-width:26em">{{ config('site.tagline') }} Clients, renewals and reminders, with loans, income and expenses in any currency.</p>
            </div>
            <nav aria-label="Pages">
                <h3>{{ $name }}</h3>
                <ul>
                    @foreach (['site.features', 'site.uses', 'site.pricing', 'site.faq', 'site.contact'] as $route)
                        <li><a href="{{ route($route) }}">{{ \App\Http\Controllers\SiteController::PAGES[$route][1] }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <nav aria-label="Account and rules">
                <h3>Account</h3>
                <ul>
                    <li><a href="{{ route('login') }}">Sign in</a></li>
                    @if ($signup)<li><a href="{{ route('register') }}">Create an account</a></li>@endif
                    <li><a href="{{ route('site.privacy') }}">Privacy</a></li>
                    <li><a href="{{ route('site.terms') }}">Terms of use</a></li>
                </ul>
            </nav>
        </div>
        <div class="foot-end">
            <span>© {{ date('Y') }} {{ $name }}</span>
            <span>Made by <a href="https://fida.af" rel="noopener">Fida Technologies</a></span>
        </div>
    </div>
</footer>
</body>
</html>
