@extends('layouts.app', ['title' => __('Subscription')])

@php
    $usable = $tenant->isUsable();
    $forever = $tenant->status === 'active' && $tenant->endsOn() === null;
    $per = ['monthly' => __('a month'), 'yearly' => __('a year'), 'lifetime' => __('once')];
    $kinds = \App\Http\Controllers\Admin\AccountController::TYPES;
@endphp

@section('content')
<x-page-header :title="__('Subscription')" icon="credit-card" />

<div class="row g-3">
    <div class="col-12 col-lg-5">
        {{-- where the business stands --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="sc-icon icon-soft {{ ! $usable ? 'danger' : ($tenant->status === 'trial' ? 'warning' : 'success') }} flex-shrink-0" style="width:48px;height:48px;font-size:20px"><x-icon :name="! $usable ? 'triangle-exclamation' : ($tenant->status === 'trial' ? 'hourglass-half' : 'circle-check')" /></span>
                    <div class="min-w-0">
                        @if ($tenant->status === 'suspended')
                            <div class="fw-bold fs-5">{{ __('Suspended') }}</div>
                            <div class="small text-muted">{{ __('Please contact support.') }}</div>
                        @elseif ($forever)
                            <div class="fw-bold fs-5">{{ __('Active') }}</div>
                            <div class="small text-muted">{{ __('No end date') }}</div>
                        @elseif (! $usable)
                            <div class="fw-bold fs-5">{{ $tenant->status === 'trial' ? __('Your free trial has ended') : __('Your subscription has ended') }}</div>
                            <div class="small text-muted">{{ __('It ended on :date. Your records are safe.', ['date' => fmt_date($tenant->endsOn())]) }}</div>
                        @elseif ($tenant->status === 'trial')
                            <div class="fw-bold fs-5">{{ $left > 0 ? trans_choice('{1} 1 day of free trial left|[2,*] :count days of free trial left', $left, ['count' => $left]) : __('Your free trial ends today') }}</div>
                            <div class="small text-muted">{{ __('Free until :date', ['date' => fmt_date($tenant->endsOn())]) }}</div>
                        @else
                            <div class="fw-bold fs-5">{{ __('Active') }}</div>
                            <div class="small text-muted">{{ __('Paid until :date', ['date' => fmt_date($tenant->endsOn())]) }}@if ($left !== null && $left <= 30) · {{ trans_choice('{0} ends today|{1} 1 day left|[2,*] :count days left', $left, ['count' => $left]) }}@endif</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- the plan --}}
        @if ($plan)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="small fw-semibold text-muted text-uppercase">{{ $plan->name }}</div>
                    <div class="d-flex align-items-baseline gap-2 mt-1">
                        <span class="fw-bold" style="font-size:34px;line-height:1.1" dir="ltr">{{ rtrim(rtrim(number_format((float) $plan->price, 2), '0'), '.') }} {{ $plan->currency }}</span>
                        <span class="text-muted">/ {{ $per[$plan->interval] ?? $plan->interval }}</span>
                    </div>
                    <div class="small text-muted mt-1">{{ trans_choice('{1} The first day is free.|[2,*] The first :count days are free.', config('duebook.trial_days'), ['count' => config('duebook.trial_days')]) }}</div>
                    @if ($plan->description)
                        <p class="mt-3 mb-0">{{ $plan->description }}</p>
                    @endif
                    <ul class="list-clean mt-3">
                        <li><x-icon name="check" class="text-success" /><span>{{ $plan->max_clients ? __('Up to :count clients', ['count' => number_format($plan->max_clients)]) : __('Clients without limit') }}</span></li>
                        <li><x-icon name="check" class="text-success" /><span>{{ $plan->max_users ? __('Up to :count users', ['count' => number_format($plan->max_users)]) : __('Users without limit') }}</span></li>
                        <li><x-icon name="check" class="text-success" /><span>{{ __('Renewal reminders by email, for you and your clients') }}</span></li>
                        <li><x-icon name="check" class="text-success" /><span>{{ __('Loans, income, expenses and reports') }}</span></li>
                    </ul>
                </div>
            </div>
        @endif

        @if ($history->isNotEmpty())
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Your payments') }}</h5></div>
                <div class="card-body">
                    <ul class="list-clean">
                        @foreach ($history as $row)
                            <li>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="d-block fw-semibold">{{ $row->plan?->name }}</span>
                                    <span class="d-block small text-muted">{{ fmt_date($row->starts_on) }} – {{ $row->ends_on ? fmt_date($row->ends_on) : __('no end') }}</span>
                                </span>
                                <span class="fw-semibold text-nowrap" dir="ltr">{{ money($row->amount, $row->currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-lg-7">
        @if ($pending)
            <div class="card mb-0">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="es-icon"><x-icon name="hourglass-half" /></div>
                        <h3>{{ __('Your payment is being checked') }}</h3>
                        <p>{{ __('You told us on :date that you paid :amount. As soon as we see the money, your subscription is switched on until :until.', ['date' => fmt_date($pending->created_at), 'amount' => money($pending->amount, $pending->currency), 'until' => $pending->ends_on ? fmt_date($pending->ends_on) : __('no end')]) }}</p>
                    </div>
                </div>
            </div>
        @elseif ($plan && ! $forever && $tenant->status !== 'suspended')
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('1. Pay :amount', ['amount' => money($plan->price, $plan->currency)]) }}</h5>
                </div>
                <div class="card-body">
                    @if ($accounts->isEmpty())
                        <p class="text-muted mb-0">{{ __('Please contact support for the way to pay.') }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($accounts as $account)
                                <li class="align-items-start">
                                    <span class="sc-icon icon-soft primary flex-shrink-0" style="width:38px;height:38px"><x-icon name="building-columns" /></span>
                                    <span class="min-w-0 flex-grow-1">
                                        <span class="d-block fw-semibold">{{ $account->name }} <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $account->currency }}</span></span>
                                        <span class="d-block small text-muted">{{ __($kinds[$account->type] ?? $account->type) }}</span>
                                        @if ($account->details)<span class="d-block small mt-1" style="white-space:pre-line" dir="auto">{{ $account->details }}</span>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            @if (auth()->user()->isOwner())
                <form method="POST" action="{{ route('billing.store') }}" class="card mb-0" enctype="multipart/form-data">
                    @csrf
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('2. Tell us you have paid') }}</h5>
                        <div class="card-subtitle small text-muted">{{ __('It covers :from to :until.', ['from' => fmt_date($period[0]), 'until' => $period[1] ? fmt_date($period[1]) : __('no end')]) }}</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="b-sent">{{ __('Paid on') }} <span class="text-danger">*</span></label>
                                <x-date name="sent_on" id="b-sent" :value="old('sent_on', today_iso())" required class="{{ $errors->has('sent_on') ? 'is-invalid' : '' }}" />
                                @error('sent_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            @if ($accounts->isNotEmpty())
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="b-account">{{ __('Paid to') }}</label>
                                    <select class="form-select @error('platform_account_id') is-invalid @enderror" id="b-account" name="platform_account_id" data-plain>
                                        @if ($accounts->count() > 1)<option value="">{{ __('Choose…') }}</option>@endif
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" @selected((string) old('platform_account_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency }})</option>
                                        @endforeach
                                    </select>
                                    @error('platform_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endif
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="b-reference">{{ __('Transfer or receipt number') }}</label>
                                <input type="text" class="form-control @error('reference') is-invalid @enderror" id="b-reference" name="reference" value="{{ old('reference') }}" maxlength="255" dir="ltr">
                                @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="b-receipt">{{ __('Photo of the receipt') }}</label>
                                <input type="file" class="form-control @error('receipt') is-invalid @enderror" id="b-receipt" name="receipt" accept=".pdf,image/*">
                                @error('receipt')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('I have paid') }}</button></div>
                </form>
            @else
                <div class="card mb-0"><div class="card-body"><p class="text-muted mb-0">{{ __('Only the owner of the business can send the payment.') }}</p></div></div>
            @endif
        @endif
    </div>
</div>
@endsection
