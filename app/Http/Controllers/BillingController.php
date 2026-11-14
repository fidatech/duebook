<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Subscription page of a business: how long it may still use the system, what the plan costs,
 * how to pay, and the "I have paid" form. The money is checked by a system administrator, who
 * confirms the request under /admin > Subscriptions; only then the business becomes active.
 */
class BillingController extends Controller
{
    public function show(): View
    {
        $tenant = tenant();
        $plan = $this->plan($tenant);

        return view('billing.show', [
            'tenant' => $tenant,
            'plan' => $plan,
            'left' => $tenant->daysLeft(),
            'period' => $plan ? $this->period($tenant, $plan) : null,
            'accounts' => PlatformAccount::where('is_active', true)->orderBy('name')->get(),
            'pending' => $tenant->subscriptions()->where('requested', true)->whereNull('paid_on')->orderByDesc('id')->first(),
            'history' => $tenant->subscriptions()->with('plan')->whereNotNull('paid_on')->orderByDesc('starts_on')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = tenant();
        $plan = $this->plan($tenant);
        if (! $plan) {
            throw new UserError(__('There is no plan to subscribe to yet. Please contact support.'));
        }
        if ($tenant->subscriptions()->where('requested', true)->whereNull('paid_on')->exists()) {
            throw new UserError(__('Your payment is already waiting to be confirmed.'));
        }

        $data = $request->validate([
            'sent_on' => ['required', 'date', 'before_or_equal:'.Carbon::parse(today_iso())->addDay()->toDateString()],
            'platform_account_id' => ['nullable', Rule::exists('platform_accounts', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        [$from, $until] = $this->period($tenant, $plan);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'platform_account_id' => $data['platform_account_id'] ?? null,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'starts_on' => $from,
            'ends_on' => $until,
            'requested' => true,
            'sent_on' => $data['sent_on'],
            'reference' => $data['reference'] ?? null,
            'receipt_path' => $request->file('receipt')?->store('receipts/'.$tenant->id, 'local'),
        ]);

        return redirect()->route('billing.show')->with('success', __('Thank you. We will check your payment and switch your subscription on.'));
    }

    /** The plan of the business, or the first plan on offer. */
    private function plan(Tenant $tenant): ?Plan
    {
        return ($tenant->plan?->is_active ? $tenant->plan : null) ?? Plan::where('is_active', true)->orderBy('sort')->orderBy('id')->first();
    }

    /**
     * The period a new payment covers: from today, or from the day after the paid time that is
     * still running, for one month or one year as the plan says (no end for a lifetime plan).
     *
     * @return array{0: Carbon, 1: ?Carbon}
     */
    private function period(Tenant $tenant, Plan $plan): array
    {
        $today = Carbon::parse(today_iso());
        $paidUntil = $tenant->status === Tenant::ACTIVE ? $tenant->subscription_ends_at : null;
        $from = $paidUntil && $paidUntil->gte($today) ? $paidUntil->copy()->addDay() : $today;

        $until = match ($plan->interval) {
            'monthly' => $from->copy()->addMonthNoOverflow(),
            'lifetime' => null,
            default => $from->copy()->addYearNoOverflow(),
        };

        return [$from, $until];
    }
}
