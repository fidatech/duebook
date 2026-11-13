<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function label(): string
    {
        return $this->company ? $this->name.' ('.$this->company.')' : $this->name;
    }

    /** The phone as WhatsApp wants it: digits only, or null. */
    public function whatsapp(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        return strlen($digits) >= 8 ? ltrim($digits, '0') : null;
    }

    /** [id => "Name (Company)"] for pick lists. */
    public static function options(): array
    {
        return static::orderBy('name')->get()->mapWithKeys(fn (Client $c) => [$c->id => $c->label()])->all();
    }
}
