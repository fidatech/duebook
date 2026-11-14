<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Seeder;

/** What a fresh installation starts with: the plan (199 USD a year after the free trial) and the HesabPay account to pay into. Both can be changed under /admin. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'DueBook Yearly', 'price' => 199, 'max_clients' => null, 'max_users' => null, 'sort' => 1, 'description' => 'Everything, with no limits, for one year.'],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan + ['currency' => 'USD', 'interval' => 'yearly']);
        }

        // the way businesses pay for now: HesabPay, by scanning the QR code or sending to the number
        $hesab = PlatformAccount::firstOrCreate(['name' => 'HesabPay'], [
            'type' => 'mobile',
            'currency' => 'USD',
            'number' => '+93784006200',
            'details' => "Pay 199 USD, or the equivalent in AFN, to HesabPay: scan the QR code or send to the number above.\nThen take a screenshot of the payment and add it below.",
        ]);
        if (! $hesab->qr_path && is_file($qr = database_path('seeders/files/hesabpay-qr.png'))) {
            Storage::disk('local')->put('accounts/hesabpay-qr.png', file_get_contents($qr));
            $hesab->update(['qr_path' => 'accounts/hesabpay-qr.png']);
        }
    }
}
