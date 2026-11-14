@extends('layouts.app', ['title' => __('Settings')])

@php
    $names = config('duebook.currency_names');
    $offered = config('duebook.currencies');
    $main = old('currency', $tenant->currency);
    $mine = old('picked', $tenant->currencies ?? []);
    // the ones on offer first, then the codes the business added by hand
    $tiles = array_values(array_unique(array_merge($offered, $tenant->currencies ?? [], [$tenant->currency])));
    $used = count(array_unique(array_merge([$tenant->currency], $tenant->currencies ?? [])));
@endphp

@section('content')
<x-page-header :title="__('Settings')" icon="gear" />

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="POST" action="{{ route('settings.update') }}" class="d-flex flex-column gap-3">
            @csrf
            @method('PUT')

            {{-- the business --}}
            <div class="card mb-0">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Your business') }}</h5>
                </div>
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
                            <div class="form-text">{{ __('Due dates and reminder emails follow this clock.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- currencies --}}
            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">{{ __('Currencies') }}</h5>
                        <div class="card-subtitle small text-muted">{{ __('Tick every currency you receive or pay money in.') }}</div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary-emphasis flex-shrink-0">{{ trans_choice('{1} 1 in use|[2,*] :count in use', $used, ['count' => $used]) }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="s-currency">{{ __('Main currency') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror" id="s-currency" name="currency" required data-plain>
                                @foreach ($tiles as $code)
                                    <option value="{{ $code }}" @selected($main === $code)>{{ $code }}@isset($names[$code]) · {{ $names[$code][0] }}@endisset</option>
                                @endforeach
                            </select>
                            @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-7">
                            <div class="form-text mb-md-2">{{ __('The main currency is chosen first in every form. You can still pick another one each time.') }}</div>
                        </div>
                    </div>

                    <div class="cur-grid">
                        @foreach ($tiles as $code)
                            @php
                                $isMain = $tenant->currency === $code;
                            @endphp
                            <div class="cur-tile {{ $isMain ? 'is-main' : '' }}">
                                <input type="checkbox" name="picked[]" id="cur-{{ $code }}" value="{{ $code }}" autocomplete="off" @checked($isMain || in_array($code, $mine, true)) @if ($isMain) onclick="return false" aria-readonly="true" @endif>
                                <label for="cur-{{ $code }}">
                                    <span class="cur-sym" dir="ltr">{{ $names[$code][1] ?? mb_substr($code, 0, 1) }}</span>
                                    <span class="cur-text"><span class="cur-code">{{ $code }}</span><span class="cur-name">{{ $names[$code][0] ?? __('Added by you') }}</span></span>
                                    @if ($isMain)
                                        <span class="cur-main">{{ __('Main') }}</span>
                                    @else
                                        <span class="cur-check"><x-icon name="check" /></span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('picked.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="s-currencies">{{ __('Add a currency that is not above') }}</label>
                            <input type="text" class="form-control text-uppercase @error('currencies') is-invalid @enderror" id="s-currencies" name="currencies" value="{{ old('currencies') }}" maxlength="200" dir="ltr" placeholder="OMR, QAR">
                            @error('currencies')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Its three-letter code. Commas between several.') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-start gap-2 small text-muted border rounded p-3 h-100">
                                <x-icon name="circle-info" class="mt-1" />
                                <span>{{ __('Amounts are never converted. Every page shows a total for each currency, and the currency switch at the top of a page shows one at a time.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- reminders --}}
            <div class="card mb-0">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Reminder emails') }}</h5>
                    <div class="card-subtitle small text-muted">{{ __('One email a day with what is coming up and what is late.') }}</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
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
                            <div class="d-flex align-items-start gap-2 small text-muted border rounded p-3">
                                <x-icon name="circle-info" class="mt-1" />
                                <span>{{ __('"30, 7, 1, 0" means: one email 30 days before a due date, again 7 days and 1 day before, and on the day itself. Late payments are repeated every Saturday. Clients are emailed on the same days, only for contracts where you switched that on.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="me-1" /> {{ __('Save settings') }}</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <div class="d-flex flex-column gap-3">
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
                    <a class="btn btn-soft-primary w-100 mt-3" href="{{ route('billing.show') }}"><x-icon name="credit-card" class="me-1" /> {{ __('Subscription') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
