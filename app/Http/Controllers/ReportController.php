<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::parse(today_iso());
        $year = (int) $request->query('year', $today->year);
        if ($year < 2000 || $year > 2100) {
            $year = $today->year;
        }

        // income and expense of every month of the year, by currency: [currency => [month => ['income' => .., 'expense' => ..]]]
        $months = [];
        foreach (Transaction::whereBetween('date', ["$year-01-01", "$year-12-31"])->get(['type', 'currency', 'amount', 'date']) as $row) {
            $month = (int) $row->date->format('n');
            $months[$row->currency][$month][$row->type] = ($months[$row->currency][$month][$row->type] ?? 0) + (float) $row->amount;
        }
        ksort($months);

        // income and expense of the year by category
        $categories = Transaction::whereBetween('date', ["$year-01-01", "$year-12-31"])
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw('transactions.type as type, categories.name as category, transactions.currency as currency, sum(transactions.amount) as total')
            ->groupBy('transactions.type', 'categories.name', 'transactions.currency')
            ->orderBy('transactions.type')->orderByDesc('total')->get();

        return view('reports.index', [
            'year' => $year,
            'years' => range($today->year, $today->year - 5),
            'months' => $months,
            'categories' => $categories,
            'forecast' => $this->forecast($today),
        ]);
    }

    /**
     * Money expected from contracts in each of the next 12 months, by currency.
     * A yearly contract counts once, a monthly one twelve times. Late ones come first, under "late".
     *
     * @return array<string, array<string, float>> ['late' => [...], '2026-11' => ['USD' => 120.0], ...]
     */
    private function forecast(Carbon $today): array
    {
        $out = ['late' => []];
        for ($i = 0; $i < 12; $i++) {
            $out[$today->copy()->startOfMonth()->addMonths($i)->format('Y-m')] = [];
        }
        $end = $today->copy()->startOfMonth()->addMonths(12);

        foreach (Contract::open()->get(['amount', 'currency', 'cycle', 'next_due_date']) as $contract) {
            $due = $contract->next_due_date->copy();
            $step = $contract->months();
            if ($due->lt($today->copy()->startOfDay())) {
                $out['late'][$contract->currency] = ($out['late'][$contract->currency] ?? 0) + (float) $contract->amount;
                if ($step === 0) {
                    continue;
                }
                // the periods after the late one
                while ($due->lt($today->copy()->startOfMonth())) {
                    $due->addMonthsNoOverflow($step);
                }
                if ($due->lt($today->copy()->startOfDay())) {
                    $due->addMonthsNoOverflow($step);
                }
            }
            while ($due->lt($end)) {
                $key = $due->format('Y-m');
                $out[$key][$contract->currency] = ($out[$key][$contract->currency] ?? 0) + (float) $contract->amount;
                if ($step === 0) {
                    break;
                }
                $due->addMonthsNoOverflow($step);
            }
        }

        return $out;
    }
}
