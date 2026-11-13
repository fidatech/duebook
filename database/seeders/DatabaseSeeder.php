<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Example plans to start with. Change the names, prices and limits under /admin > Plans. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Starter', 'price' => 60, 'max_clients' => 50, 'max_users' => 1, 'sort' => 1, 'description' => 'For one person with up to 50 clients.'],
            ['name' => 'Business', 'price' => 150, 'max_clients' => 500, 'max_users' => 5, 'sort' => 2, 'description' => 'For a small team with up to 500 clients.'],
            ['name' => 'Unlimited', 'price' => 300, 'max_clients' => null, 'max_users' => null, 'sort' => 3, 'description' => 'No limits.'],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan + ['currency' => 'USD', 'interval' => 'yearly']);
        }
    }
}
