@extends('layouts.app', ['title' => __('Receive a payment')])

@section('content')
<x-page-header :title="__('Receive a payment')" icon="hand-holding-dollar" :crumbs="[[__('Due & Renewals'), route('dues.index')], [$contract->client?->name, route('clients.show', ['id' => $contract->client_id])], [__('Receive')]]" />

<form method="POST" action="{{ route('contracts.pay.store', ['id' => $contract->id]) }}" class="card" style="max-width:640px">
    @csrf
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <span class="avatar avatar-md avatar-soft-primary flex-shrink-0">{{ \App\Models\User::initialsOf($contract->client?->name) }}</span>
            <div class="min-w-0">
                <div class="fw-semibold">{{ $contract->client?->name }}</div>
                <div class="small text-muted">{{ $contract->title }}@if ($contract->reference) · {{ $contract->reference }}@endif</div>
                <div class="small mt-1">{{ __('Due') }}: {{ \App\Http\Controllers\ContractController::dueCell($contract) }}</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="amount">{{ __('Amount received') }} ({{ $contract->currency }}) <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" dir="ltr" class="form-control form-control-lg @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', rtrim(rtrim(number_format((float) $contract->amount, 2, '.', ''), '0'), '.')) }}" autocomplete="off" required>
                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="paid_on">{{ __('Received on') }} <span class="text-danger">*</span></label>
                <x-date name="paid_on" id="paid_on" :value="old('paid_on', today_iso())" required class="form-control-lg {{ $errors->has('paid_on') ? 'is-invalid' : '' }}" />
                @error('paid_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="note">{{ __('Note') }}</label>
                <input type="text" class="form-control @error('note') is-invalid @enderror" id="note" name="note" value="{{ old('note') }}" maxlength="255" placeholder="{{ __('Cash, bank transfer, receipt number…') }}">
                @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="advance" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="advance" name="advance" value="1" @checked(old('advance', '1') == '1')>
                    <label class="form-check-label" for="advance">
                        @if ($after)
                            {{ __('This pays the period that was due. Move the next due date to :date.', ['date' => fmt_date($after)]) }}
                        @else
                            {{ __('This is the full payment. Mark the contract as finished.') }}
                        @endif
                    </label>
                    <div class="form-text">{{ __('Switch this off for a part payment: the due date stays where it is.') }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ url()->previous(route('dues.index')) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save the payment') }}</button>
    </div>
</form>
@endsection
