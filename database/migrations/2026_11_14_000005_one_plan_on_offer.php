<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Only one plan is on offer: DueBook Yearly. The three example plans of the first seed
 * (Starter 60, Business 150, Unlimited 300) are switched off, and businesses that were
 * put on them by default move to DueBook Yearly. Plans somebody edited are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $examples = DB::table('plans')->where(fn ($q) => $q
            ->where(fn ($p) => $p->where('name', 'Starter')->where('price', 60))
            ->orWhere(fn ($p) => $p->where('name', 'Business')->where('price', 150))
            ->orWhere(fn ($p) => $p->where('name', 'Unlimited')->where('price', 300)))->pluck('id')->all();
        if (! $examples) {
            return;
        }

        $yearly = DB::table('plans')->where('name', 'DueBook Yearly')->value('id');
        if (! $yearly) {
            $yearly = DB::table('plans')->insertGetId([
                'name' => 'DueBook Yearly', 'description' => 'Everything, with no limits, for one year.', 'price' => 199, 'currency' => 'USD',
                'interval' => 'yearly', 'is_active' => true, 'sort' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('plans')->whereIn('id', $examples)->update(['is_active' => false, 'sort' => 99]);
        DB::table('plans')->where('id', $yearly)->update(['sort' => 1, 'is_active' => true]);
        DB::table('tenants')->whereIn('plan_id', $examples)->update(['plan_id' => $yearly]);
    }

    public function down(): void
    {
        //
    }
};
