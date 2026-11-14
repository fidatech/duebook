@extends('layouts.site')

@section('content')
<section class="band">
    <div class="wrap inset prose">
        <h1>Privacy</h1>
        <p style="margin-top:18px">This page says what {{ config('app.name') }} keeps about you and what it does with it. It is written to be read, not to hide things.</p>

        <h2>What we keep</h2>
        <ul>
            <li><strong>Your account:</strong> your name, email address, the name of your business, and a password stored in a form that cannot be read back.</li>
            <li><strong>What you record:</strong> your clients, contracts, payments, loans, income, expenses and the files you attach, such as a scan of a contract.</li>
            <li><strong>Payments to us:</strong> the plan you paid for, the date, the amount, the transfer number and the screenshot you upload.</li>
            <li><strong>Sign-in:</strong> a cookie that keeps you signed in. The website itself uses no advertising or tracking cookies.</li>
        </ul>

        <h2>What we do with it</h2>
        <ul>
            <li>We show your records to you and to the people you invite to your account. Every business has its own separate records.</li>
            <li>We send the emails you asked for: reminders to you, and to your clients only for the contracts where you switched that on.</li>
            <li>We write to you about your own account: trial, subscription and payments.</li>
            <li>We do not sell your records and we do not use them for advertising.</li>
        </ul>

        <h2>Your clients' details</h2>
        <p>The names, phone numbers and email addresses of your clients are yours. You are responsible for having the right to keep them and to email them. We only use them to do what you ask the system to do.</p>

        <h2>How long we keep it</h2>
        <p>While your account exists. If you stop paying, your account is locked and your records are kept so you can continue later. To have your account and its records deleted, write to us from the email address of the owner of the account.</p>

        <h2>Questions</h2>
        <p>Write to us on the <a href="{{ route('site.contact') }}">contact page</a>.</p>
    </div>
</section>
@endsection
