<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** The plan every new business starts on: 200 USD a year after the free trial. Change it under /admin > Plans. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'DueBook Yearly', 'price' => 200, 'max_clients' => null, 'max_users' => null, 'sort' => 1, 'description' => 'Everything, with no limits, for one year.'],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan + ['currency' => 'USD', 'interval' => 'yearly']);
        }
    }
}
