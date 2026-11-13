<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\PersonEntry;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today_iso();
        $in30 = Carbon::parse($today)->addDays(30)->toDateString();
        $monthStart = Carbon::parse($today)->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($today)->endOfMonth()->toDateString();

        // [currency => total] and how many, for a set of contracts
        $byCurrency = fn ($query) => $query->selectRaw('currency, count(*) as n, sum(amount) as total')->groupBy('currency')->orderBy('currency')->get();

        $overdue = $byCurrency(Contract::open()->where('next_due_date', '<', $today));
        $soon = $byCurrency(Contract::open()->whereBetween('next_due_date', [$today, $in30]));

        $month = fn (string $type) => Transaction::where('type', $type)->whereBetween('date', [$monthStart, $monthEnd])
            ->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all();

        // people: what they owe me and what I owe, by currency
        $owedToMe = [];
        $iOwe = [];
        foreach (PersonEntry::balances() as $balances) {
            foreach ($balances as $currency => $balance) {
                if ($balance > 0) {
                    $owedToMe[$currency] = ($owedToMe[$currency] ?? 0) + $balance;
                } else {
                    $iOwe[$currency] = ($iOwe[$currency] ?? 0) - $balance;
                }
            }
        }

        return view('dashboard', [
            'overdue' => $overdue,
            'soon' => $soon,
            'income' => $month('income'),
            'expense' => $month('expense'),
            'owedToMe' => $owedToMe,
            'iOwe' => $iOwe,
            'clients' => Client::count(),
            'contracts' => Contract::where('status', 'active')->count(),
            'next' => Contract::open()->with('client')->orderBy('next_due_date')->limit(8)->get(),
            'recent' => Transaction::with('category')->orderByDesc('date')->orderByDesc('id')->limit(6)->get(),
        ]);
    }
}
