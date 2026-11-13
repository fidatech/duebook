@extends('layouts.admin', ['title' => __('Dashboard')])

@php
    $cards = [
        [__('Businesses'), 'building', 'primary', $tenants],
        [__('Paying'), 'circle-check', 'success', $active],
        [__('On trial'), 'hourglass-half', 'warning', $trial],
        [__('Suspended'), 'ban', 'danger', $suspended],
    ];
@endphp

@section('content')
<x-page-header :title="__('Dashboard')" icon="gauge-high" :subtitle="__('Received this year').': '.sums($revenue)">
    <a class="btn btn-primary" href="{{ route('admin.tenants.create') }}"><x-icon name="plus" class="me-1" /> {{ __('New business') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $icon, $tone, $count])
        <div class="col-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="sc-icon icon-soft {{ $tone }} flex-shrink-0" style="width:38px;height:38px"><x-icon :name="$icon" /></span><span class="small fw-semibold text-muted">{{ $label }}</span></div>
                    <div class="fs-3 fw-bold lh-1">{{ number_format($count) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    @foreach ([[__('Ending in the next 30 days'), $expiring, 'warning'], [__('Ended: trial or subscription'), $expired, 'danger'], [__('Newest businesses'), $latest, 'primary']] as [$heading, $rows, $tone])
        <div class="col-12 col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header"><h5 class="card-title mb-0">{{ $heading }}</h5></div>
                <div class="card-body">
                    @if ($rows->isEmpty())
                        <p class="text-muted mb-0">{{ __('None.') }}</p>
                    @else
                        <ul class="list-clean">
                            @foreach ($rows as $tenant)
                                <li>
                                    <span class="avatar avatar-sm avatar-soft-{{ $tone }} flex-shrink-0">{{ \App\Models\User::initialsOf($tenant->name) }}</span>
                                    <a class="min-w-0 flex-grow-1 text-decoration-none text-reset" href="{{ route('admin.tenants.edit', ['id' => $tenant->id]) }}">
                                        <span class="d-block fw-semibold text-truncate">{{ $tenant->name }}</span>
                                        <span class="d-block small text-muted">{{ $tenant->plan?->name ?: __('No plan') }} · {{ __($tenant->statusLabel()) }}</span>
                                    </a>
                                    <span class="small text-muted text-nowrap">{{ $tenant->endsOn() ? fmt_date($tenant->endsOn()) : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
