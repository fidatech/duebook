@extends('layouts.auth', ['title' => __('Create an account')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="user-plus" /></div>
    <h2>{{ __('Create an account') }}</h2>
    <p class="ac-sub">{{ \App\Models\Plan::free() ? __('Free to start. No card needed.') : __('Free for :days days. No card needed.', ['days' => (int) platform('trial_days')]) }}</p>

    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="business">{{ __('Name of your business') }}</label>
            <input type="text" id="business" name="business" value="{{ old('business') }}" class="form-control @error('business') is-invalid @enderror" maxlength="150" required autofocus>
            @error('business')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">{{ __('Your name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="150" autocomplete="name" required>
            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-5">
                <label class="form-label" for="currency">{{ __('Main currency') }}</label>
                <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required data-plain>
                    @foreach (config('duebook.currencies') as $code)
                        <option value="{{ $code }}" @selected(old('currency', 'USD') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
                @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-7">
                <label class="form-label" for="timezone">{{ __('Time zone') }}</label>
                <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror" required data-plain>
                    @foreach ($timezones as $zone)
                        <option value="{{ $zone }}" @selected(old('timezone', 'Asia/Kabul') === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
                @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('app.auth.password') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" required><button type="button" class="field-eye" data-toggle-password aria-label="{{ __('app.auth.show_password') }}"><x-icon name="eye" set="regular" /></button></div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">{{ __('Password again') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" required></div>
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('Create my account') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    <p class="text-center small text-muted mt-4 mb-0">{{ __('Already have an account?') }} <a class="text-decoration-none fw-semibold" href="{{ route('login') }}">{{ __('app.auth.submit') }}</a></p>
</div>
@endsection
