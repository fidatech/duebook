<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_business_signs_up_and_lands_on_its_dashboard(): void
    {
        $this->post('/register', [
            'business' => 'Fida Technologies',
            'name' => 'Fida',
            'email' => 'Fida@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'currency' => 'usd',
            'timezone' => 'Asia/Kabul',
        ])->assertRedirect('/dashboard');

        $tenant = Tenant::firstOrFail();
        $this->assertSame('trial', $tenant->status);
        $this->assertSame('USD', $tenant->currency);
        $this->assertSame('fida@example.com', User::firstOrFail()->email);
        $this->assertTrue(User::firstOrFail()->isOwner());
        $this->assertGreaterThan(0, Category::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $this->get('/dashboard')->assertOk()->assertSee('Fida Technologies');
    }

    public function test_guests_are_sent_to_the_right_sign_in_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_a_business_user_cannot_open_the_admin_panel(): void
    {
        $this->signIn($this->business());

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/tenants')->assertRedirect('/admin/login');
    }

    public function test_an_ended_trial_shows_the_renew_page(): void
    {
        $tenant = $this->business();
        $tenant->update(['trial_ends_at' => '2026-11-01']);
        $this->signIn($tenant);

        $this->get('/dashboard')->assertStatus(402)->assertSee('free trial ended');
    }

    public function test_a_member_cannot_open_team_or_settings(): void
    {
        $tenant = $this->business();
        $member = User::create(['tenant_id' => $tenant->id, 'name' => 'Member', 'email' => 'member@acme.test', 'password' => 'password123', 'role' => 'member']);
        $this->actingAs($member, 'web');

        $this->get('/team')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/clients')->assertOk();
    }
}
