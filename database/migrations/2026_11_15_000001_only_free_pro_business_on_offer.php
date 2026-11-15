<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The plans on offer are exactly these five, at these prices:
 *
 *   Free 0 · Pro 4.99 a month or 47.90 a year · Business 14.99 a month or 143.90 a year
 *
 * The migration before this one left alone every plan somebody had edited, so an installation
 * whose earlier plans had other prices (a Starter at 69, a Business at 199 a year) still showed
 * those on the website beside the new ones, and did not get the new yearly Business plan at all.
 *
 * Here a plan on offer with one of these names but another price, and the earlier plans
 * (Starter, DueBook Yearly, Unlimited) whatever their price, are switched off; they stay in
 * the records, so a business that is on one keeps it until it ends. A missing plan is added.
 * A new installation has no plans yet and gets them from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('plans')->doesntExist()) {
            return;
        }
        $this->report('before');

        $plans = [
            ['Free', 'lifetime', 0, 10, 1, 1, 'Essential features to get started.'],
            ['Pro', 'monthly', 4.99, 200, 3, 2, 'More power and flexibility.'],
            ['Pro', 'yearly', 47.90, 200, 3, 3, 'More power and flexibility.'],
            ['Business', 'monthly', 14.99, null, null, 4, 'For teams and businesses.'],
            ['Business', 'yearly', 143.90, null, null, 5, 'For teams and businesses.'],
        ];
        $keep = [];
        foreach ($plans as [$name, $interval, $price, $clients, $users, $sort, $description]) {
            $id = DB::table('plans')->where('name', $name)->where('interval', $interval)->where('is_active', true)
                ->whereBetween('price', [$price - 0.001, $price + 0.001])->orderBy('id')->value('id');
            $keep[] = $id ?: DB::table('plans')->insertGetId([
                'name' => $name, 'description' => $description, 'price' => $price, 'currency' => 'USD', 'interval' => $interval,
                'max_clients' => $clients, 'max_users' => $users, 'is_active' => true, 'sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('plans')->where('id', end($keep))->update(['sort' => $sort]);
        }

        DB::table('plans')->where('is_active', true)->whereNotIn('id', $keep)
            ->whereIn('name', ['Free', 'Pro', 'Business', 'Starter', 'DueBook Yearly', 'Unlimited'])
            ->update(['is_active' => false, 'sort' => 99, 'updated_at' => now()]);

        $this->report('after');
    }

    public function down(): void
    {
        //
    }

    /** The plans on offer, written to the output of `artisan migrate`, so the deploy log shows what was changed. */
    private function report(string $when): void
    {
        if (app()->runningUnitTests() || ! app()->runningInConsole()) {
            return;
        }
        $line = DB::table('plans')->where('is_active', true)->orderBy('sort')->orderBy('id')->get()
            ->map(fn ($plan) => $plan->name.' '.$plan->interval.' '.$plan->price)->implode(', ');
        echo "  plans on offer {$when}: {$line}\n";
    }
};
