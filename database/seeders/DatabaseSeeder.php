<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Seeder;

/** What a fresh installation starts with: the plans (Free, and Pro and Business by the month or the year) and the HesabPay account to pay into. Both can be changed under /admin. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Free', 'interval' => 'lifetime', 'price' => 0, 'max_clients' => 10, 'max_users' => 1, 'sort' => 1, 'description' => 'Essential features to get started.'],
            ['name' => 'Pro', 'interval' => 'monthly', 'price' => 4.99, 'max_clients' => 200, 'max_users' => 3, 'sort' => 2, 'description' => 'More power and flexibility.'],
            ['name' => 'Pro', 'interval' => 'yearly', 'price' => 47.90, 'max_clients' => 200, 'max_users' => 3, 'sort' => 3, 'description' => 'More power and flexibility.'],
            ['name' => 'Business', 'interval' => 'monthly', 'price' => 14.99, 'max_clients' => null, 'max_users' => null, 'sort' => 4, 'description' => 'For teams and businesses.'],
            ['name' => 'Business', 'interval' => 'yearly', 'price' => 143.90, 'max_clients' => null, 'max_users' => null, 'sort' => 5, 'description' => 'For teams and businesses.'],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name'], 'interval' => $plan['interval']], $plan + ['currency' => 'USD']);
        }

        // the way businesses pay for now: HesabPay, by scanning the QR code or sending to the number
        $hesab = PlatformAccount::firstOrCreate(['name' => 'HesabPay'], [
            'type' => 'mobile',
            'currency' => 'USD',
            'number' => '+93784006200',
            'details' => "Scan the QR code with HesabPay, or send the amount to the number above.\nThen take a screenshot of the payment and add it below.",
        ]);
        if (! $hesab->qr_path && is_file($qr = database_path('seeders/files/hesabpay-qr.png'))) {
            Storage::disk('local')->put('accounts/hesabpay-qr.png', file_get_contents($qr));
            $hesab->update(['qr_path' => 'accounts/hesabpay-qr.png']);
        }
    }
}
