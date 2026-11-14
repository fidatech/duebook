@extends('layouts.admin', ['title' => __('Businesses')])

@php
    $badge = ['Active' => 'bg-success-subtle text-success-emphasis', 'Trial' => 'bg-warning-subtle text-warning-emphasis', 'Expired' => 'bg-danger-subtle text-danger-emphasis', 'Suspended' => 'bg-secondary-subtle text-secondary-emphasis'];
@endphp

@section('content')
<x-page-header :title="__('Businesses')" icon="building">
    <a class="btn btn-primary" href="{{ route('admin.tenants.create') }}"><x-icon name="plus" class="me-1" /> {{ __('New business') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('Status') }}</option>
                    @foreach (['trial' => 'Trial', 'active' => 'Active', 'suspended' => 'Suspended', 'ended' => 'Ended or suspended'] as $value => $text)
                        <option value="{{ $value }}" @selected($status === $value)>{{ __($text) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="building" /></div>
                <h3>{{ __('Nothing found') }}</h3>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Business') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Until') }}</th><th>{{ __('Users') }}</th><th>{{ __('Since') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $tenant)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.tenants.edit', ['id' => $tenant->id]) }}">{{ $tenant->name }}</a><span class="d-block small text-muted">{{ $tenant->email }}</span></td>
                            <td>{{ $tenant->plan?->name ?: '—' }}</td>
                            <td><span class="badge {{ $badge[$tenant->statusLabel()] }}">{{ __($tenant->statusLabel()) }}</span></td>
                            <td class="text-nowrap">{{ $tenant->endsOn() ? fmt_date($tenant->endsOn()) : __('No end date') }}</td>
                            <td>{{ $tenant->users_count }}</td>
                            <td class="text-nowrap">{{ fmt_date($tenant->created_at) }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    <a class="btn btn-sm btn-soft-success" href="{{ route('admin.subscriptions.create', ['tenant' => $tenant->id]) }}"><x-icon name="receipt" class="me-1" />{{ __('Subscription') }}</a>
                                    <x-act kind="edit" :href="route('admin.tenants.edit', ['id' => $tenant->id])" />
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
@endsection
