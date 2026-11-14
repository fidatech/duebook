<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin side grows up: settings kept in the database, an activity log, roles for
 * administrators, the platform's own expenses, a record of the emails sent to businesses,
 * and a number on every confirmed payment (its receipt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name')->nullable(); // kept even when the administrator is deleted later
            $table->string('action', 60);
            $table->string('subject')->nullable();
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->string('role', 10)->default('super'); // super, manager, viewer
        });

        Schema::create('platform_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index('date');
        });

        // one row for every automatic email to a business, so the same one never goes twice in a day
        Schema::create('tenant_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->date('on_date');
            $table->timestamps();
            $table->unique(['tenant_id', 'kind', 'on_date']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('receipt_no')->nullable()->unique();
        });

        // payments confirmed before today get their numbers in the order they were paid
        $number = 0;
        foreach (DB::table('subscriptions')->whereNotNull('paid_on')->orderBy('paid_on')->orderBy('id')->pluck('id') as $id) {
            DB::table('subscriptions')->where('id', $id)->update(['receipt_no' => ++$number]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('receipt_no'));
        Schema::dropIfExists('tenant_mails');
        Schema::dropIfExists('platform_expenses');
        Schema::table('admins', fn (Blueprint $table) => $table->dropColumn('role'));
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('settings');
    }
};
