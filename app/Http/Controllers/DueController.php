<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The main screen of the system: who should pay, how much, and when. */
class DueController extends Controller
{
    public const TABS = ['overdue' => 'Late', 'week' => 'Next 7 days', 'month' => 'Next 30 days', 'quarter' => 'Next 90 days', 'all' => 'All'];

    public function index(Request $request): View
    {
        $today = today_iso();
        $day = fn (int $days) => Carbon::parse($today)->addDays($days)->toDateString();
        $ranges = [
            'overdue' => fn (Builder $q) => $q->where('next_due_date', '<', $today),
            'week' => fn (Builder $q) => $q->whereBetween('next_due_date', [$today, $day(7)]),
            'month' => fn (Builder $q) => $q->whereBetween('next_due_date', [$today, $day(30)]),
            'quarter' => fn (Builder $q) => $q->whereBetween('next_due_date', [$today, $day(90)]),
            'all' => fn (Builder $q) => $q,
        ];

        // the currency chips narrow the tabs, the totals and the list to one currency
        $currency = picked_currency();
        $open = fn () => Contract::open()->when($currency, fn (Builder $q) => $q->where('currency', $currency));

        $counts = [];
        foreach ($ranges as $key => $range) {
            $counts[$key] = $range($open())->count();
        }

        // open on the late ones when there are any, otherwise on what comes next
        $tab = $request->query('when');
        if (! isset($ranges[$tab])) {
            $tab = $counts['overdue'] ? 'overdue' : 'month';
        }
        $term = trim((string) $request->query('q', ''));

        $query = $ranges[$tab]($open())
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%$term%")->orWhere('reference', 'like', "%$term%")
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%$term%")->orWhere('company', 'like', "%$term%"))));

        return view('dues.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'counts' => $counts,
            'term' => $term,
            'totals' => (clone $query)->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all(),
            'rows' => $query->with('client', 'service')->orderBy('next_due_date')->orderBy('id')->paginate(30)->withQueryString(),
        ]);
    }
}
