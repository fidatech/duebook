<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One income or one expense. */
class Transaction extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => Day::class];
    }

    protected static function booted(): void
    {
        // deleting the income of a contract payment deletes the payment and puts the contract back
        static::deleting(function (Transaction $transaction) {
            if ($payment = $transaction->payment) {
                $payment->rollBackContract();
                $payment->delete();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
