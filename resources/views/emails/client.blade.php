@extends('emails.layout', ['heading' => $contract->title, 'sender' => $tenant->name])

@section('body')
<p style="margin:0 0 14px;">Dear {{ $contract->client?->name }},</p>
<p style="margin:0 0 14px;">
    This is a friendly reminder that <strong>{{ $contract->title }}</strong>@if ($contract->reference) ({{ $contract->reference }})@endif
    @if ($daysBefore === 0)
        is due <strong>today</strong>.
    @else
        is due on <strong>{{ fmt_date($contract->next_due_date) }}</strong>, in {{ $daysBefore }} {{ $daysBefore === 1 ? 'day' : 'days' }}.
    @endif
</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;background:#f6f8fc;border-radius:10px;">
    <tr><td style="padding:14px 18px;font-size:14px;color:#7b8498;">Amount</td><td style="padding:14px 18px;font-size:18px;font-weight:700;color:#1f2637;">{{ money($contract->amount, $contract->currency) }}</td></tr>
</table>
<p style="margin:0 0 14px;">To keep it running without a break, please arrange the payment before the due date. If you have already paid, thank you, and please ignore this message.</p>
<p style="margin:0;">Regards,<br><strong>{{ $tenant->name }}</strong>@if ($tenant->phone)<br>{{ $tenant->phone }}@endif</p>
@endsection

@section('footer')
Sent by {{ $tenant->name }}. Reply to this email to reach them.
@endsection
