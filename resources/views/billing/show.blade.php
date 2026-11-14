@extends('layouts.app', ['title' => __('Subscription')])

@php
    $usable = $tenant->isUsable();
    $trial = $tenant->status === 'trial';
    $forever = $tenant->status === 'active' && $tenant->endsOn() === null;
    $choosing = $plans->isNotEmpty() && ! $forever && $tenant->status !== 'suspended';
    // a higher plan can be taken at any time; anything else only when choosing is open
    $immediate = $plan && ! empty($upgrades[$plan->id]);
    $canPay = $choosing && ($open || $immediate) && $plan && ! $pending && ! ($tooSmall[$plan->id] ?? null);
    // a paid period is still running: what is bought now starts when it ends
    $nextPeriod = $open && ! $trial && $usable;
    $kinds = \App\Http\Controllers\Admin\AccountController::TYPES;
    $per = ['monthly' => __('a month'), 'yearly' => __('a year'), 'lifetime' => __('once')];
    // "199 USD", without useless zeros
    $amount = fn ($p) => rtrim(rtrim(number_format((float) $p->price, 2), '0'), '.').' '.$p->currency;
    $price = $plan ? $amount($plan) : '';
    $note = platform('pay_note');
    // what a plan gives, as short lines for its card
    $features = fn ($p) => [
        $p->max_clients ? __('Up to :count clients', ['count' => number_format($p->max_clients)]) : __('Clients and contracts without limit'),
        $p->max_users ? trans_choice('{1} 1 user|[2,*] Up to :count users', $p->max_users, ['count' => $p->max_users]) : __('Users without limit'),
        __('Renewal reminders by email, for you and your clients'),
        __('Loans, income, expenses and reports'),
    ];
@endphp

@section('content')
<x-page-header :title="__('Subscription')" icon="credit-card" />

{{-- where the business stands: status, plan, the running period and the days left --}}
@include('partials.plan-status', ['tenant' => $tenant, 'link' => false])

@if ($pending)
    <div class="card h-auto">
        <div class="card-body">
            <div class="empty-state">
                <div class="es-icon"><x-icon name="hourglass-half" /></div>
                <h3>{{ __('Your payment is being checked') }}</h3>
                <p>{{ __('You told us on :date that you paid :amount for :plan. As soon as we see the money, your subscription is switched on until :until.', ['date' => fmt_date($pending->created_at), 'plan' => $pending->plan?->name ?: __('your plan'), 'amount' => money($pending->amount, $pending->currency), 'until' => $pending->ends_on ? fmt_date($pending->ends_on) : __('no end')]) }}</p>
            </div>
        </div>
    </div>
@endif

@if ($rejected)
    <div class="alert alert-danger d-flex align-items-start gap-3">
        <x-icon name="circle-xmark" class="fs-4 mt-1" />
        <div class="min-w-0">
            <div class="fw-bold">{{ __('Your payment was not accepted') }}</div>
            <div>{{ $rejected->rejectText() }}@if ($rejected->reject_note) {{ $rejected->reject_note }}@endif</div>
            <div class="small mt-1">{{ $rejected->rejectAdvice() }}</div>
            <div class="small opacity-75 mt-1">{{ __(':amount, sent on :date', ['amount' => money($rejected->amount, $rejected->currency), 'date' => fmt_date($rejected->sent_on ?? $rejected->created_at)]) }}</div>
        </div>
    </div>
@endif

