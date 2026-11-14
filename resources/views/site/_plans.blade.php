{{-- the plans on offer, straight from the system, so the site never shows an old price --}}
@php
    $per = ['monthly' => 'a month', 'yearly' => 'a year', 'lifetime' => 'paid once'];
    $note = trim((string) platform('pay_note'));
@endphp
@if ($plans->isEmpty())
    <p class="intro">The plans are shown when you sign up.</p>
@else
    <div class="plans">
        @foreach ($plans as $plan)
            <article class="plan">
                <h3>{{ $plan->name }}</h3>
                <div class="price">{{ rtrim(rtrim(number_format((float) $plan->price, 2), '0'), '.') }} {{ $plan->currency }}</div>
                <div class="per">{{ $per[$plan->interval] ?? $plan->interval }}@if ($note), {{ $note }}@endif</div>
                @if ($plan->description)<p style="margin:14px 0 0">{{ $plan->description }}</p>@endif
                <ul>
                    <li>{{ $plan->max_clients ? 'Up to '.number_format($plan->max_clients).' clients' : 'Clients and contracts without limit' }}</li>
                    <li>{{ $plan->max_users ? ($plan->max_users === 1 ? '1 user' : 'Up to '.number_format($plan->max_users).' users') : 'Users without limit' }}</li>
                    <li>Reminder emails to you and your clients</li>
                    <li>Loans, income, expenses and reports</li>
                    <li>Every currency you work in</li>
                </ul>
                @if ($signup)
                    <a class="btn btn-main" href="{{ route('register') }}">Try it free for {{ $trial }} days</a>
                @else
                    <a class="btn btn-line" href="{{ route('login') }}">Sign in</a>
                @endif
            </article>
        @endforeach
    </div>
@endif
