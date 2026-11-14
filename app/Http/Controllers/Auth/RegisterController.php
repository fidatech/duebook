<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Platform;
use App\Support\Tenants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** A new business signs up by itself and starts on the free plan, or on a free trial when no free plan is offered. */
class RegisterController extends Controller
{
    public function show(): View
    {
        abort_unless(Platform::bool('registration'), 404);

        return view('auth.register', ['timezones' => timezone_identifiers_list()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Platform::bool('registration'), 404);

        $request->merge(['email' => strtolower(trim((string) $request->input('email'))), 'currency' => strtoupper(trim((string) $request->input('currency')))]);
        $data = $request->validate([
            'business' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'currency' => ['required', 'alpha', 'size:3'],
            'timezone' => ['required', 'timezone'],
        ]);

        $tenant = Tenants::create([
            'name' => $data['business'],
            'owner_name' => $data['name'],
            'owner_email' => $data['email'],
            'owner_password' => $data['password'],
            'currency' => $data['currency'],
            'timezone' => $data['timezone'],
        ]);

        Auth::guard('web')->login($tenant->owner());
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', __('Welcome! Your account is ready.'));
    }
}
