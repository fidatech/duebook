{{-- the plans on offer, straight from the system, so the site never shows an old price --}}
@php
    $per = ['monthly' => 'a month', 'yearly' => 'a year', 'lifetime' => 'paid once'];
    $note = trim((string) platform('pay_note'));
    // paid by the month or by the year: when both are offered, one of the two is shown at a time
    $paid = $plans->reject(fn ($plan) => $plan->isFree());
    $switch = $paid->contains('interval', 'monthly') && $paid->contains('interval', 'yearly');
    $saving = (int) $paid->where('interval', 'yearly')->map(function ($year) use ($paid) {
        $month = $paid->first(fn ($plan) => $plan->interval === 'monthly' && $plan->name === $year->name && $plan->currency === $year->currency);

        return $month ? (int) round(100 * (1 - (float) $year->price / (12 * (float) $month->price))) : 0;
    })->max();
@endphp
@if ($plans->isEmpty())
    <p class="intro">The plans are shown when you sign up.</p>
@else
    @if ($switch)
        <div class="cycle" role="group" aria-label="Pay monthly or yearly">
            <button type="button" class="btn btn-main" data-cycle-show="monthly" aria-pressed="true">Monthly</button>
            <button type="button" class="btn btn-line" data-cycle-show="yearly" aria-pressed="false">Yearly{{ $saving > 0 ? ' · save '.$saving.'%' : '' }}</button>
        </div>
    @endif
    <div class="plans">
        @foreach ($plans as $plan)
            @php $side = $switch && ! $plan->isFree() && in_array($plan->interval, ['monthly', 'yearly'], true) ? $plan->interval : null; @endphp
            <article class="plan" @if ($side) data-cycle="{{ $side }}" @endif @if ($side === 'yearly') hidden @endif>
                <h3>{{ $plan->name }}</h3>
                <div class="price">{{ $plan->priceText() }}</div>
                <div class="per">{{ $plan->isFree() ? 'free, with no time limit' : ($per[$plan->interval] ?? $plan->interval) }}@if ($note && ! $plan->isFree()), {{ $note }}@endif</div>
                @if ($plan->description)<p style="margin:14px 0 0">{{ $plan->description }}</p>@endif
                <ul>
                    <li>{{ $plan->max_clients ? 'Up to '.number_format($plan->max_clients).' clients' : 'Clients and contracts without limit' }}</li>
                    <li>{{ $plan->max_users ? ($plan->max_users === 1 ? '1 user' : 'Up to '.number_format($plan->max_users).' users') : 'Users without limit' }}</li>
                    <li>Reminder emails to you and your clients</li>
                    <li>Loans, income, expenses and reports</li>
                    <li>Every currency you work in</li>
                </ul>
                @if ($signup && $free)
                    <a class="btn btn-main" href="{{ route('register') }}">{{ $plan->isFree() ? 'Get started free' : 'Start free, upgrade any time' }}</a>
                @elseif ($signup)
                    <a class="btn btn-main" href="{{ route('register') }}">Try it free for {{ $trial }} days</a>
                @else
                    <a class="btn btn-line" href="{{ route('login') }}">Sign in</a>
                @endif
            </article>
        @endforeach
    </div>
    @if ($switch)
        <script>
        document.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-cycle-show]');
            if (!pick) return;
            var cycle = pick.getAttribute('data-cycle-show');
            document.querySelectorAll('.plan[data-cycle]').forEach(function (card) { card.hidden = card.getAttribute('data-cycle') !== cycle; });
            document.querySelectorAll('[data-cycle-show]').forEach(function (button) {
                var on = button === pick;
                button.classList.toggle('btn-main', on);
                button.classList.toggle('btn-line', !on);
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        });
        </script>
    @endif
@endif
