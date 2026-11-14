@extends('layouts.app', ['title' => __('Settings')])

@php
    $names = config('duebook.currency_names');
    $offered = config('duebook.currencies');
    $mine = $tenant->currencies ?? [];
    // the tiles: the usual currencies, then any other code this business added by hand
    $tiles = array_values(array_unique(array_merge($offered, $mine, [$tenant->currency])));
    $ticked = array_map('strtoupper', old('picked', $mine));
    $main = strtoupper(old('currency', $tenant->currency));
    $choices = config('duebook.remind_choices');
    $days = array_map('intval', old('days', $tenant->remindDays()));
    $otherDays = array_values(array_diff($days, $choices));
@endphp

@section('content')
<x-page-header :title="__('Settings')" icon="gear" :subtitle="__('Your business, the currencies you work in, and when you are reminded.')" />

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="POST" action="{{ route('settings.update') }}" class="d-flex flex-column gap-3" id="settingsForm">
            @csrf
            @method('PUT')

            {{-- the business --}}
            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft primary"><x-icon name="building" /></span><div><h5>{{ __('Your business') }}</h5><p>{{ __('Clients see this name on the reminder emails you send them.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-name">{{ __('Name of your business') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="s-name" name="name" value="{{ old('name', $tenant->name) }}" maxlength="150" required>
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-phone">{{ __('Phone') }}</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="s-phone" name="phone" value="{{ old('phone', $tenant->phone) }}" maxlength="50" dir="ltr" placeholder="+93 70 000 0000">
                            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-country">{{ __('Country') }}</label>
                            <input type="text" class="form-control @error('country') is-invalid @enderror" id="s-country" name="country" value="{{ old('country', $tenant->country) }}" maxlength="100">
                            @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-timezone">{{ __('Time zone') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('timezone') is-invalid @enderror" id="s-timezone" name="timezone" required data-search>
                                @foreach ($timezones as $zone)
                                    <option value="{{ $zone }}" @selected(old('timezone', $tenant->timezone) === $zone)>{{ $zone }}</option>
                                @endforeach
                            </select>
                            @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Due dates and reminder hours follow this clock.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- currencies --}}
            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft success"><x-icon name="coins" /></span><div><h5>{{ __('Currencies') }}</h5><p>{{ __('Tap the currencies you work in. Amounts are never converted: every page shows a total for each one.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <div class="pick-grid" id="currencyTiles">
                        @foreach ($tiles as $code)
                            @php
                                [$label, $sign] = $names[$code] ?? [__('Added by you'), mb_substr($code, 0, 1)];
                            @endphp
                            <div>
                                <input type="checkbox" class="btn-check" name="picked[]" id="cur-{{ $code }}" value="{{ $code }}" autocomplete="off" @checked(in_array($code, $ticked, true) || $code === $main)>
                                <label class="pick-tile {{ $code === $main ? 'is-main' : '' }}" for="cur-{{ $code }}" data-code="{{ $code }}">
                                    <span class="pick-sign" dir="ltr">{{ $sign }}</span>
                                    <span class="min-w-0"><span class="pick-code">{{ $code }}<span class="pick-main">{{ __('Main') }}</span></span><span class="pick-name text-truncate">{{ __($label) }}</span></span>
                                    <span class="pick-check"><x-icon name="check" /></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('picked.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="s-currency">{{ __('Main currency') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror" id="s-currency" name="currency" required data-plain>
                                @foreach ($tiles as $code)
                                    <option value="{{ $code }}" @selected($main === $code)>{{ $code }}@isset($names[$code]) · {{ __($names[$code][0]) }}@endisset</option>
                                @endforeach
                            </select>
                            @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Chosen first in every form. It is always in use.') }}</div>
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="s-currencies">{{ __('Add a currency that is not above') }}</label>
                            <input type="text" class="form-control text-uppercase @error('currencies') is-invalid @enderror" id="s-currencies" name="currencies" value="{{ old('currencies') }}" maxlength="200" dir="ltr" placeholder="OMR, QAR" autocomplete="off">
                            @error('currencies')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Its three-letter code. Several: put commas between them. It appears as a tile after you save.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- reminders --}}
            <div class="card mb-0">
                <div class="card-header">
                    <div class="set-head"><span class="set-icon icon-soft warning"><x-icon name="bell" /></span><div><h5>{{ __('Reminder emails') }}</h5><p>{{ __('One email a day with what is coming and what is late. Late payments are repeated every Saturday.') }}</p></div></div>
                </div>
                <div class="card-body">
                    <label class="form-label">{{ __('Remind me') }} <span class="text-danger">*</span></label>
                    <div class="pick-grid is-small">
                        @foreach ($choices as $day)
                            <div>
                                <input type="checkbox" class="btn-check" name="days[]" id="day-{{ $day }}" value="{{ $day }}" autocomplete="off" @checked(in_array($day, $days, true))>
                                <label class="pick-tile" for="day-{{ $day }}"><span class="min-w-0"><span class="pick-code">{{ $day === 0 ? __('On the day') : $day }}</span><span class="pick-name">{{ $day === 0 ? __('it is due') : trans_choice('{1} day before|[2,*] days before', $day) }}</span></span><span class="pick-check"><x-icon name="check" /></span></label>
                            </div>
                        @endforeach
                    </div>
                    @error('days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('remind_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Clients are emailed on the same days, only for the contracts where you switched that on.') }}</div>

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="s-days">{{ __('Other days before') }}</label>
                            <input type="text" class="form-control @error('remind_days') is-invalid @enderror" id="s-days" name="remind_days" value="{{ old('remind_days', implode(', ', $otherDays)) }}" maxlength="100" dir="ltr" placeholder="45, 10" inputmode="numeric" autocomplete="off">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="s-hour">{{ __('At') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('remind_hour') is-invalid @enderror" id="s-hour" name="remind_hour" required data-plain>
                                @for ($hour = 0; $hour < 24; $hour++)
                                    <option value="{{ $hour }}" @selected((int) old('remind_hour', $tenant->remind_hour) === $hour)>{{ date('g:00 A', mktime($hour, 0, 0)) }}</option>
                                @endfor
                            </select>
                            @error('remind_hour')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="s-notify">{{ __('Send them to') }}</label>
                            <input type="email" class="form-control @error('notify_email') is-invalid @enderror" id="s-notify" name="notify_email" value="{{ old('notify_email', $tenant->notify_email) }}" maxlength="255" dir="ltr" placeholder="{{ $tenant->email }}">
                            @error('notify_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Empty: the email of the owner.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="save-bar">
                <span class="small text-muted">{{ __('Changes are kept when you press Save.') }}</span>
                <button type="submit" class="btn btn-primary px-4"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card h-auto">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Your plan') }}</h5>
                <span class="badge {{ $tenant->isUsable() ? ($tenant->status === 'trial' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis') : 'bg-danger-subtle text-danger-emphasis' }}">{{ __($tenant->statusLabel()) }}</span>
            </div>
            <div class="card-body">
                <div class="fs-5 fw-bold">{{ $plan?->name ?: __('No plan yet') }}</div>
                <div class="small text-muted mb-3">{{ $tenant->endsOn() ? ($tenant->status === 'trial' ? __('Free trial until :date', ['date' => fmt_date($tenant->endsOn())]) : __('Paid until :date', ['date' => fmt_date($tenant->endsOn())])) : __('No end date') }}</div>
                <ul class="list-clean">
                    <li><span class="text-muted small flex-grow-1">{{ __('Clients allowed') }}</span><span class="fw-semibold">{{ $plan?->max_clients ?: __('No limit') }}</span></li>
                    <li><span class="text-muted small flex-grow-1">{{ __('Users allowed') }}</span><span class="fw-semibold">{{ $plan?->max_users ?: __('No limit') }}</span></li>
                </ul>
                <a class="btn btn-soft-primary w-100 mt-3" href="{{ route('billing.show') }}"><x-icon name="credit-card" class="me-1" /> {{ __('Subscription') }}</a>
                @if ($subscriptions->isNotEmpty())
                    <div class="small fw-semibold text-muted mt-3 mb-2">{{ __('Payments for your plan') }}</div>
                    <ul class="list-clean">
                        @foreach ($subscriptions as $subscription)
                            <li><span class="small flex-grow-1">{{ fmt_date($subscription->starts_on) }} – {{ $subscription->ends_on ? fmt_date($subscription->ends_on) : __('no end') }}</span><span class="small fw-semibold" dir="ltr">{{ money($subscription->amount, $subscription->currency) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// the main currency is always ticked and carries a "Main" mark on its tile
(function () {
    var select = document.getElementById('s-currency'), tiles = document.getElementById('currencyTiles');
    if (!select || !tiles) return;
    var sync = function () {
        tiles.querySelectorAll('.pick-tile').forEach(function (tile) {
            var main = tile.getAttribute('data-code') === select.value, box = document.getElementById(tile.getAttribute('for'));
            tile.classList.toggle('is-main', main);
            if (main && box) box.checked = true;
        });
    };
    select.addEventListener('change', sync);
    tiles.addEventListener('change', function (e) {
        // the main currency cannot be switched off
        if (e.target.value === select.value && !e.target.checked) e.target.checked = true;
    });
    sync();
})();
</script>
@endpush
