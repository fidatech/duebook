<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_of_the_website_opens_for_a_visitor(): void
    {
        Plan::create(['name' => 'DueBook Yearly', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly']);
        PlatformAccount::create(['name' => 'HesabPay', 'type' => 'mobile', 'currency' => 'USD']);
        PlatformAccount::create(['name' => 'Binance (USDT)', 'type' => 'crypto', 'currency' => 'USD']);

        $this->get('/')->assertOk()
            ->assertSee('Know who owes you, and when it is due.')
            ->assertSee('What is DueBook?')
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"price":"199.00"', false)
            ->assertSee('index, follow', false)
            ->assertSee('Start your free trial');

        foreach (['/features', '/who-it-is-for', '/pricing', '/questions', '/contact', '/privacy', '/terms'] as $path) {
            $this->get($path)->assertOk()->assertSee('<link rel="canonical" href="'.url($path).'">', false)->assertSee('BreadcrumbList', false);
        }

        // the price and the ways to pay come from the system itself
        $this->get('/pricing')->assertOk()->assertSee('199 USD')->assertSee('HesabPay')->assertSee('Binance (USDT)')->assertSee('Payments are not refunded');
        $this->get('/questions')->assertOk()->assertSee('DueBook Yearly is 199 USD a year')->assertSee('You can pay by Binance (USDT) or HesabPay.');
    }

    public function test_search_engines_and_ai_assistants_get_their_files(): void
    {
        Plan::create(['name' => 'DueBook Yearly', 'price' => 199, 'currency' => 'USD', 'interval' => 'yearly']);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/pricing').'</loc>', false)->assertSee('<loc>'.url('/').'</loc>', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /')->assertSee('Disallow: /admin')->assertSee('Sitemap: '.url('/sitemap.xml'));
        $this->get('/llms.txt')->assertOk()->assertSee('# '.config('app.name'))->assertSee('199 USD a year')->assertSee('Questions and answers');

        // the system itself stays out of search engines
        $this->get('/login')->assertOk()->assertSee('noindex', false);
    }

    public function test_the_contact_form_sends_an_email_and_drops_messages_from_programs(): void
    {
        Mail::fake();
        Admin::create(['name' => 'System Admin', 'email' => 'admin@duebook.test', 'password' => 'password123']);

        $this->post('/contact', ['name' => 'Sara', 'email' => 'sara@example.com', 'message' => 'Can I use it for my hosting clients?'])->assertRedirect('/contact');
        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => $mail->hasTo('admin@duebook.test') && $mail->hasReplyTo('sara@example.com'));

        // a program fills the hidden box: it gets the same answer and nothing is sent
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap things here now', 'website' => 'http://spam.example'])->assertRedirect('/contact');
        Mail::assertSent(ContactMessage::class, 1);

        $this->post('/contact', ['name' => '', 'email' => 'x', 'message' => 'hi'])->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_the_site_follows_the_settings_and_the_signed_in_user(): void
    {
        Platform::set(['registration' => '0']);
        $this->get('/')->assertOk()->assertDontSee('Start your free trial')->assertSee('Sign in');

        Platform::set(['registration' => '1', 'trial_days' => 10]);
        $this->get('/pricing')->assertOk()->assertSee('free for 10 days');

        $this->signIn($this->business());
        $this->get('/')->assertOk()->assertSee('Open '.config('app.name'));
    }

    public function test_the_site_shows_the_free_plan_and_both_ways_to_pay(): void
    {
        Plan::create(['name' => 'Free', 'price' => 0, 'currency' => 'USD', 'interval' => 'lifetime', 'max_clients' => 10, 'max_users' => 1, 'sort' => 1]);
        Plan::create(['name' => 'Pro', 'price' => 4.99, 'currency' => 'USD', 'interval' => 'monthly', 'max_clients' => 200, 'sort' => 2]);
        Plan::create(['name' => 'Pro', 'price' => 47.90, 'currency' => 'USD', 'interval' => 'yearly', 'max_clients' => 200, 'sort' => 3]);

        $this->get('/pricing')->assertOk()
            ->assertSee('There are 2 plans: Free is free and Pro is 4.99 USD a month or 47.90 USD a year')
            ->assertSee('Monthly')->assertSee('save 20%')->assertSee('Get started free')->assertDontSee('free trial');
        $this->get('/')->assertOk()->assertSee('Get started free')->assertDontSee('Start your free trial');
    }
}
