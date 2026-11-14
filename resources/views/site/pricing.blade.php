@extends('layouts.site', ['faqSchema' => array_values(array_filter($faqs, fn ($faq) => in_array($faq[0], ['How much does DueBook cost?', 'How do I pay for DueBook?', 'Can I get a refund or change my plan?', 'What happens if I stop paying?'], true)))])

@php
    $kinds = ['bank' => 'bank transfer', 'cash' => 'cash', 'online' => 'online payment', 'mobile' => 'mobile money', 'crypto' => 'USDT', 'other' => 'other'];
    $moneyFaqs = array_values(array_filter($faqs, fn ($faq) => in_array($faq[0], ['How much does DueBook cost?', 'How do I pay for DueBook?', 'Can I get a refund or change my plan?', 'What happens if I stop paying?'], true)));
@endphp

@section('content')
<section class="band">
    <div class="wrap inset">
        <h1>Pricing</h1>
        <p class="intro" style="margin-top:18px">{{ $facts['price'] }}@if ($signup && $free) Start on the free plan; no card is needed.@elseif ($signup) Try everything free for {{ $trial }} days first; no card is needed.@endif</p>
        @include('site._plans')

        <div class="pay-ways">
            <h2 style="font-size:1.3rem;margin:0">How you pay</h2>
            @if ($accounts->isEmpty())
                <p style="margin:10px 0 0">The ways to pay are shown on your Subscription page after you sign up.</p>
            @else
                <ul>
                    @foreach ($accounts as $account)
                        <li><strong>{{ $account->name }}</strong> ({{ $kinds[$account->type] ?? $account->type }}, {{ $account->currency }})</li>
                    @endforeach
                </ul>
            @endif
            <p style="margin:14px 0 0">You pay, upload a screenshot of the payment on your Subscription page, and we switch your subscription on after checking it, usually the same day. You get a numbered receipt.</p>
        </div>
    </div>
</section>

<section class="band band-sheet" aria-labelledby="rules">
    <div class="wrap inset">
        <h2 id="rules">Payment, refunds and changing your plan</h2>
        @include('site._faq', ['items' => $moneyFaqs])
    </div>
</section>

@include('site._cta', ['heading' => 'Try it before you pay.'])
@endsection
