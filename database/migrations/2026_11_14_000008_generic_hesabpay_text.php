<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** With several plans to choose from, the HesabPay text no longer names one price: the page shows the price of the chosen plan. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_accounts')->where('name', 'HesabPay')->where('details', 'like', 'Pay 199 USD, or the equivalent in AFN, to HesabPay:%')
            ->update(['details' => "Scan the QR code with HesabPay, or send the amount to the number above.\nThen take a screenshot of the payment and add it below."]);
    }

    public function down(): void
    {
        //
    }
};
