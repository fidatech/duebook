<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Something the business sells: a service (hosting, a system, support) or a product (a digital good). */
class Service extends Model
{
    use BelongsToTenant;

    public const KINDS = ['service' => 'Service', 'product' => 'Product'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }
}
