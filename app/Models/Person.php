<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone the owner lends money to or borrows money from. */
class Person extends Model
{
    use BelongsToTenant;

    protected $table = 'people';

    protected $guarded = [];

    public function entries(): HasMany
    {
        return $this->hasMany(PersonEntry::class);
    }

    /** Balance by currency: above zero the person owes me, below zero I owe the person. */
    public function balances(): array
    {
        return $this->entries()
            ->selectRaw("currency, sum(case when direction = 'gave' then amount else -amount end) as balance")
            ->groupBy('currency')->orderBy('currency')->pluck('balance', 'currency')
            ->map(fn ($balance) => round((float) $balance, 2))->all();
    }
}
