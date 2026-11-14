<?php

namespace App\Support;

use App\Mail\BusinessMail;
use App\Models\Notice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails from the platform to its businesses about their own account: the trial is ending,
 * the subscription is ending, it has ended, a payment is confirmed. Each kind goes at most
 * once a day to a business, and a mail problem never stops the work that caused it.
 */
class Lifecycle
{
    /** Called by the scheduler for one business. Returns the kind of email that went out, or null. */
    public static function check(Tenant $tenant): ?string
    {
        if ($tenant->status === Tenant::SUSPENDED) {
            return null;
        }
        $left = $tenant->daysLeft();
        if ($left === null) {
            return null; // no end date
        }
        $trial = $tenant->status === Tenant::TRIAL;
        $warn = Platform::days($trial ? 'trial_warn_days' : 'renew_warn_days');

        $kind = match (true) {
            $left === -1 => $trial ? 'trial_ended' : 'subscription_ended',
            $left === 0, in_array($left, $warn, true) => $trial ? 'trial_ending' : 'renewal_due',
            default => null,
        };
        // a business whose payment is being checked is not chased
        if (! $kind || $tenant->subscriptions()->waiting()->exists()) {
            return null;
        }

        $date = fmt_date($tenant->endsOn());
        $when = $left === 0 ? 'today' : ($left === 1 ? 'tomorrow' : 'in '.$left.' days');
        [$subject, $lines] = match ($kind) {
            'trial_ending' => ["Your free trial ends $when", [
                "Your free trial of ".config('app.name')." for {$tenant->name} ends $when, on $date.",
                'To keep your clients, renewals and reminders working without a break, subscribe before then. Everything you entered stays as it is.',
            ]],
            'trial_ended' => ['Your free trial has ended', [
                "The free trial for {$tenant->name} ended on $date.",
                'Your records are safe. Subscribe and you continue exactly where you stopped.',
            ]],
            'renewal_due' => ["Your subscription ends $when", [
                "The subscription of {$tenant->name} ends $when, on $date.",
                'Renew it before then to keep everything working without a break. The new period starts when the current one ends, so you lose no days by paying early.',
            ]],
            'subscription_ended' => ['Your subscription has ended', [
                "The subscription of {$tenant->name} ended on $date.",
                'Your records are safe. Renew and you continue exactly where you stopped.',
            ]],
        };

        return self::send($tenant, $kind, $subject, $lines, 'Open the Subscription page', route('billing.show')) ? $kind : null;
    }

    /** A payment was confirmed: thank the business and point it to its receipt. */
    public static function paymentConfirmed(Subscription $subscription): void
    {
        $tenant = $subscription->tenant;
        if (! $tenant) {
            return;
        }
        $until = $subscription->ends_on ? 'until '.fmt_date($subscription->ends_on) : 'with no end date';

        self::send($tenant, 'payment_confirmed', 'Your payment is confirmed', [
            'We received your payment of '.money($subscription->amount, $subscription->currency).'. Thank you.',
            "The subscription of {$tenant->name} is active $until.",
            'Receipt number: '.$subscription->receiptNumber(),
        ], 'See the receipt', route('billing.receipt', ['id' => $subscription->id]));
    }

    /** One email and one notification under the bell. False when this kind already went today. */
    private static function send(Tenant $tenant, string $kind, string $subject, array $lines, string $button, string $url): bool
    {
        $today = now($tenant->timezone ?: 'UTC')->toDateString();
        $log = TenantMail::firstOrCreate(['tenant_id' => $tenant->id, 'kind' => $kind, 'on_date' => $today]);
        if (! $log->wasRecentlyCreated) {
            return false;
        }

        $users = User::where('tenant_id', $tenant->id)->where('is_active', true)->get();
        foreach ($users as $user) {
            Notice::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'title' => $subject, 'body' => $lines[0], 'url' => $url]);
        }

        $to = $users->where('role', User::OWNER)->pluck('email')->merge([$tenant->email])->filter()->unique()->values()->all();
        if ($to) {
            try {
                Mail::to($to)->send(new BusinessMail($tenant, $subject, $lines, $button, $url));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return true;
    }
}
