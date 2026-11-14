<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Lifecycle as Emails;
use Illuminate\Console\Command;
use Throwable;

/**
 * Emails from the platform to its businesses: the trial is ending, the subscription is
 * ending, it has ended. Run every hour by the scheduler; a business is looked at from
 * nine in the morning of its own time zone, and each email goes once.
 */
class Lifecycle extends Command
{
    protected $signature = 'duebook:lifecycle {--tenant= : Only this business (id)} {--force : Whatever the hour}';

    protected $description = 'Email businesses whose trial or subscription is ending or has ended';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('status', '!=', Tenant::SUSPENDED)
            ->when($this->option('tenant'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($tenants as $tenant) {
            if (! $this->option('force') && now($tenant->timezone ?: 'UTC')->hour < 9) {
                continue;
            }
            try {
                if ($kind = Emails::check($tenant)) {
                    $this->info($tenant->name.': '.$kind);
                }
            } catch (Throwable $e) {
                report($e);
                $this->error($tenant->name.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
