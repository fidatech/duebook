@extends('layouts.app', ['title' => __('Subscription')])

@php
    $usable = $tenant->isUsable();
    $trial = $tenant->status === 'trial';
    $forever = $tenant->status === 'active' && $tenant->endsOn() === null;
    $canPay = $plan && ! $pending && ! $forever && $tenant->status !== 'suspended';
    $kinds = \App\Http\Controllers\Admin\AccountController::TYPES;
    $per = ['monthly' => __('a month'), 'yearly' => __('a year'), 'lifetime' => __('once')];
    // "199 USD", without useless zeros
    $price = $plan ? rtrim(rtrim(number_format((float) $plan->price, 2), '0'), '.').' '.$plan->currency : '';
    $note = config('duebook.pay_note');
    $tone = ! $usable ? 'danger' : ($trial ? 'warning' : 'success');
    $icon = ! $usable ? 'triangle-exclamation' : ($trial ? 'hourglass-half' : 'circle-check');
    // how much of the free trial is used, for the bar
    $trialDays = max(1, (int) config('duebook.trial_days'));
    $used = $trial && $usable && $left !== null ? min(100, max(4, (int) round(100 * ($trialDays - $left) / $trialDays))) : null;
    $includes = $plan ? [
        $plan->max_clients ? __('Up to :count clients', ['count' => number_format($plan->max_clients)]) : __('As many clients and contracts as you need'),
        $plan->max_users ? trans_choice('{1} 1 user|[2,*] Up to :count users', $plan->max_users, ['count' => $plan->max_users]) : __('As many users as you need'),
        __('Renewal reminders by email, for you and your clients'),
        __('Loans, income, expenses and reports'),
    ] : [];
@endphp

@section('content')
<x-page-header :title="__('Subscription')" icon="credit-card" />

{{-- where the business stands, and the price --}}
<div class="card h-auto">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:52px;height:52px;font-size:21px"><x-icon :name="$icon" /></span>
            <div class="min-w-0 flex-grow-1">
                @if ($tenant->status === 'suspended')
                    <div class="fw-bold fs-5">{{ __('Suspended') }}</div>
                    <div class="small text-muted">{{ __('Please contact support.') }}</div>
                @elseif ($forever)
                    <div class="fw-bold fs-5">{{ __('Active') }}</div>
                    <div class="small text-muted">{{ __('No end date') }}</div>
                @elseif (! $usable)
                    <div class="fw-bold fs-5">{{ $trial ? __('Your free trial has ended') : __('Your subscription has ended') }}</div>
                    <div class="small text-muted">{{ __('It ended on :date. Your records are safe: pay to continue where you stopped.', ['date' => fmt_date($tenant->endsOn())]) }}</div>
                @elseif ($trial)
                    <div class="fw-bold fs-5">{{ $left > 0 ? trans_choice('{1} 1 day of free trial left|[2,*] :count days of free trial left', $left, ['count' => $left]) : __('Your free trial ends today') }}</div>
                    <div class="small text-muted">{{ __('Free until :date. Pay any time before then and you lose nothing.', ['date' => fmt_date($tenant->endsOn())]) }}</div>
                @else
                    <div class="fw-bold fs-5">{{ __('Active') }}</div>
                    <div class="small text-muted">{{ __('Paid until :date', ['date' => fmt_date($tenant->endsOn())]) }}@if ($left !== null && $left <= 30) · {{ trans_choice('{0} ends today|{1} 1 day left|[2,*] :count days left', $left, ['count' => $left]) }}@endif</div>
                @endif
            </div>
            @if ($plan && ! $forever)
                <div class="text-end flex-shrink-0">
                    <div class="small fw-semibold text-muted text-uppercase">{{ $plan->name }}</div>
                    <div class="lh-1 mt-1"><span class="fw-bold" style="font-size:30px" dir="ltr">{{ $price }}</span> <span class="text-muted small">/ {{ $per[$plan->interval] ?? $plan->interval }}</span></div>
                </div>
            @endif
        </div>
        @if ($used !== null)
            <div class="progress mt-3" style="height:6px" role="progressbar" aria-valuenow="{{ $used }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-warning" style="width:{{ $used }}%"></div></div>
        @endif
    </div>
