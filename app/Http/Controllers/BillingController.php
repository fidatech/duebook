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
            'period' => $plan ? $this->period($tenant, $plan) : null,
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
        $offered = $plans->where('is_active', true);

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
        $from = $paidUntil && $paidUntil->gte($today) ? $paidUntil->copy()->addDay() : $today;

        $until = match ($plan->interval) {
            'monthly' => $from->copy()->addMonthNoOverflow(),
            'lifetime' => null,
            default => $from->copy()->addYearNoOverflow(),
        };

        return [$from, $until];
    }
}
