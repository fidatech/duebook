@extends('layouts.admin', ['title' => $account->name])

@php
    $kinds = \App\Http\Controllers\Admin\AccountController::TYPES;
@endphp

@section('content')
<x-page-header :title="$account->name" icon="building-columns" :subtitle="__($kinds[$account->type] ?? $account->type).' · '.$account->currency.($account->number ? ' · '.$account->number : '')" :crumbs="[[__('Financial accounts'), route('admin.accounts.index')], [$account->name]]">
    <a class="btn btn-outline-soft" href="{{ route('admin.accounts.edit', ['id' => $account->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach ([[__('This month'), $month, 'success'], [__('This year'), $year, 'primary'], [__('All time'), $all, 'purple']] as [$label, $totals, $tone])
        <div class="col-12 col-md-4">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon name="arrow-down" /></span><span class="small fw-semibold text-muted">{{ __('Received') }} · {{ $label }}</span></div>
                    <div class="fs-4 fw-bold" dir="ltr">{{ sums($totals) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Where the money came from') }}</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-6 col-md-auto"><input type="date" class="form-control" name="from" value="{{ $from }}" title="{{ __('From') }}" aria-label="{{ __('From') }}" onchange="this.form.submit()"></div>
                    <div class="col-6 col-md-auto"><input type="date" class="form-control" name="to" value="{{ $to }}" title="{{ __('To') }}" aria-label="{{ __('To') }}" onchange="this.form.submit()"></div>
                    @if ($from || $to)
                        <div class="col-auto"><a class="btn btn-light-secondary" href="{{ route('admin.accounts.show', ['id' => $account->id]) }}">{{ __('All dates') }}</a></div>
                        <div class="col-auto d-flex align-items-center"><span class="badge bg-primary-subtle text-primary-emphasis fw-semibold p-2">{{ __('Total') }}: <span dir="ltr">{{ sums($filtered) }}</span></span></div>
                    @endif
                </form>

                @if ($rows->isEmpty())
                    <div class="empty-state">
                        <div class="es-icon"><x-icon name="building-columns" /></div>
                        <h3>{{ $from || $to ? __('Nothing found') : __('No money received here yet') }}</h3>
                        <p>{{ __('A payment appears here when you confirm it and choose this account under "Received in".') }}</p>
                    </div>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('From') }}</th><th>{{ __('Paid on') }}</th><th>{{ __('For') }}</th><th>{{ __('Transfer number') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.tenants.edit', ['id' => $row->tenant_id]) }}">{{ $row->tenant?->name }}</a><span class="d-block small text-muted">{{ $row->tenant?->email }}</span></td>
                                    <td class="text-nowrap">{{ fmt_date($row->paid_on) }}</td>
                                    <td>{{ $row->plan?->name }}<span class="d-block small text-muted">{{ fmt_date($row->starts_on) }} – {{ $row->ends_on ? fmt_date($row->ends_on) : __('no end') }}</span></td>
                                    <td dir="ltr">{{ $row->reference ?: '—' }}</td>
                                    <td class="text-end fw-semibold text-nowrap bal-due" dir="ltr">+{{ money($row->amount, $row->currency) }}</td>
                                    <td class="text-end text-nowrap">
                                        <div class="act-group">
                                            @if ($row->receipt_path)
                                                <a class="btn btn-sm btn-light-secondary" target="_blank" rel="noopener" href="{{ route('admin.subscriptions.receipt', ['id' => $row->id]) }}"><x-icon name="paperclip" class="me-1" />{{ __('Screenshot') }}</a>
                                            @endif
                                            <x-act kind="edit" :href="route('admin.subscriptions.edit', ['id' => $row->id])" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $rows->links('partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Who paid the most') }}</h5></div>
            <div class="card-body">
                @if ($payers->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nobody yet.') }}</p>
                @else
                    <ul class="list-clean">
                        @foreach ($payers as $payer)
                            <li>
                                <span class="avatar avatar-sm avatar-soft-primary flex-shrink-0">{{ \App\Models\User::initialsOf($payer->tenant?->name) }}</span>
                                <a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ route('admin.tenants.edit', ['id' => $payer->tenant_id]) }}">
                                    <span class="d-block fw-semibold text-truncate">{{ $payer->tenant?->name }}</span>
                                    <span class="d-block small text-muted">{{ trans_choice('{1} 1 payment|[2,*] :count payments', $payer->payments, ['count' => $payer->payments]) }}</span>
                                </a>
                                <span class="fw-semibold text-nowrap" dir="ltr">{{ money($payer->total, $payer->currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($account->details)
                    <div class="border-top mt-3 pt-3 small text-muted" style="white-space:pre-line" dir="auto">{{ $account->details }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
