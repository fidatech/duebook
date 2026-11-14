<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Opens a new business: the business itself, its owner, and the categories it starts with. */
class Tenants
{
    /**
     * @param  array{name: string, owner_name: string, owner_email: string, owner_password: string, currency?: string, timezone?: string, plan_id?: ?int, status?: string, subscription_ends_at?: ?string, country?: ?string, phone?: ?string}  $data
     */
    public static function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $currency = strtoupper($data['currency'] ?? 'USD');
            $planId = $data['plan_id'] ?? Plan::where('is_active', true)->orderBy('sort')->orderBy('id')->value('id');
            // a business that starts on the free plan has no trial: it is active with no end date
            $free = (bool) Plan::find($planId)?->isFree();
            $status = $data['status'] ?? ($free ? Tenant::ACTIVE : Tenant::TRIAL);

            $tenant = Tenant::create([
                'name' => $data['name'],
                'email' => strtolower($data['owner_email']),
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'currency' => $currency,
                'currencies' => [$currency],
                'plan_id' => $planId,
                'status' => $status,
                'trial_ends_at' => $status === Tenant::TRIAL ? now()->addDays((int) platform('trial_days'))->toDateString() : null,
                'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
                'remind_days' => config('duebook.remind_days'),
            ]);

            User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'email' => strtolower($data['owner_email']),
                'password' => $data['owner_password'],
                'role' => User::OWNER,
            ]);

            foreach (config('duebook.default_categories') as $type => $names) {
                foreach ($names as $name) {
                    Category::create(['tenant_id' => $tenant->id, 'type' => $type, 'name' => $name]);
                }
            }

            return $tenant;
        });
    }
}
