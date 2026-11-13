<?php

namespace App\Console\Commands;

use App\Mail\ClientReminder;
use App\Mail\DuesDigest;
use App\Models\Contract;
use App\Models\Notice;
use App\Models\PersonEntry;
use App\Models\ReminderLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The daily reminders. Run every hour by the scheduler: a business gets its email once a day,
 * when its own clock reaches the hour chosen in its Settings.
 *
 * To the business: one email with what is due today, what is coming (on the days it chose,
 * for example 30, 7 and 1 day before) and, once a week, everything that is late.
 * To a client: one email per contract on those same days, only where the contract asks for it.
 */
class RemindDues extends Command
{
    protected $signature = 'duebook:remind {--tenant= : Only this business (id)} {--force : Send now, whatever the hour, even if already sent today}';

    protected $description = 'Send the reminder emails for due and late payments';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('status', '!=', Tenant::SUSPENDED)
            ->when($this->option('tenant'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($tenants as $tenant) {
            try {
                $this->remind($tenant);
            } catch (Throwable $e) {
                // one business with a wrong email address must not stop the others
                report($e);
                $this->error($tenant->name.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function remind(Tenant $tenant): void
    {
        $now = now($tenant->timezone ?: 'UTC');
        $today = $now->toDateString();
        $force = (bool) $this->option('force');

        if (! $tenant->isUsable()) {
            return;
        }
        if (! $force && ($now->hour < $tenant->remind_hour || $tenant->last_reminded_on?->toDateString() === $today)) {
            return;
        }

        $days = $tenant->remindDays();
        $dates = array_map(fn (int $d) => Carbon::parse($today)->addDays($d)->toDateString(), $days);

        $open = Contract::withoutGlobalScopes()->with('client')->where('tenant_id', $tenant->id)
            ->where('status', 'active')->whereNotNull('next_due_date');

        $coming = (clone $open)->whereIn('next_due_date', $dates)->orderBy('next_due_date')->get();
        $late = (clone $open)->where('next_due_date', '<', $today)->orderBy('next_due_date')->get();

        // money between people that should move today or on a reminder day
        $entries = PersonEntry::withoutGlobalScopes()->with(['person' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('tenant_id', $tenant->id)->whereIn('due_date', $dates)->orderBy('due_date')->get();

        // the late list is repeated once a week (and on any day something else is in the email)
        $weekly = $now->dayOfWeek === Carbon::SATURDAY;
        $send = $coming->isNotEmpty() || $entries->isNotEmpty() || ($late->isNotEmpty() && ($weekly || $force));

        if ($send) {
            if ($to = $tenant->reminderEmail()) {
                Mail::to($to)->send(new DuesDigest($tenant, $today, $coming, $late, $entries));
            }
            $count = $coming->count() + $late->count();
            foreach (User::where('tenant_id', $tenant->id)->where('is_active', true)->get() as $user) {
                Notice::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'title' => $late->isNotEmpty() ? __(':late late, :coming coming up', ['late' => $late->count(), 'coming' => $coming->count()]) : __(':count payments are coming up', ['count' => $count]),
                    'body' => $coming->take(3)->map(fn (Contract $c) => $c->client?->name)->filter()->implode(', ') ?: null,
                    'url' => route('dues.index'),
                ]);
            }
            $this->info($tenant->name.': reminder sent ('.$coming->count().' coming, '.$late->count().' late)');
        }

        $this->remindClients($tenant, $coming, $today);

        $tenant->update(['last_reminded_on' => $today]);
    }

    /** One email to the client of each contract that asks for it; never twice for the same day. */
    private function remindClients(Tenant $tenant, $coming, string $today): void
    {
        foreach ($coming as $contract) {
            $email = $contract->client?->email;
            if (! $contract->notify_client || ! $email) {
                continue;
            }
            $daysBefore = (int) $contract->daysLeft($today);
            $log = ReminderLog::firstOrCreate(
                ['contract_id' => $contract->id, 'due_date' => $contract->next_due_date->toDateString(), 'days_before' => $daysBefore],
                ['tenant_id' => $tenant->id],
            );
            if (! $log->wasRecentlyCreated) {
                continue;
            }
            try {
                Mail::to($email)->send(new ClientReminder($tenant, $contract, $daysBefore));
            } catch (Throwable $e) {
                $log->delete(); // try again next time
                report($e);
            }
        }
    }
}
