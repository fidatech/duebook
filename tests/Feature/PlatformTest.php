<?php

namespace Tests\Feature;

use App\Mail\BusinessMail;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_saved_in_the_admin_panel_are_used(): void
    {
        $this->systemAdmin();

        $this->get('/admin/settings')->assertOk();
        $this->put('/admin/settings', ['trial_days' => 3, 'renew_open_days' => 30, 'registration' => 0, 'pay_note' => 'or the same in AFN', 'renew_warn_days' => '7, 1', 'trial_warn_days' => '2', 'receipt_prefix' => 'INV-'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-17', $this->business()->trial_ends_at->toDateString());
        $this->assertDatabaseHas('admin_logs', ['action' => 'changed settings']);

        auth('admin')->logout();
        $this->get('/register')->assertNotFound();
    }

    public function test_a_confirmed_payment_gets_a_receipt_number_an_email_and_a_log_line(): void
    {
        Mail::fake();
        $this->systemAdmin();
        $tenant = $this->business();
        $other = $this->business('Other', 'owner@other.test');
        $plan = Plan::create(['name' => 'DueBook Yearly', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly']);
        $account = PlatformAccount::create(['name' => 'HesabPay', 'type' => 'mobile', 'currency' => 'USD']);

        $this->post('/admin/subscriptions', ['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'starts_on' => '2026-11-14', 'ends_on' => '2027-11-14', 'amount' => '199', 'currency' => 'USD', 'paid_on' => '2026-11-14', 'platform_account_id' => $account->id])->assertRedirect();

        $subscription = Subscription::firstOrFail();
        $this->assertSame(1, $subscription->receipt_no);
        $this->assertSame('DB-00001', $subscription->receiptNumber());
        Mail::assertSent(BusinessMail::class, fn (BusinessMail $mail) => $mail->hasTo('owner@acme.test') && $mail->heading === 'Your payment is confirmed');
        $this->assertDatabaseHas('admin_logs', ['action' => 'confirmed payment', 'subject' => 'Acme']);

        $this->get('/admin/subscriptions/'.$subscription->id.'/print')->assertOk()->assertSee('DB-00001')->assertSee('Acme');
        $this->get('/admin/logs')->assertOk()->assertSee('Confirmed payment');

        // the business opens its own receipt; another business cannot
        $this->signIn($tenant);
        $this->get('/subscription')->assertOk()->assertSee('DB-00001');
        $this->get('/subscription/receipt/'.$subscription->id)->assertOk()->assertSee('199.00 USD');
        $this->signIn($other);
        $this->get('/subscription/receipt/'.$subscription->id)->assertNotFound();
    }

    public function test_expenses_give_the_profit_and_the_balance_of_an_account(): void
    {
        $this->systemAdmin();
        $account = PlatformAccount::create(['name' => 'HesabPay', 'type' => 'mobile', 'currency' => 'USD']);

        $this->get('/admin/expenses/create')->assertOk();
        $this->post('/admin/expenses', ['date' => '2026-11-10', 'category' => 'hosting', 'amount' => '40', 'currency' => 'usd', 'platform_account_id' => $account->id, 'description' => 'VPS'])->assertRedirect('/admin/expenses');

        $this->get('/admin/expenses')->assertOk()->assertSee('VPS')->assertSee('40.00 USD');
        $this->get('/admin/reports')->assertOk()->assertSee('Servers and hosting')->assertSee('-40.00');
        $this->get('/admin/accounts/'.$account->id)->assertOk()->assertSee('VPS')->assertSee('-40.00 USD');
    }

    public function test_roles_limit_what_an_administrator_can_do(): void
    {
        $tenant = $this->business();
        $viewer = Admin::create(['name' => 'Viewer', 'email' => 'viewer@duebook.test', 'password' => 'password123', 'role' => 'viewer']);
        $manager = Admin::create(['name' => 'Manager', 'email' => 'manager@duebook.test', 'password' => 'password123', 'role' => 'manager']);

        $this->actingAs($viewer, 'admin');
        $this->get('/admin')->assertOk()->assertDontSee('Activity log');
        $this->get('/admin/tenants')->assertOk();
        $this->from('/admin/plans')->post('/admin/plans', ['name' => 'X', 'interval' => 'yearly', 'price' => 1, 'currency' => 'USD', 'sort' => 1])->assertRedirect('/admin/plans')->assertSessionHas('danger');
        $this->assertSame(0, Plan::count());
        $this->get('/admin/settings')->assertForbidden();

        $this->actingAs($manager, 'admin');
        $this->post('/admin/plans', ['name' => 'X', 'interval' => 'yearly', 'price' => 1, 'currency' => 'USD', 'sort' => 1])->assertRedirect('/admin/plans');
        $this->assertSame(1, Plan::count());
        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/logs')->assertForbidden();
        $this->delete('/admin/tenants/'.$tenant->id, ['confirm' => $tenant->name])->assertForbidden();
        $this->get('/admin/profile')->assertOk();

        $this->assertSame(1, AdminLog::where('action', 'added plan')->count());
    }

    public function test_a_business_is_warned_once_before_its_trial_ends(): void
    {
        Mail::fake();
        $tenant = $this->business(); // the trial ends on 21 Nov
        $this->business('Far away', 'owner@far.test')->update(['trial_ends_at' => '2026-12-20']);

        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'UTC')); // two days before
        $this->artisan('duebook:lifecycle')->assertSuccessful();
        $this->artisan('duebook:lifecycle')->assertSuccessful();

        Mail::assertSent(BusinessMail::class, 1);
        Mail::assertSent(BusinessMail::class, fn (BusinessMail $mail) => $mail->hasTo('owner@acme.test') && $mail->heading === 'Your free trial ends in 2 days');

        $this->travelTo(Carbon::parse('2026-11-22 10:00:00', 'UTC')); // the day after it ended
        $this->artisan('duebook:lifecycle')->assertSuccessful();
        Mail::assertSent(BusinessMail::class, fn (BusinessMail $mail) => $mail->heading === 'Your free trial has ended');
        $this->assertSame(2, \App\Models\TenantMail::where('tenant_id', $tenant->id)->count());
    }
}
