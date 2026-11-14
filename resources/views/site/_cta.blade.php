{{-- the last word of every page: one action --}}
<section class="band closing">
    <div class="wrap inset">
        <h2>{{ $heading ?? 'Stop searching for who should pay you.' }}</h2>
        <p class="intro">@if ($signup && $free)Start on the free plan. No card is needed, and it takes a minute to add your first client.@elseif ($signup)Try everything free for {{ $trial }} days. No card is needed, and it takes a minute to add your first client.@else Sign in to your account to continue.@endif</p>
        <div class="hero-actions">
            @if ($signup)
                <a class="btn btn-main btn-big" href="{{ route('register') }}">{{ $free ? 'Get started free' : 'Start your free trial' }}</a>
                <a class="btn btn-line btn-big" href="{{ route('site.pricing') }}">See the price</a>
            @else
                <a class="btn btn-main btn-big" href="{{ route('login') }}">Sign in</a>
            @endif
        </div>
    </div>
</section>
