<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A period a business paid for. Written by a system administrator; it sets the plan and the end date of the business. */
class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => Day::class, 'ends_on' => Day::class, 'paid_on' => Day::class];
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
}
