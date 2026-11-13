<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();
        $in30 = now()->addDays(30)->toDateString();
        $yearStart = now()->startOfYear()->toDateString();

        return view('admin.dashboard', [
            'tenants' => Tenant::count(),
            'active' => Tenant::where('status', Tenant::ACTIVE)->where(fn ($q) => $q->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '>=', $today))->count(),
            'trial' => Tenant::where('status', Tenant::TRIAL)->where('trial_ends_at', '>=', $today)->count(),
            'suspended' => Tenant::where('status', Tenant::SUSPENDED)->count(),
            'expiring' => Tenant::with('plan')->where('status', Tenant::ACTIVE)->whereBetween('subscription_ends_at', [$today, $in30])->orderBy('subscription_ends_at')->get(),
            'expired' => Tenant::with('plan')->where(fn ($q) => $q
                ->where(fn ($a) => $a->where('status', Tenant::ACTIVE)->where('subscription_ends_at', '<', $today))
                ->orWhere(fn ($t) => $t->where('status', Tenant::TRIAL)->where('trial_ends_at', '<', $today)))->orderByDesc('updated_at')->limit(20)->get(),
            'revenue' => Subscription::whereNotNull('paid_on')->where('paid_on', '>=', $yearStart)->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->all(),
            'latest' => Tenant::with('plan')->orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