{{-- all plans: the current one is marked, another one can be chosen --}}
@if ($choosing)
    <div class="d-flex justify-content-between align-items-baseline mt-4 mb-2">
        <h5 class="mb-0">{{ $pending ? __('Plans') : ($plans->where('is_active', true)->count() > 1 ? __('Choose your plan') : __('The plan')) }}</h5>
        @if ($note)<span class="small text-muted">{{ __('Prices') }} {{ __($note) }}</span>@endif
    </div>
    <div class="row g-3 mb-3">
        @foreach ($plans as $option)
            @php
                $isCurrent = $current && $current->id === $option->id;
                $isChosen = ! $pending && $plan && $plan->id === $option->id;
                $problem = $tooSmall[$option->id] ?? null;
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 mb-0 {{ $isChosen ? 'border-primary' : '' }}" @if ($isChosen) style="border-width:2px" @endif>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="fw-bold fs-5">{{ $option->name }}</span>
                            <span class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                                @if ($isCurrent)<span class="badge bg-success-subtle text-success-emphasis">{{ __('Your current plan') }}</span>@endif
                                @if (! $isCurrent && ! empty($upgrades[$option->id]))<span class="badge bg-primary-subtle text-primary-emphasis">{{ __('Higher plan') }}</span>@endif
                                @if ($isChosen && ! $isCurrent)<span class="badge bg-primary">{{ __('Selected') }}</span>@endif
                            </span>
                        </div>
                        <div class="lh-1 mt-2"><span class="fw-bold" style="font-size:30px" dir="ltr">{{ $amount($option) }}</span> <span class="text-muted small">/ {{ $per[$option->interval] ?? $option->interval }}</span></div>
                        @if ($isCurrent && $tenant->endsOn())
                            <div class="small mt-2 {{ $usable ? 'text-muted' : 'text-danger' }}">
                                {{ ! $usable ? __('Ended on :date', ['date' => fmt_date($tenant->endsOn())]) : ($trial ? __('Free trial until :date', ['date' => fmt_date($tenant->endsOn())]) : __('Paid until :date', ['date' => fmt_date($tenant->endsOn())])) }}
                            </div>
                        @endif
                        @if ($option->description)
                            <p class="small text-muted mt-2 mb-0">{{ $option->description }}</p>
                        @endif
                        <div class="d-flex flex-column gap-1 mt-3 mb-3">
                            @foreach ($features($option) as $line)
                                <div class="d-flex align-items-start gap-2 small"><x-icon name="circle-check" class="text-success mt-1" /><span>{{ $line }}</span></div>
                            @endforeach
                        </div>
                        <div class="mt-auto">
                            @if (! $option->is_active)
                                <div class="small text-muted text-center">{{ __('This plan is no longer offered. Choose another one to renew.') }}</div>
                            @elseif ($problem)
                                <div class="small text-danger text-center">{{ $problem }}</div>
                            @elseif (! $open && empty($upgrades[$option->id]))
                                <div class="small text-muted text-center">{{ $isCurrent ? __('You can renew from :date.', ['date' => fmt_date($opensOn)]) : __('You can change to this plan from :date.', ['date' => fmt_date($opensOn)]) }}</div>
                            @elseif ($pending)
                                @if ($pending->plan_id === $option->id)<div class="small text-muted text-center">{{ __('Your payment for this plan is being checked.') }}</div>@endif
                            @elseif ($isChosen && ! empty($upgrades[$option->id]))
                                <a class="btn btn-primary w-100" href="#pay"><x-icon name="arrow-up" class="me-1" /> {{ __('Upgrade: pay below') }}</a>
                            @elseif (! empty($upgrades[$option->id]))
                                <a class="btn btn-outline-primary w-100" href="{{ route('billing.show', ['plan' => $option->id]) }}#pay"><x-icon name="arrow-up" class="me-1" /> {{ __('Upgrade now') }}</a>
                            @elseif ($isChosen)
                                <a class="btn btn-primary w-100" href="#pay"><x-icon name="check" class="me-1" /> {{ __('Selected: pay below') }}</a>
                            @else
                                <a class="btn btn-outline-primary w-100" href="{{ route('billing.show', ['plan' => $option->id]) }}#pay">{{ $isCurrent ? ($nextPeriod ? __('Renew this plan') : __('Choose this plan')) : ($nextPeriod ? __('Change to this plan') : __('Choose this plan')) }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($choosing && ! $pending)
    <div class="alert {{ $open || $immediate ? 'alert-info' : 'alert-secondary' }} d-flex align-items-start gap-2 py-2">
        <x-icon name="circle-info" class="mt-1" />
        <div class="small">
            @if ($immediate)
                {{ __('Upgrade to :plan: it starts today and runs for a full period. The days left on your current plan are not paid back.', ['plan' => $plan->name]) }}
            @elseif (! $open)
                {{ __('Your plan is paid until :date. From :from you can renew it or choose another plan for the next period.', ['date' => fmt_date($tenant->endsOn()), 'from' => fmt_date($opensOn)]) }}
                @if (array_filter($upgrades)){{ __('You can upgrade to a higher plan at any time.') }}@endif
            @elseif ($nextPeriod)
                {{ __('Your current plan stays as it is until :date. What you choose now starts the day after. A higher plan starts today.', ['date' => fmt_date($tenant->endsOn())]) }}
            @else
                {{ __('Choose a plan, pay, and send the screenshot.') }}
            @endif
            <strong>{{ __('Payments are not refunded.') }}</strong>
        </div>
    </div>
@endif

<div class="row g-3" id="pay">
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
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
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
                                <span class="small text-muted">{{ __('Plan') }}</span>
                                <span class="fw-semibold text-end">{{ $plan->name }} · <span dir="ltr">{{ $price }}</span></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline gap-3 mt-2">
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

    {{-- what was paid before --}}
    @if ($history->isNotEmpty())
        <div class="col-12">
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
                                <span class="text-end flex-shrink-0">
                                    <span class="d-block fw-semibold text-nowrap" dir="ltr">{{ money($row->amount, $row->currency) }}</span>
                                    @if ($row->receipt_no)<a class="small text-decoration-none" target="_blank" rel="noopener" href="{{ route('billing.receipt', ['id' => $row->id]) }}"><x-icon name="print" class="me-1" />{{ $row->receiptNumber() }}</a>@endif
                                </span>
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
