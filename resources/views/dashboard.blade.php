@extends('layouts.app', ['title' => __('app.dashboard.title')])

@php
    $total = fn ($rows) => sums($rows->pluck('total', 'currency'));
    $cards = [
        [__('Late payments'), 'triangle-exclamation', 'danger', (string) $overdue->sum('n'), $total($overdue), route('dues.index', ['when' => 'overdue'])],
        [__('Due in the next 30 days'), 'bell', 'warning', (string) $soon->sum('n'), $total($soon), route('dues.index', ['when' => 'month'])],
        [__('Income this month'), 'arrow-trend-up', 'success', null, sums($income), route('money.index', ['type' => 'income'])],
        [__('Expenses this month'), 'receipt', 'purple', null, sums($expense), route('money.index', ['type' => 'expense'])],
        [__('People owe you'), 'hand-holding-dollar', 'teal', null, sums($owedToMe), route('people.index')],
        [__('You owe people'), 'handshake', 'orange', null, sums($iOwe), route('people.index')],
    ];
@endphp

@section('content')
<x-page-header :title="__('app.dashboard.empty_title', ['name' => auth()->user()->name])" icon="gauge-high" :subtitle="fmt_date(today_iso()).' · '.trans_choice('{1} 1 client|[0,*] :count clients', $clients, ['count' => $clients]).' · '.trans_choice('{1} 1 active contract|[0,*] :count active contracts', $contracts, ['count' => $contracts])">
    <a class="btn btn-primary" href="{{ route('contracts.create') }}"><x-icon name="plus" class="me-1" /> {{ __('New contract') }}</a>
</x-page-header>

@if ($clients === 0)
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="es-icon"><x-icon name="users" /></div>
                <h3>{{ __('Start with your first client') }}</h3>
                <p>{{ __('Add a client, then add what they pay for and when it is due. The system reminds you before every due date.') }}</p>
                <a class="btn btn-primary" href="{{ route('clients.create') }}"><x-icon name="user-plus" class="me-1" /> {{ __('New client') }}</a>
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $icon, $tone, $count, $amount, $url])
        <div class="col-6 col-xl-4">
            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span>
                        <span class="small fw-semibold text-muted">{{ $label }}</span>
                    </div>
                    @if ($count !== null)
                        <div class="fs-3 fw-bold lh-1 mb-1">{{ $count }}</div>
                    @endif
                    <div class="{{ $count === null ? 'fs-5 fw-bold' : 'small text-muted' }}" dir="ltr">{{ $amount }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Who pays next') }}</h5>
                <a class="small text-decoration-none" href="{{ route('dues.index') }}">{{ __('See all') }}</a>
            </div>
            <div class="card-body">
                @if ($next->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nothing is due. Contracts with a due date appear here.') }}</p>
                @else
                    <ul class="list-clean">
                        @foreach ($next as $contract)
                            <li>
                                <span class="avatar avatar-sm avatar-soft-primary flex-shrink-0">{{ \App\Models\User::initialsOf($contract->client?->name) }}</span>
                                <a href="{{ route('contracts.pay', ['id' => $contract->id]) }}" class="min-w-0 flex-grow-1 text-decoration-none text-reset">
                                    <span class="d-block fw-semibold text-truncate">{{ $contract->client?->name }}</span>
                                    <span class="d-block small text-muted text-truncate">{{ $contract->title }}</span>
                                </a>
                                <span class="text-end flex-shrink-0">
                                    <span class="d-block fw-semibold" dir="ltr">{{ money($contract->amount, $contract->currency) }}</span>
                                    <span class="d-block small">{{ \App\Http\Controllers\ContractController::dueCell($contract) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Latest income and expenses') }}</h5></div>
            <div class="card-body">
                @if ($recent->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nothing recorded yet.') }}</p>
                @else
                    <ul class="list-clean">
                        @foreach ($recent as $line)
                            <li>
                                <span class="sc-icon icon-soft {{ $line->type === 'income' ? 'success' : 'danger' }} flex-shrink-0" style="width:36px;height:36px"><x-icon :name="$line->type === 'income' ? 'arrow-down' : 'arrow-up'" /></span>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="d-block fw-semibold text-truncate">{{ $line->description ?: $line->category?->name }}</span>
                                    <span class="d-block small text-muted">{{ fmt_date($line->date) }} · {{ $line->category?->name }}</span>
                                </span>
                                <span class="fw-semibold flex-shrink-0 {{ $line->type === 'income' ? 'bal-due' : 'bal-owe' }}" dir="ltr">{{ $line->type === 'income' ? '+' : '−' }}{{ money($line->amount, $line->currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
