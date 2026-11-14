@extends('layouts.site')

@section('content')
<section class="band">
    <div class="wrap inset">
        <h1>Contact</h1>
        <p class="intro" style="margin-top:18px">A question before you start, or a problem with your account: write it here and we answer by email.</p>

        @if (session('sent'))
            <p class="notice" role="status">Your message is sent. We will answer by email.</p>
        @endif

        <form class="form" method="POST" action="{{ route('site.contact.send') }}">
            @csrf
            <label>Your name
                <input type="text" name="name" value="{{ old('name') }}" maxlength="150" autocomplete="name" required>
                @error('name')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label>Your email address
                <input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label>Your message
                <textarea name="message" rows="6" minlength="10" maxlength="3000" required>{{ old('message') }}</textarea>
                @error('message')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="trap" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <div><button type="submit" class="btn btn-main btn-big">Send the message</button></div>
        </form>

        @if (platform('support_email') || platform('support_whatsapp'))
            <p style="margin:28px 0 0">
                @if (platform('support_email'))Email: <a href="mailto:{{ platform('support_email') }}">{{ platform('support_email') }}</a>@endif
                @if (platform('support_email') && platform('support_whatsapp'))<br>@endif
                @if (platform('support_whatsapp'))WhatsApp or phone: {{ platform('support_whatsapp') }}@endif
            </p>
        @endif
    </div>
</section>
@endsection
