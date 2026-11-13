<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Subscriptions: each row is a period a business pays for. Saving one puts the business on
 * that plan and lets it work until the end date of its latest subscription.
 */
class SubscriptionController extends CrudController
{
    protected string $model = Subscription::class;

    protected string $route = 'admin.subscriptions';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Subscriptions';

    protected string $singular = 'Subscription';

    protected string $icon = 'receipt';

    protected array $search = ['note', 'tenant.name'];

    protected array $with = ['tenant', 'plan', 'account'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function query(Request $request): Builder
    {
        return Subscription::query()
            ->when($request->query('tenant'), fn (Builder $q, $id) => $q->where('tenant_id', $id))
            ->when($request->query('paid') === 'no', fn (Builder $q) => $q->whereNull('paid_on'))
            ->when($request->query('paid') === 'yes', fn (Builder $q) => $q->whereNotNull('paid_on'));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'tenant', 'label' => __('Business'), 'options' => Tenant::orderBy('name')->pluck('name', 'id')->all(), 'value' => $request->query('tenant')],
            ['name' => 'paid', 'label' => __('Paid or not'), 'options' => ['yes' => __('Paid'), 'no' => __('Not paid yet')], 'value' => $request->query('paid')],
        ];
    }

    protected function totals(Request $request): array
    {
        $totals = $this->query($request)->whereNotNull('paid_on')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->all();

        return $totals ? ['Received' => sums($totals)] : [];
    }

    protected function fields(?Model $record): array
    {
        $accounts = PlatformAccount::orderBy('name')->get()->mapWithKeys(fn (PlatformAccount $a) => [$a->id => $a->name.' ('.$a->currency.')'])->all();

        return [
            ['name' => 'tenant_id', 'label' => 'Business', 'type' => 'select', 'search' => true, 'options' => Tenant::orderBy('name')->pluck('name', 'id')->all(), 'default' => request()->query('tenant'), 'rules' => ['required', 'exists:tenants,id']],
            ['name' => 'plan_id', 'label' => 'Plan', 'type' => 'select', 'options' => Plan::orderBy('sort')->get()->mapWithKeys(fn (Plan $p) => [$p->id => $p->label()])->all(), 'rules' => ['required', 'exists:plans,id']],
            ['name' => 'starts_on', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString(), 'rules' => ['required', 'date']],
            ['name' => 'ends_on', 'label' => 'Until', 'type' => 'date', 'default' => now()->addYear()->toDateString(), 'rules' => ['nullable', 'date', 'after_or_equal:starts_on'], 'help' => 'Leave empty for a lifetime plan.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3']],
            ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date', 'rules' => ['nullable', 'date'], 'help' => 'Leave empty while the money has not come.'],
            ['name' => 'platform_account_id', 'label' => 'Received in', 'type' => 'select', 'options' => $accounts, 'rules' => ['nullable', 'exists:platform_accounts,id']],
            ['name' => 'note', 'label' => 'Note', 'rules' => ['nullable', 'string', 'max:255'], 'col' => 12],
        ];
    }

    protected function columns(): array
    {
        return [
            'Business' => fn (Subscription $s) => $s->tenant?->name,
            'Plan' => fn (Subscription $s) => $s->plan?->name,
            'Period' => fn (Subscription $s) => fmt_date($s->starts_on).' – '.($s->ends_on ? fmt_date($s->ends_on) : __('no end')),
            'Amount' => fn (Subscription $s) => money($s->amount, $s->currency),
            'Paid on' => fn (Subscription $s) => $s->paid_on ? fmt_date($s->paid_on) : __('Not paid yet'),
            'Received in' => fn (Subscription $s) => $s->account?->name,
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    /** The business follows its latest subscription: plan, active status and end date. */
    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        $tenant = $record->tenant;
        $latest = $tenant->subscriptions()->orderByRaw('ends_on is null desc')->orderByDesc('ends_on')->first();
        if ($latest && $latest->id === $record->id) {
            $tenant->update([
                'plan_id' => $record->plan_id,
                'subscription_ends_at' => $record->ends_on,
                'status' => $tenant->status === Tenant::SUSPENDED ? Tenant::SUSPENDED : Tenant::ACTIVE,
            ]);
        }
    }
}
