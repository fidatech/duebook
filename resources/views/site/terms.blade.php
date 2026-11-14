@extends('layouts.site')

@section('content')
<section class="band">
    <div class="wrap inset prose">
        <h1>Terms of use</h1>
        <p style="margin-top:18px">By using {{ config('app.name') }} you agree to these rules. {{ config('app.name') }} is made and run by Fida Technologies.</p>

        <h2>Your account</h2>
        <ul>
            <li>Keep your password to yourself. What is done in your account is your responsibility, including what the people you invite do.</li>
            <li>Use the system for your own lawful business. Do not use it to send messages people did not agree to receive.</li>
        </ul>

        <h2>Trial and payment</h2>
        <ul>
            <li>A new account can be used free for {{ $trial }} days.</li>
            <li>After the trial you pay for a plan in advance, for the period shown on the pricing page.</li>
            <li>Your subscription starts when we have checked your payment.</li>
        </ul>

        <h2>Refunds and changing your plan</h2>
        <ul>
            <li>Payments are not refunded.</li>
            <li>You can move to a higher plan at any time. It is paid in full and starts that day; the days left on the earlier plan are not paid back.</li>
            <li>Any other change of plan, or a renewal, is made near the end of the paid period and starts when that period ends.</li>
        </ul>

        <h2>When a period ends</h2>
        <p>If you do not renew, your account is locked. Your records are kept, and you continue where you stopped when you pay again.</p>

        <h2>Your records</h2>
        <p>What you record is yours. You are responsible for its accuracy and for having the right to keep your clients' details. {{ config('app.name') }} helps you remember what is due; it does not collect money for you and it is not accounting, tax or legal advice.</p>

        <h2>The service</h2>
        <p>We work to keep the system available and your records safe, but it is provided as it is, without a promise that it will never be interrupted. Reminders are sent by email and can be delayed or blocked by email providers, so check your due list as well. To the extent the law allows, we are not liable for losses that follow from using or not being able to use the system.</p>

        <h2>Changes</h2>
        <p>We may change these rules or the prices for future periods. The version on this page is the one in force.</p>

        <h2>Contact</h2>
        <p>Write to us on the <a href="{{ route('site.contact') }}">contact page</a>.</p>
    </div>
</section>
@endsection
