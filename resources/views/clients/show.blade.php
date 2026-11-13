@extends('layouts.app', ['title' => $client->name])

@section('content')
<x-page-header :title="$client->name" icon="user" :subtitle="$client->company" :crumbs="[[__('Clients'), route('clients.index')], [$client->name]]">
    <a class="btn btn-outline-soft" href="{{ route('clients.edit', ['id' => $client->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
    <a class="btn btn-primary" href="{{ route('contracts.create', ['client_id' => $client->id]) }}"><x-icon name="plus" class="me-1" /> {{ __('New contract') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card h-100 mb-0">
            <div class="card-body">
                <ul class="list-clean">
                    <li><span class="text-muted small flex-shrink-0" style="width:70px">{{ __('Phone') }}</span><span class="min-w-0 flex-grow-1" dir="ltr">@if ($client->phone)<a class="text-decoration-none" href="tel:{{ $client->phone }}">{{ $client->phone }}</a>@else — @endif</span>
                        @if ($client->whatsapp())<a class="btn btn-sm btn-soft-success" target="_blank" rel="noopener" href="https://wa.me/{{ $client->whatsapp() }}"><x-icon name="comment-dots" /></a>@endif
                    </li>
                    <li><span class="text-muted small flex-shrink-0" style="width:70px">{{ __('Email') }}</span><span class="min-w-0 text-truncate">@if ($client->email)<a class="text-decoration-none" href="mailto:{{ $client->email }}">{{ $client->email }}</a>@else — @endif</span></li>
                    <li><span class="text-muted small flex-shrink-0" style="width:70px">{{ __('Address') }}</span><span class="min-w-0">{{ $client->address ?: '—' }}</span></li>
                    <li><span class="text-muted small flex-shrink-0" style="width:70px">{{ __('Paid so far') }}</span><span class="fw-semibold" dir="ltr">{{ sums($paid) }}</span></li>
                </ul>
                @if ($client->notes)
                    <p class="small text-muted mt-3 mb-0" style="white-space:pre-line">{{ $client->notes }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Contracts') }}</h5></div>
            <div class="card-body">
                @if ($contracts->isEmpty())
                    <p class="text-muted mb-0">{{ __('No contracts yet. Press "New contract" to add what this client pays for.') }}</p>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('Contract') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Next due date') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($contracts as $contract)
                                <tr>
                                    <td><span class="fw-semibold">{{ $contract->title }}</span>@if ($contract->reference)<span class="d-block small text-muted">{{ $contract->reference }}</span>@endif</td>
                                    <td class="text-nowrap" dir="ltr">{{ money($contract->amount, $contract->currency) }}</td>
                                    <td>{{ $contract->cycleLabel() }}</td>
                                    <td>{{ \App\Http\Controllers\ContractController::dueCell($contract) }}</td>
                                    <td class="text-end text-nowrap">
                                        <div class="act-group">
                                            @if ($contract->file_path)
                                                <a class="btn btn-sm btn-light-secondary" target="_blank" rel="noopener" href="{{ route('contracts.file', ['id' => $contract->id]) }}"><x-icon name="paperclip" class="me-1" />{{ __('File') }}</a>
                                            @endif
                                            @if ($contract->status === 'active' && $contract->next_due_date)
                                                <a class="btn btn-sm btn-soft-success" href="{{ route('contracts.pay', ['id' => $contract->id]) }}"><x-icon name="hand-holding-dollar" class="me-1" />{{ __('Receive') }}</a>
                                            @endif
                                            <x-act kind="edit" :href="route('contracts.edit', ['id' => $contract->id])" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Payments received') }}</h5></div>
            <div class="card-body">
                @if ($payments->isEmpty())
                    <p class="text-muted mb-0">{{ __('No payments yet.') }}</p>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('For') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="text-nowrap">{{ fmt_date($payment->paid_on) }}</td>
                                    <td>{{ $payment->contract?->title }}@if ($payment->period_due)<span class="d-block small text-muted">{{ __('was due :date', ['date' => fmt_date($payment->period_due)]) }}</span>@endif</td>
                                    <td>{{ $payment->note }}</td>
                                    <td class="text-end fw-semibold text-nowrap" dir="ltr">{{ money($payment->amount, $payment->currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">{{ __('To correct or delete a payment, open it under Income.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
