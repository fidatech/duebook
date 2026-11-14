@extends('layouts.site', ['faqSchema' => array_slice($faqs, 0, 6)])

@section('content')
<section class="hero">
    <div class="wrap">
        <div class="hero-grid">
            <div class="inset">
                <h1>{{ config('site.tagline') }}</h1>
                <p class="lead">DueBook keeps every client, contract and renewal date in one place, and emails you before each one is due. Loans, income and expenses too, in any currency.</p>
                <div class="hero-actions">
                    @if ($signup)
                        <a class="btn btn-main btn-big" href="{{ route('register') }}">{{ $free ? 'Get started free' : 'Start your free trial' }}</a>
                    @else
                        <a class="btn btn-main btn-big" href="{{ route('login') }}">Sign in</a>
                    @endif
                    <a class="btn btn-line btn-big" href="#how">See how it works</a>
                </div>
                @if ($signup)<p class="hero-note">{{ $free ? 'Free to start. No card needed.' : 'Free for '.$trial.' days. No card needed.' }}</p>@endif
            </div>

            {{-- what the system shows every morning; the names and amounts are examples --}}
            <div class="ledger" role="img" aria-label="An example of the due list in DueBook: five clients with what they pay for, the amount, and whether the payment is late, due soon or paid.">
                <div class="ledger-head"><strong>Due and renewals</strong><span>an example</span></div>
                <div class="ledger-row"><div><div class="ledger-who">Northgate Clinic</div><div class="ledger-what">Website hosting and domain</div></div><div><div class="ledger-amount">120.00 USD</div><div class="ledger-when is-late">12 days late</div></div></div>
                <div class="ledger-row"><div><div class="ledger-who">Baran Trading</div><div class="ledger-what">Accounting system, yearly support</div></div><div><div class="ledger-amount">45,000 AFN</div><div class="ledger-when is-late">due today</div></div></div>
                <div class="ledger-row"><div><div class="ledger-who">Olive Street Bakery</div><div class="ledger-what">Online shop care plan</div></div><div><div class="ledger-amount">60.00 USD</div><div class="ledger-when is-soon">in 7 days</div></div></div>
                <div class="ledger-row"><div><div class="ledger-who">Karim Motors</div><div class="ledger-what">Stock system licence</div></div><div><div class="ledger-amount">85,000 PKR</div><div class="ledger-when is-soon">in 30 days</div></div></div>
                <div class="ledger-row"><div><div class="ledger-who">Hill School</div><div class="ledger-what">Student portal, 2 domains</div></div><div><div class="ledger-amount">300.00 USD</div><div class="ledger-when is-paid">paid, next in a year</div></div></div>
                <div class="ledger-foot"><span>Expected this month</span><strong>180.00 USD · 45,000 AFN</strong></div>
            </div>
        </div>
    </div>
</section>

<section class="band band-sheet" aria-labelledby="what">
    <div class="wrap inset">
        <h2 id="what">What is DueBook?</h2>
        <p class="answer">{{ config('site.summary') }}</p>
    </div>
</section>

<section class="band" id="how" aria-labelledby="how-title">
    <div class="wrap inset">
        <h2 id="how-title">How you use it</h2>
        <p class="intro">Three steps, and the first one takes a minute.</p>
        <ol class="steps">
            @foreach (config('site.steps') as [$step, $text])
                <li><h3>{{ $step }}</h3><p>{{ $text }}</p></li>
            @endforeach
        </ol>
    </div>
</section>

<section class="band band-sheet" aria-labelledby="does">
    <div class="wrap inset">
        <h2 id="does">What it does for you</h2>
        <p class="intro">Everything is built around one question: who should pay, how much, and when.</p>
        <ul class="entries">
            @foreach (array_slice(config('site.features'), 0, 7) as [$icon, $feature, $text])
                <li><x-icon :name="$icon" /><h3>{{ $feature }}</h3><p>{{ $text }}</p></li>
            @endforeach
        </ul>
        <p style="margin:28px 0 0"><a class="btn btn-line" href="{{ route('site.features') }}">All features</a></p>
    </div>
</section>

<section class="band" aria-labelledby="who">
    <div class="wrap inset">
        <h2 id="who">Who it is for</h2>
        <p class="intro">Any business whose customers pay again on a date.</p>
        <div class="who">
            @foreach (config('site.audiences') as [, $audience, $text])
                <article><h3>{{ $audience }}</h3><p>{{ $text }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="band band-sheet" aria-labelledby="price">
    <div class="wrap inset">
        <h2 id="price">What it costs</h2>
        <p class="intro">{{ $facts['price'] }}@if ($signup && ! $free) The first {{ $trial }} days are free.@endif</p>
        @include('site._plans')
        <p style="margin:24px 0 0"><a href="{{ route('site.pricing') }}">Pricing, payment and refunds in full</a></p>
    </div>
</section>

<section class="band" aria-labelledby="asked">
    <div class="wrap inset">
        <h2 id="asked">Questions people ask</h2>
        @include('site._faq', ['items' => array_slice($faqs, 0, 6)])
        <p style="margin:24px 0 0"><a href="{{ route('site.faq') }}">All questions and answers</a></p>
    </div>
</section>

@include('site._cta')
@endsection
