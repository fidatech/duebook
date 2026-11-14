<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The businesses that use the system: open one, change its plan and end date, suspend or delete it. */
class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $rows = Tenant::with('plan')->withCount('users')
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")))
            ->when(in_array($status, [Tenant::TRIAL, Tenant::ACTIVE, Tenant::SUSPENDED], true), fn (Builder $q) => $q->where('status', $status))
            // "ended": suspended, or the trial or the paid time is over
            ->when($status === 'ended', fn (Builder $q) => $q->where(fn (Builder $e) => $e
                ->where('status', Tenant::SUSPENDED)
                ->orWhere(fn (Builder $a) => $a->where('status', Tenant::ACTIVE)->where('subscription_ends_at', '<', now()->toDateString()))
                ->orWhere(fn (Builder $t) => $t->where('status', Tenant::TRIAL)->where('trial_ends_at', '<', now()->toDateString()))))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.tenants.index', ['rows' => $rows, 'term' => $term, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.tenants.form', ['tenant' => null, 'plans' => Plan::orderBy('sort')->get(), 'timezones' => timezone_identifiers_list()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['owner_email' => strtolower(trim((string) $request->input('owner_email'))), 'currency' => strtoupper(trim((string) $request->input('currency')))]);
        $data = $request->validate($this->rules() + [
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', Password::min(8)],
        ]);

        $tenant = Tenants::create($data);
        $tenant->update(array_filter(['notes' => $data['notes'] ?? null, 'trial_ends_at' => $data['status'] === Tenant::TRIAL ? ($data['trial_ends_at'] ?? null) : null]));

        return redirect()->route('admin.tenants.edit', ['id' => $tenant->id])->with('success', __('The business is opened. Its owner can sign in now.'));
    }

    public function edit(int $id): View
    {
        $tenant = Tenant::findOrFail($id);

        return view('admin.tenants.form', [
            'tenant' => $tenant,
            'plans' => Plan::orderBy('sort')->get(),
            'timezones' => timezone_identifiers_list(),
            'users' => User::where('tenant_id', $tenant->id)->orderBy('id')->get(),
            'subscriptions' => $tenant->subscriptions()->with('plan', 'account')->orderByDesc('starts_on')->orderByDesc('id')->get(),
            // counted by name of the business: an administrator never opens these records
            'usage' => [
                'Clients' => Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
                'Contracts' => Contract::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $request->merge(['currency' => strtoupper(trim((string) $request->input('currency')))]);
        $data = $request->validate($this->rules() + ['email' => ['nullable', 'email', 'max:255']]);

        if ($data['status'] !== Tenant::TRIAL) {
            $data['trial_ends_at'] = $tenant->trial_ends_at;
        }
        $tenant->update($data);

        return back()->with('success', __('The business is saved.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        // typing the name protects against a wrong click: everything of the business goes with it
        $request->validate(['confirm' => ['required', Rule::in([$tenant->name])]], ['confirm.in' => __('Type the name of the business exactly to delete it.')]);
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('success', __('The business and everything it recorded is deleted.'));
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'status' => ['required', Rule::in([Tenant::TRIAL, Tenant::ACTIVE, Tenant::SUSPENDED])],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
