<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

if (! function_exists('asset_v')) {
    /**
     * URL of a file in public/ with its modification time appended, so browsers can
     * cache it for a year and still pick up a new version the moment it changes.
     */
    function asset_v(string $path): string
    {
        static $urls = [];

        return $urls[$path] ??= asset($path).'?v='.(is_file(public_path($path)) ? filemtime(public_path($path)) : '0');
    }
}

if (! function_exists('text_dir')) {
    /** Text direction of the current language. */
    function text_dir(): string
    {
        return in_array(app()->getLocale(), ['fa', 'ps', 'ar'], true) ? 'rtl' : 'ltr';
    }
}

if (! function_exists('tenant')) {
    /** The business of the signed-in user, or null (admin panel, console, guest). */
    function tenant(): ?Tenant
    {
        return auth('web')->user()?->tenant;
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return auth('web')->user()?->tenant_id;
    }
}

if (! function_exists('own')) {
    /** Validation rule: the chosen record must belong to the business of the signed-in user. */
    function own(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('tenant_id', tenant_id());
    }
}

if (! function_exists('currencies')) {
    /** The currencies the business works with: ['USD' => 'USD', ...], its main currency first. */
    function currencies(): array
    {
        $tenant = tenant();
        $codes = array_values(array_unique(array_filter(array_merge([$tenant?->currency ?: 'USD'], $tenant?->currencies ?? []))));

        return array_combine($codes, $codes);
    }
}

if (! function_exists('today_iso')) {
    /** Today in the time zone of the business. */
    function today_iso(): string
    {
        return now(tenant()?->timezone ?: config('app.timezone'))->toDateString();
    }
}

if (! function_exists('fmt_date')) {
    /** A stored date as people read it: 14 Nov 2026. */
    function fmt_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return ($date instanceof DateTimeInterface ? $date : new DateTimeImmutable(substr((string) $date, 0, 10)))->format('j M Y');
    }
}

if (! function_exists('input_date')) {
    /** A stored date as the value of a date box. */
    function input_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }
}

if (! function_exists('money')) {
    /** 1234.5 becomes "1,234.50"; with a currency code "1,234.50 USD". */
    function money(mixed $amount, ?string $currency = null): string
    {
        return number_format((float) $amount, 2).($currency ? ' '.$currency : '');
    }
}

if (! function_exists('sums')) {
    /** Totals by currency as one line: "1,200.00 USD · 45,000.00 AFN". An empty list gives "0". */
    function sums(iterable $totals): string
    {
        $parts = [];
        foreach ($totals as $currency => $amount) {
            if ((float) $amount != 0.0) {
                $parts[] = money($amount, (string) $currency);
            }
        }

        return $parts ? implode(' · ', $parts) : '0';
    }
}

if (! function_exists('per_page')) {
    function per_page(int $usual = 25): int
    {
        return $usual;
    }
}

if (! function_exists('crud_routes')) {
    /** The six routes of a CrudController screen: list, add, save, edit, update, delete. */
    function crud_routes(string $uri, string $controller, string $name): void
    {
        Route::get($uri, [$controller, 'index'])->name($name.'.index');
        Route::get($uri.'/create', [$controller, 'create'])->name($name.'.create');
        Route::post($uri, [$controller, 'store'])->name($name.'.store');
        Route::get($uri.'/{id}/edit', [$controller, 'edit'])->whereNumber('id')->name($name.'.edit');
        Route::put($uri.'/{id}', [$controller, 'update'])->whereNumber('id')->name($name.'.update');
        Route::delete($uri.'/{id}', [$controller, 'destroy'])->whereNumber('id')->name($name.'.destroy');
    }
}
