<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A payment account can carry a QR code that businesses scan to pay (HesabPay, for example). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->string('number')->nullable(); // the account or wallet number to send the money to
            $table->string('qr_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropColumn(['number', 'qr_path']);
        });
    }
};
