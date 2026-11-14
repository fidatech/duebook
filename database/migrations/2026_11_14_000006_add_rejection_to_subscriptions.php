<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An administrator can reject an "I have paid" request, with a reason picked from a list. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 40)->nullable(); // a key of config('duebook.reject_reasons')
            $table->string('reject_note')->nullable(); // optional extra words from the administrator
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'reject_reason', 'reject_note']);
        });
    }
};
