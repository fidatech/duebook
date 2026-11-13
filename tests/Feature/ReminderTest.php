<?php

namespace Tests\Feature;

use App\Mail\ClientReminder;
use App\Mail\DuesDigest;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_daily_email_goes_out_once_and_the_client_is_told_only_when_asked(): void
    {
        Mail::fake();
        $tenant = $this->business();

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Ahmad', 'email' => 'ahmad@example.com']);
        $base = ['tenant_id' => $tenant->id, 'client_id' => $client->id, 'amount' => 60, 'currency' => 'USD', 'cycle' => 'yearly', 'status' => 'active'];
        Contract::create($base + ['title' => 'In 7 days, client told', 'next_due_date' => '2026-11-21', 'notify_client' => true]);
        Contract::create($base + ['title' => 'In 7 days, client not told', 'next_due_date' => '2026-11-21']);
        Contract::create($base + ['title' => 'In 10 days', 'next_due_date' => '2026-11-24', 'notify_client' => true]);
        Contract::create($base + ['title' => 'Late', 'next_due_date' => '2026-11-01']);

        $this->artisan('duebook:remind')->assertSuccessful();

        Mail::assertSent(DuesDigest::class, fn (DuesDigest $mail) => $mail->hasTo('owner@acme.test') && $mail->coming->count() === 2 && $mail->late->count() === 1);
        Mail::assertSent(ClientReminder::class, 1);
        Mail::assertSent(ClientReminder::class, fn (ClientReminder $mail) => $mail->hasTo('ahmad@example.com') && $mail->daysBefore === 7);
        $this->assertSame(1, Notice::withoutGlobalScopes()->count());

        // the next hour of the same day: nothing more
        $this->artisan('duebook:remind')->assertSuccessful();
        Mail::assertSent(DuesDigest::class, 1);
        Mail::assertSent(ClientReminder::class, 1);
    }

    public function test_nothing_is_sent_before_the_hour_the_business_chose(): void
    {
        Mail::fake();
        $tenant = $this->business();
        $tenant->update(['remind_hour' => 18]);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Ahmad']);
        Contract::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'title' => 'Today', 'amount' => 60, 'currency' => 'USD', 'cycle' => 'yearly', 'status' => 'active', 'next_due_date' => '2026-11-14']);

        $this->artisan('duebook:remind')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('duebook:remind', ['--force' => true])->assertSuccessful();
        Mail::assertSent(DuesDigest::class, 1);
    }
}
