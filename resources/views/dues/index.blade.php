@extends('layouts.app', ['title' => __('Due & Renewals')])

@section('content')
<x-page-header :title="__('Due & Renewals')" icon="bell" :subtitle="__('Who should pay, how much, and when.')">
    <a class="btn btn-primary" href="{{ route('contracts.create') }}"><x-icon name="plus" class="me-1" /> {{ __('New contract') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-nowrap gap-2 mb-3 overflow-auto pb-1">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('dues.index', ['when' => $key] + ($term !== '' ? ['q' => $term] : [])) }}" class="btn btn-sm text-nowrap {{ $tab === $key ? 'btn-primary' : 'btn-light-secondary' }}">
                    {{ __($label) }} <span class="badge {{ $key === 'overdue' && $counts[$key] ? 'bg-danger' : 'bg-secondary-subtle text-secondary-emphasis' }} ms-1">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="when" value="{{ $tab }}">
            <div class="col">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search a client, a domain, a contract…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($totals)
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-primary-subtle text-primary-emphasis fw-semibold p-2">{{ __('Total') }}: <span dir="ltr">{{ sums($totals) }}</span></span>
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="circle-check" /></div>
                <h3>{{ $tab === 'overdue' ? __('Nobody is late') : __('Nothing is due here') }}</h3>
                <p>{{ __('Contracts appear here by their next due date.') }}</p>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('For') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $contract)
                        <tr>
                            <td>
                                <a class="fw-semibold text-decoration-none" href="{{ route('clients.show', ['id' => $contract->client_id]) }}">{{ $contract->client?->name }}</a>
                                @if ($contract->client?->company)<span class="d-block small text-muted">{{ $contract->client->company }}</span>@endif
                            </td>
                            <td>{{ $contract->title }}@if ($contract->reference)<span class="d-block small text-muted">{{ $contract->reference }}</span>@endif</td>
                            <td>{{ \App\Http\Controllers\ContractController::dueCell($contract) }}</td>
                            <td class="text-end fw-semibold text-nowrap" dir="ltr">{{ money($contract->amount, $contract->currency) }}</td>
                            <td class="text-end text-nowrap">
                                <div class="act-group">
                                    @if ($contract->client?->whatsapp())
                                        <a class="btn btn-sm btn-light-secondary" target="_blank" rel="noopener" href="https://wa.me/{{ $contract->client->whatsapp() }}?text={{ rawurlencode(__('Hello :name, a reminder that :title (:amount) is due on :date. Thank you.', ['name' => $contract->client->name, 'title' => $contract->title, 'amount' => money($contract->amount, $contract->currency), 'date' => fmt_date($contract->next_due_date)])) }}"><x-icon name="comment-dots" class="me-1" />{{ __('WhatsApp') }}</a>
                                    @endif
                                    <a class="btn btn-sm btn-soft-success" href="{{ route('contracts.pay', ['id' => $contract->id]) }}"><x-icon name="hand-holding-dollar" class="me-1" />{{ __('Receive') }}</a>
                                    <x-act kind="edit" :href="route('contracts.edit', ['id' => $contract->id])" />
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
