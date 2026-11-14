<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function label(): string
    {
        return $this->name.' ('.money($this->price, $this->currency).' / '.$this->interval.')';
    }

    /** A plan that costs nothing: a business stays on it with no end date and pays nothing. */
    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /** The free plan on offer, if there is one. New businesses start on it instead of a trial. */
    public static function free(): ?self
    {
        return static::where('is_active', true)->where('price', '<=', 0)->orderBy('sort')->orderBy('id')->first();
    }

    /** "4.99 USD" or "199 USD": two decimals only when the price has them. */
    public function priceText(): string
    {
        $price = (float) $this->price;

        return number_format($price, fmod($price, 1.0) == 0.0 ? 0 : 2).' '.$this->currency;
    }

    /** What one month on this plan costs, to tell a higher plan from a lower one whatever the period paid for. */
    public function monthlyRate(): float
    {
        return $this->interval === 'yearly' ? (float) $this->price / 12 : (float) $this->price;
    }
}
