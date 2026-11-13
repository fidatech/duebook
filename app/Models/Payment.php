<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/** Money received for a contract. It also writes one income line, and moves the contract to its next due date. */
class Payment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_on' => Day::class, 'period_due' => Day::class, 'advanced' => 'boolean'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * Receive money for a contract.
     *
     * @param  array{amount: mixed, paid_on: string, note?: ?string, advance?: bool}  $data
     */
    public static function receive(Contract $contract, array $data): self
    {
        return DB::transaction(function () use ($contract, $data) {
            $advance = (bool) ($data['advance'] ?? true);

            $payment = static::create([
                'tenant_id' => $contract->tenant_id,
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'amount' => $data['amount'],
                'currency' => $contract->currency,
                'paid_on' => $data['paid_on'],
                'period_due' => $contract->next_due_date,
                'advanced' => $advance,
                'note' => $data['note'] ?? null,
            ]);

            $category = $contract->service?->category_id
                ?: Category::firstOrCreate(['tenant_id' => $contract->tenant_id, 'type' => 'income', 'name' => 'Client renewals'])->id;

            Transaction::create([
                'tenant_id' => $contract->tenant_id,
                'type' => 'income',
                'category_id' => $category,
                'client_id' => $contract->client_id,
                'service_id' => $contract->service_id,
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
                'currency' => $contract->currency,
                'date' => $data['paid_on'],
                'description' => $contract->title,
            ]);

            if ($advance) {
                $next = $contract->dueAfter();
                // a one-time contract is finished once it is paid
                $contract->update($next ? ['next_due_date' => $next] : ['next_due_date' => null, 'status' => 'ended']);
            }

            return $payment;
        });
    }

    /** Undo what this payment did to its contract (used when the payment is deleted). */
    public function rollBackContract(): void
    {
        $contract = $this->contract;
        if (! $this->advanced || ! $contract || ! $this->period_due) {
            return;
        }
        // only when no later payment moved the contract further
        $expected = $contract->months() === 0 ? null : $this->period_due->copy()->addMonthsNoOverflow($contract->months())->toDateString();
        if ($contract->next_due_date?->toDateString() === $expected) {
            $contract->update(['next_due_date' => $this->period_due, 'status' => 'active']);
        }
    }
}
