<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings of the business: name, currencies, time zone and reminders. Only the owner opens this screen. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $tenant = tenant();

        return view('settings.edit', [
            'tenant' => $tenant,
            'plan' => $tenant->plan,
            'timezones' => timezone_identifiers_list(),
            'subscriptions' => $tenant->subscriptions()->with('plan')->orderByDesc('starts_on')->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'currencies' => ['nullable', 'string', 'max:200'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'remind_days' => ['required', 'string', 'max:100', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'remind_hour' => ['required', 'integer', 'between:0,23'],
        ], ['remind_days.regex' => __('Write the days as numbers with commas between them, for example 30, 7, 1, 0.')]);

        $data['currency'] = strtoupper($data['currency']);

        // "usd, afn eur" becomes ['USD', 'AFN', 'EUR']; anything that is not a 3-letter code is left out
        $codes = preg_split('/[\s,;]+/', strtoupper((string) ($data['currencies'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $codes = array_filter($codes, fn (string $code) => preg_match('/^[A-Z]{3}$/', $code) === 1);
        $data['currencies'] = array_values(array_unique(array_merge([$data['currency']], $codes)));

        $days = array_map('intval', preg_split('/\s*,\s*/', trim($data['remind_days'])));
        rsort($days);
        $data['remind_days'] = array_values(array_unique($days));

        tenant()->update($data);

        return back()->with('success', __('Settings saved.'));
    }
}
