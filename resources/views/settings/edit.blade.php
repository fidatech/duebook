@extends('layouts.app', ['title' => __('Settings')])

@section('content')
<x-page-header :title="__('Settings')" icon="gear" />

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <form method="POST" action="{{ route('settings.update') }}" class="card mb-0">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="s-name">{{ __('Name of your business') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="s-name" name="name" value="{{ old('name', $tenant->name) }}" maxlength="150" required>
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Clients see this name on reminder emails.') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="s-phone">{{ __('Phone') }}</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="s-phone" name="phone" value="{{ old('phone', $tenant->phone) }}" maxlength="50" dir="ltr">
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
                    </div>

                    <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Currencies') }}</h6></div></div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="s-currency">{{ __('Main currency') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase @error('currency') is-invalid @enderror" id="s-currency" name="currency" value="{{ old('currency', $tenant->currency) }}" maxlength="3" required dir="ltr">
                        @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="s-currencies">{{ __('Other currencies you work with') }}</label>
                        <input type="text" class="form-control text-uppercase @error('currencies') is-invalid @enderror" id="s-currencies" name="currencies" value="{{ old('currencies', implode(', ', array_diff($tenant->currencies ?? [], [$tenant->currency]))) }}" maxlength="200" dir="ltr" placeholder="AFN, EUR">
                        @error('currencies')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Three-letter codes with commas between them. Amounts are never converted: totals are shown for each currency.') }}</div>
                    </div>

                    <div class="col-12"><div class="border-top pt-3 mt-2"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ __('Reminder emails') }}</h6></div></div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="s-notify">{{ __('Send my reminders to') }}</label>
                        <input type="email" class="form-control @error('notify_email') is-invalid @enderror" id="s-notify" name="notify_email" value="{{ old('notify_email', $tenant->notify_email) }}" maxlength="255" dir="ltr" placeholder="{{ $tenant->email }}">
                        @error('notify_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Leave empty to use the email of the owner.') }}</div>
                    </div>
                    <div class="col-7 col-md-3">
                        <label class="form-label" for="s-days">{{ __('Days before') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('remind_days') is-invalid @enderror" id="s-days" name="remind_days" value="{{ old('remind_days', implode(', ', $tenant->remindDays())) }}" maxlength="100" required dir="ltr">
                        @error('remind_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-5 col-md-3">
                        <label class="form-label" for="s-hour">{{ __('At hour') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('remind_hour') is-invalid @enderror" id="s-hour" name="remind_hour" required data-plain>
                            @for ($hour = 0; $hour < 24; $hour++)
                                <option value="{{ $hour }}" @selected((int) old('remind_hour', $tenant->remind_hour) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                            @endfor
                        </select>
                        @error('remind_hour')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-text mt-0">{{ __('"30, 7, 1, 0" means: one email 30 days before a due date, again 7 days and 1 day before, and on the day itself. Late payments are repeated every Saturday. Clients are emailed on the same days, only for contracts where you switched that on.') }}</div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
        </form>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Your plan') }}</h5></div>
            <div class="card-body">
                <ul class="list-clean">
                    <li><span class="text-muted small flex-grow-1">{{ __('Plan') }}</span><span class="fw-semibold">{{ $plan?->name ?: '—' }}</span></li>
                    <li><span class="text-muted small flex-grow-1">{{ __('Status') }}</span><span class="fw-semibold">{{ __($tenant->statusLabel()) }}</span></li>
                    <li><span class="text-muted small flex-grow-1">{{ $tenant->status === 'trial' ? __('Trial ends') : __('Paid until') }}</span><span class="fw-semibold">{{ $tenant->endsOn() ? fmt_date($tenant->endsOn()) : __('No end date') }}</span></li>
                    <li><span class="text-muted small flex-grow-1">{{ __('Clients allowed') }}</span><span class="fw-semibold">{{ $plan?->max_clients ?: __('No limit') }}</span></li>
                    <li><span class="text-muted small flex-grow-1">{{ __('Users allowed') }}</span><span class="fw-semibold">{{ $plan?->max_users ?: __('No limit') }}</span></li>
                </ul>
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
