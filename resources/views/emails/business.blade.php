@extends('emails.layout', ['heading' => $heading, 'sender' => config('app.name')])

@section('body')
<p style="margin:0 0 14px;">Dear {{ $tenant->owner()?->name ?: $tenant->name }},</p>
@foreach ($lines as $line)
    <p style="margin:0 0 14px;">{{ $line }}</p>
@endforeach
<p style="margin:20px 0 0;"><a href="{{ $url }}" style="display:inline-block;background:#3a7bf0;color:#ffffff;text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;">{{ $button }}</a></p>
@endsection

@section('footer')
You get this email because you are the owner of {{ $tenant->name }} in {{ config('app.name') }}.@if (platform('support_email')) Questions: {{ platform('support_email') }}@endif
@endsection
