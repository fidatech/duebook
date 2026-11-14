<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period a business pays for. Written by a system administrator, or sent by the business itself
 * from its Subscription page ("I have paid"). While paid_on is empty the money is not confirmed.
 * Once it is confirmed, the business follows it: plan, active status and end date.
 */
class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => Day::class, 'ends_on' => Day::class, 'paid_on' => Day::class, 'sent_on' => Day::class, 'requested' => 'boolean', 'rejected_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null && ! $this->isPaid();
    }

    /** Sent by the business and neither confirmed nor rejected yet. */
    public function isWaiting(): bool
    {
        return $this->requested && ! $this->isPaid() && $this->rejected_at === null;
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('requested', true)->whereNull('paid_on')->whereNull('rejected_at');
    }

    /** The reason of the rejection as the business reads it. */
    public function rejectText(): string
    {
        return __(config('duebook.reject_reasons')[$this->reject_reason]['text'] ?? 'The payment could not be accepted.');
    }

    /** What the business should do after this rejection. */
    public function rejectAdvice(): string
    {
        return __(config('duebook.reject_reasons')[$this->reject_reason]['advice'] ?? 'Please pay and send the screenshot again.');
    }

    /** The short name of the reason, for the lists of the administrators. */
    public function rejectLabel(): string
    {
        return __(config('duebook.reject_reasons')[$this->reject_reason]['label'] ?? 'Rejected');
    }

    /** The business follows its latest confirmed subscription: plan, active status and end date. */
    public function apply(): void
    {
        if (! $this->isPaid()) {
            return;
        }
        $tenant = $this->tenant;
        $latest = $tenant->subscriptions()->whereNotNull('paid_on')->orderByRaw('ends_on is null desc')->orderByDesc('ends_on')->orderByDesc('id')->first();
        if ($latest && $latest->id === $this->id) {
            $tenant->update([
                'plan_id' => $this->plan_id,
                'subscription_ends_at' => $this->ends_on,
                'status' => $tenant->status === Tenant::SUSPENDED ? Tenant::SUSPENDED : Tenant::ACTIVE,
            ]);
        }
    }
}
