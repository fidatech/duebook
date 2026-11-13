<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenants;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // a fixed day, so tests do not depend on the clock (a Saturday: the weekly "late" email goes out)
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
    }

    /** A new business with its owner. */
    protected function business(string $name = 'Acme', string $email = 'owner@acme.test'): Tenant
    {
        return Tenants::create([
            'name' => $name,
            'owner_name' => 'Owner of '.$name,
            'owner_email' => $email,
            'owner_password' => 'password123',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
    }

    /** Sign in as the owner of a business. */
    protected function signIn(Tenant $tenant): User
    {
        $user = $tenant->owner();
        $this->actingAs($user, 'web');

        return $user;
    }

    protected function systemAdmin(): Admin
    {
        $admin = Admin::firstOrCreate(['email' => 'admin@duebook.test'], ['name' => 'System Admin', 'password' => 'password123']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }
}
