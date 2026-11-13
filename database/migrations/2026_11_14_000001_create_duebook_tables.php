<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------ the platform (admin side) */

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('interval', 10)->default('yearly'); // monthly, yearly, lifetime
            $table->unsignedInteger('max_clients')->nullable(); // empty = no limit
            $table->unsignedInteger('max_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // where the money of subscriptions is received (bank, cash, online wallet ...)
        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('bank');
            $table->char('currency', 3)->default('USD');
            $table->text('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->char('currency', 3)->default('USD');
            $table->json('currencies')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('trial'); // trial, active, suspended
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable(); // empty on an active business = no end
            $table->json('remind_days')->nullable();
            $table->unsignedTinyInteger('remind_hour')->default(8);
            $table->string('notify_email')->nullable();
            $table->date('last_reminded_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        /* ------------------------------------------------------------------ people who sign in */

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 10)->default('member'); // owner, member
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /* --------------------------------------------------- the books of a business (tenant_id) */

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income, expense
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
        });

        // what the business sells: services (hosting, a system, support) and products (digital goods)
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 10)->default('service'); // service, product
            $table->decimal('price', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('cycle', 12)->default('yearly');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // one thing a client pays for again and again (or once): "example.af hosting, 60 USD every year"
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('reference')->nullable(); // domain, cPanel user, contract number ...
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->string('cycle', 12)->default('yearly');
            $table->date('start_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->boolean('notify_client')->default(false);
            $table->string('status', 10)->default('active'); // active, paused, ended
            $table->string('file_path')->nullable(); // scan of the signed contract
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'next_due_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->date('paid_on');
            $table->date('period_due')->nullable(); // the due date this payment was for
            $table->boolean('advanced')->default(true); // did it move the contract to its next due date?
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // every income and expense; a payment of a contract writes its income here by itself
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income, expense
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'type', 'date']);
        });

        // people the owner lends to or borrows from
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // "gave": money went from me to the person. "received": money came from the person to me.
        // gave - received > 0: the person owes me. Below zero: I owe the person.
        Schema::create('person_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('direction', 10); // gave, received
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->date('date');
            $table->date('due_date')->nullable(); // when the money should come back or be paid back
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'person_id']);
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        // one row for every reminder email sent to a client, so it is never sent twice
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->smallInteger('days_before');
            $table->timestamps();
            $table->unique(['contract_id', 'due_date', 'days_before']);
        });
    }

    public function down(): void
    {
        foreach (['reminder_logs', 'notices', 'person_entries', 'people', 'transactions', 'payments', 'contracts', 'services', 'clients', 'categories', 'password_reset_tokens', 'users', 'subscriptions', 'tenants', 'platform_accounts', 'plans', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
