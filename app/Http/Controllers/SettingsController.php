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
            'picked' => ['nullable', 'array'],
            'picked.*' => ['string', 'alpha', 'size:3'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            // the days are ticked on the page (days[]), with room for others typed by hand (remind_days)
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:0,365'],
            'remind_days' => ['nullable', 'required_without:days', 'string', 'max:100', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'remind_hour' => ['required', 'integer', 'between:0,23'],
        ], [
            'remind_days.regex' => __('Write the days as numbers with commas between them, for example 45, 10.'),
            'remind_days.required_without' => __('Choose at least one day to be reminded on.'),
        ]);

        $data['currency'] = strtoupper($data['currency']);

        // "usd, afn eur" becomes ['USD', 'AFN', 'EUR']; anything that is not a 3-letter code is left out
        $codes = preg_split('/[\s,;]+/', strtoupper((string) ($data['currencies'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $codes = array_filter($codes, fn (string $code) => preg_match('/^[A-Z]{3}$/', $code) === 1);
        // the ticked ones first, then the codes typed by hand
        $picked = array_map('strtoupper', $data['picked'] ?? []);
        $data['currencies'] = array_values(array_unique(array_merge([$data['currency']], $picked, $codes)));
        unset($data['picked']);

        $typed = trim((string) ($data['remind_days'] ?? ''));
        $days = array_merge(array_map('intval', $data['days'] ?? []), $typed === '' ? [] : array_map('intval', preg_split('/\s*,\s*/', $typed)));
        rsort($days);
        $data['remind_days'] = array_values(array_unique($days));
        unset($data['days']);

        tenant()->update($data);

        return back()->with('success', __('Settings saved.'));
    }
}
