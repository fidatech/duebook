@extends('layouts.admin', ['title' => __('Settings')])

@php
    // one box of the form: [name, label, help, width on a 12-column row, type]
    $text = fn (string $name, string $label, ?string $help = null, int $col = 6, string $type = 'text') => compact('name', 'label', 'help', 'col', 'type');
    $groups = [
        [__('Trial and sign-up'), __('What a new business gets.'), [
            $text('trial_days', 'Free trial, in days', 'Used for businesses that sign up from now on. 0 means no free trial.', 4, 'number'),
        ]],
        [__('Payments'), __('Shown on the Subscription page of businesses and on receipts.'), [
            $text('pay_note', 'Words beside the price', 'For example: or the equivalent in AFN'),
            $text('billing_email', 'Tell these addresses when a business says it has paid', 'Commas between several. Empty: every administrator who may sign in.'),
            $text('receipt_prefix', 'Receipt numbers start with', 'DB- gives DB-00001, DB-00002 ...', 4),
            $text('company_name', 'Name on receipts', 'Empty: the name of the system.', 8),
            $text('company_address', 'Address on receipts', null, 12, 'textarea'),
        ]],
        [__('Emails to businesses'), __('Sent by themselves, once each. A business whose payment is being checked is left alone.'), [
            $text('trial_warn_days', 'Warn before the trial ends, days before', 'For example 2. An email also goes on the last day and the day after.'),
            $text('renew_warn_days', 'Warn before a subscription ends, days before', 'For example 7, 1. An email also goes on the last day and the day after.'),
        ]],
        [__('Support'), __('How businesses reach you. Shown in emails and on receipts.'), [
            $text('support_email', 'Support email', null, 6, 'email'),
            $text('support_whatsapp', 'Support WhatsApp or phone'),
        ]],
    ];
@endphp

@section('content')
<x-page-header :title="__('Settings')" icon="gear" :subtitle="__('What is saved here is used instead of the values in the .env file.')" />

<form method="POST" action="{{ route('admin.settings.update') }}" class="card h-auto" style="max-width:920px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            @foreach ($groups as $index => [$title, $intro, $fields])
                <div class="col-12"><div class="{{ $index ? 'border-top pt-3 mt-2' : '' }}"><h6 class="mb-0 text-uppercase small fw-semibold text-muted">{{ $title }}</h6><div class="form-text">{{ $intro }}</div></div></div>
                @if ($index === 0)
                    <div class="col-12 col-md-8">
                        <div class="form-check form-switch mt-md-4 pt-md-2">
                            <input type="hidden" name="registration" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="s-registration" name="registration" value="1" @checked(old('registration', $values['registration'] ? '1' : '0') == '1')>
                            <label class="form-check-label" for="s-registration">{{ __('New businesses may sign up by themselves') }}</label>
                            <div class="form-text">{{ __('Switched off: only an administrator opens a business.') }}</div>
                        </div>
                    </div>
                @endif
                @foreach ($fields as $field)
                    <div class="col-12 col-md-{{ $field['col'] }}">
                        <label class="form-label" for="s-{{ $field['name'] }}">{{ __($field['label']) }}</label>
                        @if ($field['type'] === 'textarea')
                            <textarea class="form-control @error($field['name']) is-invalid @enderror" id="s-{{ $field['name'] }}" name="{{ $field['name'] }}" rows="2">{{ old($field['name'], $values[$field['name']]) }}</textarea>
                        @else
                            <input type="{{ $field['type'] }}" class="form-control @error($field['name']) is-invalid @enderror" id="s-{{ $field['name'] }}" name="{{ $field['name'] }}" value="{{ old($field['name'], $values[$field['name']]) }}" @if ($field['type'] === 'number') min="0" max="365" @endif>
                        @endif
                        @error($field['name'])<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if ($field['help'])<div class="form-text">{{ __($field['help']) }}</div>@endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
    <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
</form>
@endsection
