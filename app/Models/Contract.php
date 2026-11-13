<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One thing a client pays for: "example.af hosting, 60 USD every year".
 * next_due_date is the day the next money should come; receiving a payment moves it one cycle on.
 */
class Contract extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => Day::class, 'next_due_date' => Day::class, 'notify_client' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Contracts that money is still expected from. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNotNull('next_due_date');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->where('next_due_date', '<', today_iso());
    }

    /** Months between two due dates; 0 for a one-time contract. */
    public function months(): int
    {
        return (int) (config('duebook.cycles')[$this->cycle] ?? 12);
    }

    public function cycleLabel(): string
    {
        return __(config('duebook.cycle_labels')[$this->cycle] ?? $this->cycle);
    }

    /** The due date after the current one, or null for a one-time contract. */
    public function dueAfter(): ?Carbon
    {
        if (! $this->next_due_date || $this->months() === 0) {
            return null;
        }

        return $this->next_due_date->copy()->addMonthsNoOverflow($this->months());
    }

    /** Days until the due date: below zero when it is late. Null when nothing is due. */
    public function daysLeft(?string $today = null): ?int
    {
        if (! $this->next_due_date) {
            return null;
        }

        return (int) Carbon::parse($today ?: today_iso())->diffInDays($this->next_due_date->copy()->startOfDay(), false);
    }

    /** ["3 days late", "danger"] for the badge beside a due date. */
    public function dueBadge(): array
    {
        $days = $this->daysLeft();

        return match (true) {
            $this->status !== 'active' => [__(self::STATUSES[$this->status] ?? $this->status), 'secondary'],
            $days === null => [__('Nothing due'), 'secondary'],
            $days < 0 => [trans_choice('{1} 1 day late|[2,*] :count days late', -$days, ['count' => -$days]), 'danger'],
            $days === 0 => [__('Due today'), 'danger'],
            $days <= 30 => [trans_choice('{1} in 1 day|[2,*] in :count days', $days, ['count' => $days]), 'warning'],
            default => [trans_choice('{1} in 1 day|[2,*] in :count days', $days, ['count' => $days]), 'success'],
        };
    }
}
