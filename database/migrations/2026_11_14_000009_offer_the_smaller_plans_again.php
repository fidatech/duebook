<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Businesses now choose among several plans, so the two smaller example plans are offered
 * again beside DueBook Yearly: Starter (60) and Business (150). "Unlimited" (300) stays off,
 * because DueBook Yearly already has no limits. Plans somebody edited are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('name', 'Starter')->where('price', 60)->where('is_active', false)->update(['is_active' => true, 'sort' => 1]);
        DB::table('plans')->where('name', 'Business')->where('price', 150)->where('is_active', false)->update(['is_active' => true, 'sort' => 2]);
        DB::table('plans')->where('name', 'DueBook Yearly')->update(['sort' => 3]);
    }

    public function down(): void
    {
        //
    }
};
