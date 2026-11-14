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
        $this->post('/subscription', ['plan_id' => Plan::firstOrFail()->id, 'sent_on' => '2026-11-14'])->assertSessionHasErrors('receipt');

        $this->post('/subscription', ['plan_id' => Plan::firstOrFail()->id, 'sent_on' => '2026-11-14', 'platform_account_id' => $account->id, 'reference' => 'TX-77', 'receipt' => $shot()])->assertRedirect('/subscription');

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
        $this->post('/subscription', ['plan_id' => Plan::firstOrFail()->id, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertSessionHas('danger');
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

    public function test_a_rejected_payment_tells_the_business_why_and_lets_it_send_again(): void
    {
        Mail::fake();
        Storage::fake('local');
        Plan::create(['name' => 'DueBook Yearly', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly']);
        $tenant = $this->business();
        $owner = $this->signIn($tenant);
        $shot = fn () => UploadedFile::fake()->create('payment.png', 120, 'image/png');

        $this->post('/subscription', ['plan_id' => Plan::firstOrFail()->id, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertRedirect('/subscription');
        $request = Subscription::firstOrFail();

        $this->systemAdmin();
        $this->get('/admin/subscriptions?paid=no')->assertOk()->assertSee('Reject');
        $this->get('/admin/subscriptions/'.$request->id.'/reject')->assertOk()->assertSee('Amount is less than the price');
        // a reason must be picked from the list
        $this->post('/admin/subscriptions/'.$request->id.'/reject', ['reject_reason' => 'because'])->assertSessionHasErrors('reject_reason');
        $this->post('/admin/subscriptions/'.$request->id.'/reject', ['reject_reason' => 'less_amount'])->assertRedirect();

        $this->assertTrue($request->fresh()->isRejected());
        $this->assertSame('trial', $tenant->fresh()->status);
        Mail::assertSent(\App\Mail\PaymentRejected::class, fn ($mail) => $mail->hasTo('owner@acme.test'));
        $this->get('/admin')->assertOk()->assertDontSee('says it has paid');
        $this->get('/admin/subscriptions?paid=rejected')->assertOk()->assertSee('Amount is less than the price');

        // the business sees the reason and can send a new screenshot
        $this->actingAs($owner, 'web');
        $this->get('/subscription')->assertOk()->assertSee('Your payment was not accepted')->assertSee('less than the price of the plan')->assertSee('I have paid');
        $this->post('/subscription', ['plan_id' => Plan::firstOrFail()->id, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertRedirect('/subscription');
        $this->assertSame(2, Subscription::count());
        $this->get('/subscription')->assertOk()->assertSee('Your payment is being checked')->assertDontSee('Each payment needs its own screenshot')->assertDontSee('Please pay the rest');
    }

    public function test_a_business_sees_all_plans_and_pays_for_the_one_it_chooses(): void
    {
        Mail::fake();
        Storage::fake('local');
        $starter = Plan::create(['name' => 'Starter', 'price' => 99, 'currency' => 'USD', 'interval' => 'yearly', 'max_clients' => 1, 'sort' => 1]);
        $pro = Plan::create(['name' => 'Professional', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly', 'sort' => 2]);
        Plan::create(['name' => 'Hidden', 'price' => 5, 'currency' => 'USD', 'interval' => 'yearly', 'is_active' => false]);
        $tenant = $this->business(); // starts on the first plan: Starter
        $this->signIn($tenant);

        $this->get('/subscription')->assertOk()
            ->assertSee('Choose your plan')->assertSee('Starter')->assertSee('Professional')->assertDontSee('Hidden')
            ->assertSee('Your current plan')->assertSee('Pay 99 USD');
        $this->get('/subscription?plan='.$pro->id)->assertOk()->assertSee('Pay 199 USD')->assertSee('Selected: pay below');

        // a plan that is not offered cannot be paid for
        $shot = fn () => UploadedFile::fake()->create('payment.png', 120, 'image/png');
        $this->post('/subscription', ['plan_id' => 999, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertSessionHasErrors('plan_id');

        // a plan too small for the business cannot be chosen
        \App\Models\Client::create(['name' => 'One']);
        \App\Models\Client::create(['name' => 'Two']);
        $this->get('/subscription')->assertOk()->assertSee('You have 2 clients and this plan allows 1.');
        $this->post('/subscription', ['plan_id' => $starter->id, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertSessionHas('danger');
        $this->assertSame(0, Subscription::count());

        $this->post('/subscription', ['plan_id' => $pro->id, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertRedirect('/subscription');
        $request = Subscription::firstOrFail();
        $this->assertSame($pro->id, $request->plan_id);
        $this->assertEquals(199, $request->amount);
        $this->get('/subscription')->assertOk()->assertSee('for Professional');
    }

    public function test_a_paid_plan_can_only_be_renewed_or_changed_near_its_end_and_the_new_plan_starts_after_it(): void
    {
        Mail::fake();
        Storage::fake('local');
        $basic = Plan::create(['name' => 'Basic', 'price' => 99, 'currency' => 'USD', 'interval' => 'yearly', 'sort' => 1]);
        $pro = Plan::create(['name' => 'Professional', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly', 'sort' => 2]);
        $account = PlatformAccount::create(['name' => 'HesabPay', 'type' => 'mobile', 'currency' => 'USD']);
        $tenant = $this->business();
        // Basic is paid from today for a year
        Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $basic->id, 'platform_account_id' => $account->id, 'amount' => 99, 'currency' => 'USD', 'starts_on' => '2026-11-14', 'ends_on' => '2027-11-14', 'paid_on' => '2026-11-14'])->apply();
        $owner = $this->signIn($tenant);
        $shot = fn () => UploadedFile::fake()->create('payment.png', 120, 'image/png');

        // far from the end: every plan is shown, none can be paid for
        $this->get('/subscription?plan='.$pro->id)->assertOk()
            ->assertSee('Basic')->assertSee('Professional')->assertSee('Your current plan')
            ->assertSee('Payments are not refunded')->assertSee('You can change to this plan from 15 Oct 2027.')->assertDontSee('I have paid');
        $this->post('/subscription', ['plan_id' => $pro->id, 'sent_on' => '2026-11-14', 'receipt' => $shot()])->assertSessionHas('danger');
        $this->assertSame(1, Subscription::count());

        // in the last 30 days: Professional is chosen for the next period
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-11-01 10:00:00', 'UTC'));
        $this->get('/subscription?plan='.$pro->id)->assertOk()->assertSee('Pay 199 USD')->assertSee('I have paid')->assertSee('starts the day after');
        $this->post('/subscription', ['plan_id' => $pro->id, 'sent_on' => '2027-11-01', 'receipt' => $shot()])->assertRedirect('/subscription');
        $request = Subscription::orderByDesc('id')->firstOrFail();
        $this->assertSame('2027-11-15', $request->starts_on->toDateString());
        $this->assertSame('2028-11-15', $request->ends_on->toDateString());

        // confirmed: paid until the new end, but still on Basic until the new period starts
        $this->systemAdmin();
        $this->put('/admin/subscriptions/'.$request->id, ['tenant_id' => $tenant->id, 'plan_id' => $pro->id, 'starts_on' => '2027-11-15', 'ends_on' => '2028-11-15', 'amount' => '199', 'currency' => 'USD', 'paid_on' => '2027-11-01', 'platform_account_id' => $account->id])->assertRedirect();
        $this->assertSame($basic->id, $tenant->fresh()->plan_id);
        $this->assertSame('2028-11-15', $tenant->fresh()->subscription_ends_at->toDateString());

        // the day the new period starts, the plan changes by itself
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-11-15 10:00:00', 'UTC'));
        $this->artisan('duebook:lifecycle')->assertSuccessful();
        $this->assertSame($pro->id, $tenant->fresh()->plan_id);
    }
}
