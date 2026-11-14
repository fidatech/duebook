<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The plans on offer are now Free, Pro and Business, and Pro and Business can be paid by the
 * month or by the year (the year costs about 20% less):
 *
 *   Free       0            10 clients,   1 user
 *   Pro        4.99 a month or 47.90 a year    200 clients, 3 users
 *   Business  14.99 a month or 143.90 a year   no limits
 *
 * The earlier plans (DueBook Yearly, Starter, Business 150, Unlimited) are no longer offered:
 * one that a business is on, or has paid for, is switched off and stays in the records; the
 * others are removed. Plans somebody edited are left alone. Everything can be changed
 * afterwards under /admin > Plans. A new installation has no plans yet and gets these from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // a new installation gets its plans from the seeder
        if (DB::table('plans')->doesntExist()) {
            return;
        }

        $old = DB::table('plans')->where('interval', 'yearly')->where(fn ($q) => $q
            ->where(fn ($p) => $p->where('name', 'DueBook Yearly')->whereIn('price', [199, 200]))
            ->orWhere(fn ($p) => $p->where('name', 'Starter')->where('price', 60))
            ->orWhere(fn ($p) => $p->where('name', 'Business')->where('price', 150))
            ->orWhere(fn ($p) => $p->where('name', 'Unlimited')->where('price', 300)))->pluck('id');

        foreach ($old as $id) {
            $used = DB::table('tenants')->where('plan_id', $id)->exists() || DB::table('subscriptions')->where('plan_id', $id)->exists();
            if ($used) {
                DB::table('plans')->where('id', $id)->update(['is_active' => false, 'sort' => 99, 'updated_at' => now()]);
            } else {
                DB::table('plans')->where('id', $id)->delete();
            }
        }

        $plans = [
            ['Free', 'lifetime', 0, 10, 1, 1, 'Essential features to get started.'],
            ['Pro', 'monthly', 4.99, 200, 3, 2, 'More power and flexibility.'],
            ['Pro', 'yearly', 47.90, 200, 3, 3, 'More power and flexibility.'],
            ['Business', 'monthly', 14.99, null, null, 4, 'For teams and businesses.'],
            ['Business', 'yearly', 143.90, null, null, 5, 'For teams and businesses.'],
        ];
        foreach ($plans as [$name, $interval, $price, $clients, $users, $sort, $description]) {
            if (DB::table('plans')->where('name', $name)->where('interval', $interval)->where('is_active', true)->exists()) {
                continue;
            }
            DB::table('plans')->insert([
                'name' => $name, 'description' => $description, 'price' => $price, 'currency' => 'USD', 'interval' => $interval,
                'max_clients' => $clients, 'max_users' => $users, 'is_active' => true, 'sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
