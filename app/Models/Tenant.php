<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One business that uses DueBook. Everything it records carries its tenant_id. */
class Tenant extends Model
{
    public const TRIAL = 'trial';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'currencies' => 'array',
            'remind_days' => 'array',
            'trial_ends_at' => Day::class,
            'subscription_ends_at' => Day::class,
            'last_reminded_on' => Day::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function owner(): ?User
    {
        return $this->users()->where('role', User::OWNER)->orderBy('id')->first();
    }

    /** The day the business may use the system until, or null when there is no end. */
    public function endsOn(): ?\Illuminate\Support\Carbon
    {
        return $this->status === self::TRIAL ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    public function isUsable(): bool
    {
        if ($this->status === self::SUSPENDED) {
            return false;
        }
        $end = $this->endsOn();

        return $end === null || $end->toDateString() >= now($this->timezone ?: 'UTC')->toDateString();
    }

    /** Whole days of use left, 0 on the last day, below zero when it ended. Null when there is no end. */
    public function daysLeft(): ?int
    {
        $end = $this->endsOn();

        if (! $end) {
            return null;
        }
        $today = \Illuminate\Support\Carbon::parse(now($this->timezone ?: 'UTC')->toDateString());

        return (int) $today->diffInDays(\Illuminate\Support\Carbon::parse($end->toDateString()), false);
    }

    /**
     * The period that is running: when it started, when it ends, how many days it has, how many
     * are used and left, and the share used in percent. A trial runs from the day of sign-up;
     * a paid business from the start of the paid period that covers today.
     *
     * @return array{from: ?\Illuminate\Support\Carbon, to: ?\Illuminate\Support\Carbon, total: ?int, used: ?int, left: ?int, percent: ?int}
     */
    public function period(): array
    {
        $zone = $this->timezone ?: 'UTC';
        $to = $this->endsOn();
        $from = $this->status === self::TRIAL
            ? ($this->created_at ? \Illuminate\Support\Carbon::parse($this->created_at->copy()->timezone($zone)->toDateString()) : null)
            : Subscription::runningFor($this)?->starts_on;
        $left = $this->daysLeft();

        $total = $from && $to ? max(1, (int) $from->diffInDays(\Illuminate\Support\Carbon::parse($to->toDateString()), false)) : null;
        $used = $total !== null && $left !== null ? min($total, max(0, $total - max(0, $left))) : null;

        return [
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'used' => $used,
            'left' => $left,
            'percent' => $total ? (int) round(100 * $used / $total) : null,
        ];
    }

    public function statusLabel(): string
    {
        if ($this->status === self::SUSPENDED) {
            return 'Suspended';
        }
        if (! $this->isUsable()) {
            return 'Expired';
        }

        return $this->status === self::TRIAL ? 'Trial' : 'Active';
    }

    /** Days before a due date on which reminders go out, biggest first. */
    public function remindDays(): array
    {
        $days = array_values(array_unique(array_map('intval', $this->remind_days ?: config('duebook.remind_days'))));
        rsort($days);

        return $days;
    }

    /** Where the daily reminder email goes. */
    public function reminderEmail(): ?string
    {
        return $this->notify_email ?: ($this->email ?: $this->owner()?->email);
    }
}
