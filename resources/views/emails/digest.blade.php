@extends('emails.layout', ['heading' => 'Payments for '.fmt_date($today), 'sender' => $tenant->name])

@php
    $th = 'text-align:left;padding:8px 6px;font-size:12px;color:#7b8498;border-bottom:1px solid #e6eaf1;';
    $td = 'padding:9px 6px;border-bottom:1px solid #eef1f6;font-size:14px;vertical-align:top;';
    $num = $td.'text-align:right;white-space:nowrap;';
    $h2 = 'margin:22px 0 6px;font-size:15px;color:#1f2637;';
    $total = fn ($rows) => sums($rows->groupBy('currency')->map(fn ($group) => $group->sum('amount')));
@endphp

@section('body')
@if ($coming->isNotEmpty())
    <h2 style="{{ $h2 }}">Coming up ({{ $coming->count() }}) · {{ $total($coming) }}</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><th style="{{ $th }}">Client</th><th style="{{ $th }}">Due</th><th style="{{ $th }}text-align:right;">Amount</th></tr>
        @foreach ($coming as $contract)
            @php
                $days = $contract->daysLeft($today);
            @endphp
            <tr>
                <td style="{{ $td }}"><strong>{{ $contract->client?->name }}</strong><br><span style="color:#7b8498;font-size:13px;">{{ $contract->title }}@if ($contract->client?->phone) · {{ $contract->client->phone }}@endif</span></td>
                <td style="{{ $td }}white-space:nowrap;">{{ fmt_date($contract->next_due_date) }}<br><span style="color:#b7791f;font-size:13px;">{{ $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in '.$days.' days') }}</span></td>
                <td style="{{ $num }}">{{ money($contract->amount, $contract->currency) }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if ($late->isNotEmpty())
    <h2 style="{{ $h2 }}color:#c53030;">Late ({{ $late->count() }}) · {{ $total($late) }}</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><th style="{{ $th }}">Client</th><th style="{{ $th }}">Was due</th><th style="{{ $th }}text-align:right;">Amount</th></tr>
        @foreach ($late->take(50) as $contract)
            <tr>
                <td style="{{ $td }}"><strong>{{ $contract->client?->name }}</strong><br><span style="color:#7b8498;font-size:13px;">{{ $contract->title }}@if ($contract->client?->phone) · {{ $contract->client->phone }}@endif</span></td>
                <td style="{{ $td }}white-space:nowrap;">{{ fmt_date($contract->next_due_date) }}<br><span style="color:#c53030;font-size:13px;">{{ -$contract->daysLeft($today) }} days late</span></td>
                <td style="{{ $num }}">{{ money($contract->amount, $contract->currency) }}</td>
            </tr>
        @endforeach
    </table>
    @if ($late->count() > 50)
        <p style="font-size:13px;color:#7b8498;">And {{ $late->count() - 50 }} more. Open the system to see them all.</p>
    @endif
@endif

@if ($entries->isNotEmpty())
    <h2 style="{{ $h2 }}">Money with people ({{ $entries->count() }})</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><th style="{{ $th }}">Person</th><th style="{{ $th }}">Date</th><th style="{{ $th }}text-align:right;">Amount</th></tr>
        @foreach ($entries as $entry)
            <tr>
                <td style="{{ $td }}"><strong>{{ $entry->person?->name }}</strong><br><span style="color:#7b8498;font-size:13px;">{{ $entry->direction === 'gave' ? 'should pay you back' : 'you should pay back' }}@if ($entry->note) · {{ $entry->note }}@endif</span></td>
                <td style="{{ $td }}white-space:nowrap;">{{ fmt_date($entry->due_date) }}</td>
                <td style="{{ $num }}">{{ money($entry->amount, $entry->currency) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<p style="margin:26px 0 0;"><a href="{{ route('dues.index') }}" style="display:inline-block;background:#3a7bf0;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">Open Due &amp; Renewals</a></p>
@endsection

@section('footer')
You get this email because reminders are switched on for {{ $tenant->name }} in {{ config('app.name') }}. The days and the hour can be changed in Settings.
@endsection
