<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "gave": money went from me to the person. "received": money came from the person to me. */
class PersonEntry extends Model
{
    use BelongsToTenant;

    public const DIRECTIONS = ['gave' => 'I gave', 'received' => 'I received'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => Day::class, 'due_date' => Day::class];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** Balance of every person by currency: [person_id => [currency => balance]]. */
    public static function balances(?int $tenantId = null, ?string $currency = null): array
    {
        $rows = static::query()
            ->when($tenantId, fn ($query) => $query->where('person_entries.tenant_id', $tenantId))
            ->when($currency, fn ($query) => $query->where('currency', $currency))
            ->selectRaw("person_id, currency, sum(case when direction = 'gave' then amount else -amount end) as balance")
            ->groupBy('person_id', 'currency')->get();

        $out = [];
        foreach ($rows as $row) {
            if (round((float) $row->balance, 2) != 0.0) {
                $out[$row->person_id][$row->currency] = round((float) $row->balance, 2);
            }
        }

        return $out;
    }
}