</div>

@if ($pending)
    <div class="card h-auto">
        <div class="card-body">
            <div class="empty-state">
                <div class="es-icon"><x-icon name="hourglass-half" /></div>
                <h3>{{ __('Your payment is being checked') }}</h3>
                <p>{{ __('You told us on :date that you paid :amount. As soon as we see the money, your subscription is switched on until :until.', ['date' => fmt_date($pending->created_at), 'amount' => money($pending->amount, $pending->currency), 'until' => $pending->ends_on ? fmt_date($pending->ends_on) : __('no end')]) }}</p>
            </div>
        </div>
    </div>
@endif

<div class="row g-3">
    @if ($canPay)
        {{-- step 1: pay --}}
        <div class="col-12 col-lg-6">
            <div class="card mb-0">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="badge bg-primary rounded-pill me-1">1</span> {{ __('Pay :amount', ['amount' => $price]) }}@if ($note) <span class="fw-normal text-muted">{{ __($note) }}</span>@endif</h5>
                </div>
                <div class="card-body">
                    @forelse ($accounts as $account)
                        <div class="{{ $loop->first ? '' : 'border-top pt-4 mt-4' }}">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="sc-icon icon-soft primary flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$account->type === 'bank' ? 'building-columns' : 'wallet'" /></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $account->name }}</div>
                                    <div class="small text-muted">{{ __($kinds[$account->type] ?? $account->type) }}</div>
                                </div>
                            </div>

                            @if ($account->qr_path)
                                <div class="text-center">
                                    <img src="{{ route('billing.qr', ['id' => $account->id]) }}" alt="{{ __('QR code of :name', ['name' => $account->name]) }}" class="border rounded" style="width:260px;max-width:100%;height:auto;background:#fff">
                                    <div class="small text-muted mt-2">{{ __('Scan it with the :name app', ['name' => $account->name]) }}</div>
                                    <a class="btn btn-sm btn-light-secondary mt-2 d-lg-none" href="{{ route('billing.qr', ['id' => $account->id]) }}" download="{{ \Illuminate\Support\Str::slug($account->name) }}-qr.png"><x-icon name="download" class="me-1" /> {{ __('Save the QR code to this phone') }}</a>
                                </div>
                            @endif

                            @if ($account->number)
                                @if ($account->qr_path)
                                    <div class="text-center small text-muted my-3">{{ __('or send it to this number') }}</div>
                                @endif
                                <div class="d-flex align-items-center justify-content-between gap-2 border rounded px-3 py-2">
                                    <span class="fs-5 fw-bold user-select-all text-truncate" dir="ltr">{{ $account->number }}</span>
                                    <button type="button" class="btn btn-sm btn-soft-primary flex-shrink-0" data-copy="{{ $account->number }}" data-copied="{{ __('Copied') }}"><x-icon name="copy" class="me-1" /><span>{{ __('Copy') }}</span></button>
                                </div>
                            @endif

                            @if ($account->details)
                                <p class="small text-muted mt-3 mb-0" style="white-space:pre-line" dir="auto">{{ $account->details }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Please contact support for the way to pay.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- step 2: send the screenshot --}}
        <div class="col-12 col-lg-6">
            <div class="d-flex flex-column gap-3">
                @if (auth()->user()->isOwner())
                    <form method="POST" action="{{ route('billing.store') }}" class="card mb-0" enctype="multipart/form-data">
                        @csrf
                        <div class="card-header">
                            <h5 class="card-title mb-0"><span class="badge bg-primary rounded-pill me-1">2</span> {{ __('Send us the screenshot') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="b-receipt">{{ __('Screenshot of the payment') }} <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control form-control-lg @error('receipt') is-invalid @enderror" id="b-receipt" name="receipt" accept="image/*,.pdf" required data-preview="#b-preview">
                                    @error('receipt')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <img id="b-preview" alt="" class="border rounded mt-2" style="max-height:220px;max-width:100%;display:none">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label" for="b-sent">{{ __('Paid on') }} <span class="text-danger">*</span></label>
                                    <x-date name="sent_on" id="b-sent" :value="old('sent_on', today_iso())" required class="{{ $errors->has('sent_on') ? 'is-invalid' : '' }}" />
                                    @error('sent_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label" for="b-reference">{{ __('Transfer number') }} <span class="text-muted fw-normal">({{ __('if you have it') }})</span></label>
                                    <input type="text" class="form-control @error('reference') is-invalid @enderror" id="b-reference" name="reference" value="{{ old('reference') }}" maxlength="255" dir="ltr">
                                    @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                @if ($accounts->count() === 1)
                                    <input type="hidden" name="platform_account_id" value="{{ $accounts->first()->id }}">
                                @elseif ($accounts->count() > 1)
                                    <div class="col-12">
                                        <label class="form-label" for="b-account">{{ __('Paid to') }}</label>
                                        <select class="form-select @error('platform_account_id') is-invalid @enderror" id="b-account" name="platform_account_id" data-plain>
                                            <option value="">{{ __('Choose…') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}" @selected((string) old('platform_account_id') === (string) $account->id)>{{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('platform_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endif
                            </div>

                            <div class="d-flex justify-content-between align-items-baseline gap-3 border-top mt-4 pt-3">
                                <span class="small text-muted">{{ __('Your subscription will run') }}</span>
                                <span class="fw-semibold text-end">{{ fmt_date($period[0]) }} – {{ $period[1] ? fmt_date($period[1]) : __('no end') }}</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><x-icon name="check" class="me-1" /> {{ __('I have paid') }}</button>
                            <p class="small text-muted text-center mt-2 mb-0">{{ __('We check the payment and switch your subscription on, usually the same day.') }}</p>
                        </div>
                    </form>
                @else
                    <div class="card mb-0"><div class="card-body"><p class="text-muted mb-0">{{ __('Only the owner of the business can send the payment.') }}</p></div></div>
                @endif
            </div>
        </div>
    @endif

    {{-- what the plan gives, and what was paid before --}}
    @if ($plan && ! $forever)
        <div class="col-12 {{ $history->isNotEmpty() ? 'col-lg-6' : '' }}">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('What you get') }}</h5></div>
                <div class="card-body">
                    @if ($plan->description)
                        <p>{{ $plan->description }}</p>
                    @endif
                    <div class="row g-2">
                        @foreach ($includes as $line)
                            <div class="col-12 {{ $history->isNotEmpty() ? '' : 'col-md-6' }}"><div class="d-flex align-items-start gap-2"><x-icon name="circle-check" class="text-success mt-1" /><span>{{ $line }}</span></div></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($history->isNotEmpty())
        <div class="col-12 {{ $plan && ! $forever ? 'col-lg-6' : '' }}">
            <div class="card mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('Your payments') }}</h5></div>
                <div class="card-body">
                    <ul class="list-clean">
                        @foreach ($history as $row)
                            <li>
                                <span class="sc-icon icon-soft success flex-shrink-0" style="width:34px;height:34px"><x-icon name="check" /></span>
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
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    // "Copy" beside the account number
    var button = e.target.closest('[data-copy]');
    if (!button || !navigator.clipboard) return;
    navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function () {
        var label = button.querySelector('span') || button, text = label.textContent;
        label.textContent = button.getAttribute('data-copied');
        setTimeout(function () { label.textContent = text; }, 1500);
    });
});
document.addEventListener('change', function (e) {
    // show the chosen screenshot under the file box
    var input = e.target.closest('input[type="file"][data-preview]');
    if (!input) return;
    var image = document.querySelector(input.getAttribute('data-preview')), file = input.files && input.files[0];
    if (!image) return;
    if (file && file.type.indexOf('image/') === 0) { image.src = URL.createObjectURL(file); image.style.display = 'block'; } else { image.style.display = 'none'; }
});
</script>
@endpush
