<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A business can now say "I have paid" on its Subscription page; an administrator confirms it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('requested')->default(false); // sent by the business itself, not written by an administrator
            $table->date('sent_on')->nullable(); // the day the business says it sent the money
            $table->string('reference')->nullable(); // transfer or receipt number given by the business
            $table->string('receipt_path')->nullable(); // photo or PDF of the receipt
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['requested', 'sent_on', 'reference', 'receipt_path']);
        });
    }
};
