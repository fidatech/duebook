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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    protected array $search = ['note', 'reference', 'tenant.name'];

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
            // "Confirm" on a request of a business opens this form with today filled in: check it and save
            ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date', 'value' => $record && ! $record->paid_on && request()->boolean('confirm') ? ($record->sent_on ?? now()->toDateString()) : $record?->paid_on,
                'rules' => ['nullable', 'date'], 'help' => 'Leave empty while the money has not come. Filling it in confirms the payment and gives the business access until the end date.'],
            ['name' => 'platform_account_id', 'label' => 'Received in', 'type' => 'select', 'options' => $accounts, 'rules' => ['nullable', 'exists:platform_accounts,id']],
            ['name' => 'reference', 'label' => 'Transfer or receipt number', 'rules' => ['nullable', 'string', 'max:255']],
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
            'Paid on' => fn (Subscription $s) => $s->paid_on ? fmt_date($s->paid_on) : ($s->requested ? __('Waiting for you to confirm') : __('Not paid yet')),
            'Received in' => fn (Subscription $s) => $s->account?->name,
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    /** Once the money is confirmed (Paid on is filled in), the business follows the subscription. */
    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        $record->apply();
    }

    protected function rowLinks(Model $record): array
    {
        $links = [];
        if ($record->receipt_path) {
            $links[] = ['label' => __('Receipt'), 'icon' => 'paperclip', 'url' => route('admin.subscriptions.receipt', ['id' => $record->id]), 'blank' => true];
        }
        if (! $record->isPaid()) {
            $links[] = ['label' => __('Confirm'), 'icon' => 'check', 'class' => 'btn-soft-success', 'url' => route('admin.subscriptions.edit', ['id' => $record->id, 'confirm' => 1])];
        }

        return $links;
    }

    /** The receipt a business uploaded with its "I have paid" request. */
    public function receipt(int $id): StreamedResponse
    {
        $subscription = Subscription::findOrFail($id);
        abort_unless($subscription->receipt_path && Storage::disk('local')->exists($subscription->receipt_path), 404);

        return Storage::disk('local')->response($subscription->receipt_path);
    }
}
