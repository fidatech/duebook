<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The yearly plan costs 199 USD. Only the seeded rows are touched, and only if nobody changed them. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('name', 'DueBook Yearly')->where('price', 200)->update(['price' => 199]);

        DB::table('platform_accounts')->where('name', 'HesabPay')->where('details', 'like', 'Open HesabPay, scan the QR code or send to the number above.%')
            ->update(['details' => "Pay 199 USD, or the equivalent in AFN, to HesabPay: scan the QR code or send to the number above.\nThen take a screenshot of the payment and add it below."]);
    }

    public function down(): void
    {
        DB::table('plans')->where('name', 'DueBook Yearly')->where('price', 199)->update(['price' => 200]);
    }
};
