<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_opens_a_business_and_its_owner_can_sign_in(): void
    {
        $this->systemAdmin();
        $plan = Plan::create(['name' => 'Starter', 'price' => 60, 'currency' => 'USD', 'interval' => 'yearly']);

        $this->get('/admin')->assertOk();
        $this->get('/admin/tenants/create')->assertOk();
        $this->post('/admin/tenants', [
            'name' => 'Fida Technologies', 'currency' => 'usd', 'timezone' => 'Asia/Kabul', 'plan_id' => $plan->id, 'status' => 'active',
            'owner_name' => 'Fida', 'owner_email' => 'fida@example.com', 'owner_password' => 'password123',
        ])->assertRedirect();

        $tenant = Tenant::firstOrFail();
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->subscription_ends_at);
        $this->assertTrue($tenant->isUsable());

        $this->get('/admin/tenants')->assertOk()->assertSee('Fida Technologies');
        $this->get('/admin/tenants/'.$tenant->id.'/edit')->assertOk();

        $this->post('/login', ['email' => 'fida@example.com', 'password' => 'password123'])->assertRedirect('/dashboard');
    }

    public function test_a_subscription_sets_the_plan_and_the_end_date_of_the_business(): void
    {
        $this->systemAdmin();
        $tenant = $this->business();
        $plan = Plan::create(['name' => 'Business', 'price' => 150, 'currency' => 'USD', 'interval' => 'yearly']);
        $account = PlatformAccount::create(['name' => 'Bank', 'type' => 'bank', 'currency' => 'USD']);

        $this->get('/admin/subscriptions/create?tenant='.$tenant->id)->assertOk();
        $this->post('/admin/subscriptions', [
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'starts_on' => '2026-11-14', 'ends_on' => '2027-11-14',
            'amount' => '150', 'currency' => 'usd', 'paid_on' => '2026-11-14', 'platform_account_id' => $account->id,
        ])->assertRedirect('/admin/subscriptions');

        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertSame($plan->id, $tenant->plan_id);
        $this->assertSame('2027-11-14', $tenant->subscription_ends_at->toDateString());

        $this->get('/admin/subscriptions')->assertOk()->assertSee('150.00 USD');
        $this->get('/admin/accounts')->assertOk()->assertSee('Bank');
        $this->get('/admin/plans')->assertOk();
        $this->get('/admin/admins')->assertOk();
    }

    public function test_the_statement_of_an_account_shows_where_the_money_came_from(): void
    {
        $this->systemAdmin();
        $tenant = $this->business('Kabul Traders', 'owner@kabul.test');
        $plan = Plan::create(['name' => 'DueBook Yearly', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly']);
        $account = PlatformAccount::create(['name' => 'HesabPay', 'type' => 'mobile', 'currency' => 'USD']);
        $base = ['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'starts_on' => '2026-11-14', 'ends_on' => '2027-11-14', 'amount' => '199', 'currency' => 'USD', 'paid_on' => '2026-11-14'];

        // a confirmed payment must say which account it came into
        $this->post('/admin/subscriptions', $base)->assertSessionHasErrors('platform_account_id');
        $this->post('/admin/subscriptions', $base + ['platform_account_id' => $account->id, 'reference' => 'TX-9'])->assertRedirect();

        $this->get('/admin/accounts')->assertOk()->assertSee('Statement');
        $this->get('/admin/accounts/'.$account->id)->assertOk()->assertSee('Kabul Traders')->assertSee('TX-9')->assertSee('199.00 USD');
        $this->get('/admin/accounts/'.$account->id.'?from=2027-01-01')->assertOk()->assertSee('Nothing found');
        $this->get('/admin/accounts/'.$account->id.'?period=last_year')->assertOk()->assertSee('Nothing found');
        $this->get('/admin/accounts/'.$account->id.'?q=Kabul')->assertOk()->assertSee('TX-9')->assertSee('1 payment');
        $this->get('/admin/accounts/'.$account->id.'?q=nobody')->assertOk()->assertSee('Nothing found');
        $csv = $this->get('/admin/accounts/'.$account->id.'?export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Kabul Traders', $csv);
        $this->assertStringContainsString('199.00', $csv);
        $this->get('/admin')->assertOk()->assertSee('Latest payments')->assertSee('Kabul Traders');
        $this->get('/admin/tenants?status=ended')->assertOk();
        $this->get('/admin/subscriptions?account='.$account->id)->assertOk()->assertSee('Kabul Traders');
    }

    public function test_an_administrator_changes_his_own_password(): void
    {
        $admin = $this->systemAdmin();

        $this->get('/admin/profile')->assertOk();
        $this->put('/admin/profile', ['name' => 'Fida', 'email' => 'admin@duebook.test', 'current_password' => 'wrong', 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1'])->assertSessionHasErrors('current_password');
        $this->put('/admin/profile', ['name' => 'Fida', 'email' => 'admin@duebook.test', 'current_password' => 'password123', 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1'])->assertSessionHasNoErrors();

        $this->assertSame('Fida', $admin->fresh()->name);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword1', $admin->fresh()->password));
    }
}
