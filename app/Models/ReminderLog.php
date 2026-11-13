<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => Day::class];
    }
}
