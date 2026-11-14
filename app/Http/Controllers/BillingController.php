<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Mail\PaymentRequested;
use App\Models\Admin;
use App\Models\Client;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The Subscription page of a business: how long it may still use the system, what the plan costs,
 * how to pay, and the "I have paid" form. The money is checked by a system administrator, who
 * confirms the request under /admin > Subscriptions; only then the business becomes active.
 */
class BillingController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = tenant();
        $plans = $this->plans($tenant);
        $plan = $this->chosen($tenant, $plans, $request->query('plan'));
        $clients = Client::count();
        $users = User::where('tenant_id', $tenant->id)->count();

        return view('billing.show', [
            'tenant' => $tenant,
            'plans' => $plans,
            'plan' => $plan,
            'current' => $tenant->plan,
            // a plan that allows fewer clients or users than the business already has cannot be chosen
            'tooSmall' => $plans->mapWithKeys(fn (Plan $p) => [$p->id => $this->tooSmall($p, $clients, $users)])->all(),
            'left' => $tenant->daysLeft(),
            // a paid period cannot be changed or paid back: choosing opens only near its end (or after it)
            'open' => $this->open($tenant),
            'opensOn' => $this->opensOn($tenant),
            // plans higher than the running one: these can be taken at any time, and start today
            'upgrades' => $plans->mapWithKeys(fn (Plan $p) => [$p->id => $this->isUpgrade($tenant, $p)])->all(),
            'period' => $plan ? $this->period($tenant, $plan) : null,
            // the free plan can be taken, without paying, by a business on a trial or whose time has ended
            'canFree' => $this->canFree($tenant),
            // plans are paid by the month or by the year: the page shows one of the two at a time
            'cycles' => $cycles = $this->cycles($plans),
            'cycle' => $plan && in_array($plan->interval, $cycles, true) ? $plan->interval : ($cycles[0] ?? null),
            'saving' => $this->saving($plans),
            'accounts' => PlatformAccount::where('is_active', true)->orderBy('name')->get(),
            'pending' => $pending = $tenant->subscriptions()->waiting()->with('plan')->orderByDesc('id')->first(),
            // the last request, when it was rejected: shown with its reason above the form
            'rejected' => $pending ? null : $this->lastRejected($tenant),
            'history' => $tenant->subscriptions()->with('plan')->whereNotNull('paid_on')->orderByDesc('starts_on')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = tenant();
        $plans = $this->plans($tenant)->where('is_active', true);
        if ($plans->isEmpty()) {
            throw new UserError(__('There is no plan to subscribe to yet. Please contact support.'));
        }
        if ($tenant->subscriptions()->waiting()->exists()) {
            throw new UserError(__('Your payment is already waiting to be confirmed.'));
        }
        $picked = $plans->firstWhere('id', (int) $request->input('plan_id'));
        if ($picked?->isFree()) {
            return $this->moveToFree($tenant, $picked);
        }
        if (! $this->open($tenant) && ! ($picked && $this->isUpgrade($tenant, $picked))) {
            throw new UserError(__('Your current plan is paid until :date. You can renew it or choose another plan from :from.', ['date' => fmt_date($tenant->endsOn()), 'from' => fmt_date($this->opensOn($tenant))]));
        }

        $data = $request->validate([
            'plan_id' => ['required', Rule::in($plans->pluck('id')->all())],
            'sent_on' => ['required', 'date', 'before_or_equal:'.Carbon::parse(today_iso())->addDay()->toDateString()],
            'platform_account_id' => ['nullable', Rule::exists('platform_accounts', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], ['receipt.required' => __('Please add the screenshot of your payment.'), 'plan_id.required' => __('Choose a plan first.')]);

        $plan = $plans->firstWhere('id', (int) $data['plan_id']);
        if ($problem = $this->tooSmall($plan, Client::count(), User::where('tenant_id', $tenant->id)->count())) {
            throw new UserError($problem);
        }

        [$from, $until] = $this->period($tenant, $plan);

        $subscription = Subscription::create([
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

        $this->tellAdministrators($subscription);

        return redirect()->route('billing.show')->with('success', __('Thank you. We will check your payment and switch your subscription on.'));
    }

    /**
     * May the business choose a plan and pay now? Yes on a trial and after the end. While a paid
     * period runs: only in its last days (Settings: "renewal opens"), and what is bought then
     * starts when the current period ends. A business with no end date has nothing to pay.
     */
    private function open(Tenant $tenant): bool
    {
        if ($tenant->status === Tenant::SUSPENDED) {
            return false;
        }
        if ($tenant->status === Tenant::TRIAL || ! $tenant->isUsable() || $tenant->onFreePlan()) {
            return true;
        }
        $left = $tenant->daysLeft();

        return $left !== null && $left <= (int) platform('renew_open_days');
    }

    /**
     * Is this plan a step up from the paid period that is running? Then it can be taken at any
     * time: it is paid in full and starts today, and the days left on the old plan are not paid back.
     */
    private function isUpgrade(Tenant $tenant, Plan $plan): bool
    {
        $current = $tenant->plan;

        return $tenant->status === Tenant::ACTIVE && $tenant->isUsable() && $tenant->endsOn() !== null
            && $current && $plan->is_active && $plan->id !== $current->id
            && strtoupper($plan->currency) === strtoupper($current->currency)
            // by what a month costs, so a yearly plan is not "higher" than a bigger plan paid monthly
            && round($plan->monthlyRate(), 4) > round($current->monthlyRate(), 4);
    }

    /** May the business move to the free plan now? Only from a trial or after its time ended: a paid period is never cut short. */
    private function canFree(Tenant $tenant): bool
    {
        return $tenant->status !== Tenant::SUSPENDED && ! $tenant->onFreePlan()
            && ($tenant->status === Tenant::TRIAL || ! $tenant->isUsable());
    }

    /** Nothing is paid for the free plan, so nothing is checked: the business is on it at once. */
    private function moveToFree(Tenant $tenant, Plan $plan): RedirectResponse
    {
        if ($tenant->onFreePlan()) {
            throw new UserError(__('You are already on this plan.'));
        }
        if (! $this->canFree($tenant)) {
            throw new UserError(__('Your current plan is paid until :date. You can move to the free plan after that.', ['date' => fmt_date($tenant->endsOn())]));
        }
        if ($problem = $this->tooSmall($plan, Client::count(), User::where('tenant_id', $tenant->id)->count())) {
            throw new UserError($problem);
        }
        $tenant->update(['plan_id' => $plan->id, 'status' => Tenant::ACTIVE, 'subscription_ends_at' => null]);

        return redirect()->route('billing.show')->with('success', __('You are on the :plan plan now.', ['plan' => $plan->name]));
    }

    /** "monthly" and "yearly", when the paid plans on offer come in both; otherwise the page needs no switch. */
    private function cycles(Collection $plans): array
    {
        $offered = $plans->filter(fn (Plan $p) => $p->is_active && ! $p->isFree())->pluck('interval')->unique();

        return $offered->contains('monthly') && $offered->contains('yearly') ? ['monthly', 'yearly'] : [];
    }

    /** The most a business saves, in percent, by paying a plan for a year instead of twelve months. */
    private function saving(Collection $plans): int
    {
        $offered = $plans->filter(fn (Plan $p) => $p->is_active && ! $p->isFree());

        return (int) $offered->where('interval', 'yearly')->map(function (Plan $year) use ($offered) {
            $month = $offered->first(fn (Plan $p) => $p->interval === 'monthly' && $p->name === $year->name && $p->currency === $year->currency);

            return $month ? (int) round(100 * (1 - (float) $year->price / (12 * (float) $month->price))) : 0;
        })->max();
    }

    /** The first day a business with a running paid period may renew or change its plan. */
    private function opensOn(Tenant $tenant): ?Carbon
    {
        $end = $tenant->status === Tenant::ACTIVE ? $tenant->subscription_ends_at : null;

        return $end ? $end->copy()->subDays((int) platform('renew_open_days')) : null;
    }

    /** The plans on offer, in their order; the plan of the business is added when it is no longer offered. */
    private function plans(Tenant $tenant): Collection
    {
        $plans = Plan::where('is_active', true)->orderBy('sort')->orderBy('price')->orderBy('id')->get();
        if ($tenant->plan && ! $plans->contains('id', $tenant->plan_id)) {
            $plans->push($tenant->plan);
        }

        return $plans;
    }

    /** The plan the page is about: the one picked in the address, else the plan of the business, else the first on offer. */
    private function chosen(Tenant $tenant, Collection $plans, mixed $picked): ?Plan
    {
        // the free plan is never paid for, so it is never the plan to pay
        $offered = $plans->filter(fn (Plan $p) => $p->is_active && ! $p->isFree());

        return $offered->firstWhere('id', (int) $picked)
            ?? $offered->firstWhere('id', $tenant->plan_id)
            ?? $offered->first();
    }

    /** Why a business cannot take this plan, or null when it fits. */
    private function tooSmall(Plan $plan, int $clients, int $users): ?string
    {
        if ($plan->max_clients && $clients > $plan->max_clients) {
            return __('You have :have clients and this plan allows :max.', ['have' => number_format($clients), 'max' => number_format($plan->max_clients)]);
        }
        if ($plan->max_users && $users > $plan->max_users) {
            return __('You have :have users and this plan allows :max.', ['have' => number_format($users), 'max' => number_format($plan->max_users)]);
        }

        return null;
    }

    /** The newest "I have paid" request of the business, if it was rejected and nothing was paid after it. */
    private function lastRejected(Tenant $tenant): ?Subscription
    {
        $last = $tenant->subscriptions()->where('requested', true)->orderByDesc('id')->first();
        if (! $last?->isRejected()) {
            return null;
        }

        return $tenant->subscriptions()->whereNotNull('paid_on')->where('id', '>', $last->id)->exists() ? null : $last;
    }

    /** The numbered receipt of one of the business's own confirmed payments. */
    public function receipt(int $id): View
    {
        $subscription = tenant()->subscriptions()->with('tenant', 'plan', 'account')->whereNotNull('receipt_no')->findOrFail($id);

        return view('receipts.show', ['subscription' => $subscription]);
    }

    /** The QR code of a payment account, for the Subscription page. */
    public function qr(int $id): StreamedResponse
    {
        $account = PlatformAccount::where('is_active', true)->findOrFail($id);
        abort_unless($account->qr_path && Storage::disk('local')->exists($account->qr_path), 404);

        return Storage::disk('local')->response($account->qr_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** An email to the platform owner, with the screenshot. A mail problem never loses the request itself. */
    private function tellAdministrators(Subscription $subscription): void
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) platform('billing_email')))))
            ?: Admin::where('is_active', true)->pluck('email')->all();
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new PaymentRequested($subscription));
        } catch (Throwable $e) {
            report($e);
        }
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
        // an upgrade starts today; a renewal or another plan starts when the paid time ends
        $from = $paidUntil && $paidUntil->gte($today) && ! $this->isUpgrade($tenant, $plan) ? $paidUntil->copy()->addDay() : $today;

        $until = match ($plan->interval) {
            'monthly' => $from->copy()->addMonthNoOverflow(),
            'lifetime' => null,
            default => $from->copy()->addYearNoOverflow(),
        };

        return [$from, $until];
    }
}
