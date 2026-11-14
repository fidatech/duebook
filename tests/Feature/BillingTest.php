<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Mail\PaymentRequested;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_business_gets_seven_free_days_and_sees_the_price(): void
    {
        Plan::create(['name' => 'DueBook Yearly', 'price' => 200, 'currency' => 'USD', 'interval' => 'yearly']);
        PlatformAccount::create(['name' => 'AIB Bank', 'type' => 'bank', 'currency' => 'USD', 'details' => 'Account 123']);
        $tenant = $this->business();
        $this->signIn($tenant);

        $this->assertSame('2026-11-21', $tenant->trial_ends_at->toDateString());
        $this->assertSame(7, $tenant->daysLeft());

        $this->get('/subscription')->assertOk()->assertSee('200 USD')->assertSee('7 days of free trial left')->assertSee('AIB Bank')->assertSee('I have paid');
        $this->get('/dashboard')->assertOk()->assertSee('7 days of your free trial are left.');
    }

    public function test_a_business_says_it_paid_and_becomes_active_only_when_an_administrator_confirms(): void
    {
        Mail::fake();
        Storage::fake('local');
        Admin::create(['name' => 'System Admin', 'email' => 'admin@duebook.test', 'password' => 'password123']);
        $shot = fn () => UploadedFile::fake()->create('payment.png', 120, 'image/png');

        $plan = Plan::create(['name' => 'DueBook Yearly', 'price' => 200, 'currency' => 'USD', 'interval' => 'yearly']);
        $account = PlatformAccount::create(['name' => 'HesabPay', 'type' => 'mobile', 'currency' => 'USD', 'number' => '+93784006200']);
        $tenant = $this->business();
        $tenant->update(['trial_ends_at' => '2026-11-01']); // the trial is over
        $this->signIn($tenant);

        // the page to pay on stays open after the trial
        $this->get('/dashboard')->assertStatus(402)->assertSee('See the plan and pay');
        $this->get('/subscription')->assertOk()->assertSee('Your free trial has ended')->assertSee('+93784006200');

        // the screenshot is a must
        $this->post('/subscription', ['sent_on' => '2026-11-14'])->assertSessionHasErrors('receipt');

        $this->post('/subscription', ['sent_on' => '2026-11-14', 'platform_account_id' => $account->id, 'reference' => 'TX-77', 'receipt' => $shot()])->assertRedirect('/subscription');

        // the platform owner is told by email, with the screenshot
        Mail::assertSent(PaymentRequested::class, fn (PaymentRequested $mail) => $mail->hasTo('admin@duebook.test') && count($mail->attachments()) === 1);

        $request = Subscription::firstOrFail();
        $this->assertTrue($request->requested);
        $this->assertNull($request->paid_on);
        $this->assertEquals(200, $request->amount);
        $this->assertSame('2026-11-14', $request->starts_on->toDateString());
        $this->assertSame('2027-11-14', $request->ends_on->toDateString());
        $this->assertFalse($tenant->fresh()->isUsable());

        $this->get('/subscription')->assertOk()->assertSee('Your payment is being checked');
        $this->post('/subscription', ['sent_on' => '2026-11-14', 'receipt' => $shot()])->assertSessionHas('danger');
        $this->assertSame(1, Subscription::count());
        Storage::disk('local')->assertExists(Subscription::firstOrFail()->receipt_path);

        // the administrator sees the request and confirms it
        $this->systemAdmin();
        $this->get('/admin')->assertOk()->assertSee('1 business says it has paid');
        $this->get('/admin/subscriptions/'.$request->id.'/edit?confirm=1')->assertOk();
        $this->put('/admin/subscriptions/'.$request->id, [
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'starts_on' => '2026-11-14', 'ends_on' => '2027-11-14',
            'amount' => '200', 'currency' => 'USD', 'paid_on' => '2026-11-14', 'platform_account_id' => $account->id, 'reference' => 'TX-77',
        ])->assertRedirect('/admin/subscriptions');

        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertTrue($tenant->isUsable());
        $this->assertSame('2027-11-14', $tenant->subscription_ends_at->toDateString());
    }

    public function test_an_unpaid_subscription_written_by_an_administrator_does_not_open_the_business(): void
    {
        $this->systemAdmin();
        $plan = Plan::create(['name' => 'DueBook Yearly', 'price' => 200, 'currency' => 'USD', 'interval' => 'yearly']);
        $tenant = $this->business();

        $this->post('/admin/subscriptions', ['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'starts_on' => '2026-11-14', 'ends_on' => '2027-11-14', 'amount' => '200', 'currency' => 'USD'])->assertRedirect();

        $this->assertSame('trial', $tenant->fresh()->status);
    }
}
