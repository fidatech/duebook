<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Financial accounts of the platform: where the money of subscriptions is received. */
class AccountController extends CrudController
{
    public const TYPES = ['bank' => 'Bank account', 'cash' => 'Cash', 'online' => 'Online payment', 'mobile' => 'Mobile money', 'other' => 'Other'];

    protected string $model = PlatformAccount::class;

    protected string $route = 'admin.accounts';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Financial accounts';

    protected string $singular = 'Financial account';

    protected string $icon = 'building-columns';

    protected function query(Request $request): Builder
    {
        return PlatformAccount::query()
            ->withSum(['subscriptions as received' => fn ($q) => $q->whereNotNull('paid_on')], 'amount')
            ->withSum(['subscriptions as received_month' => fn ($q) => $q->whereNotNull('paid_on')->where('paid_on', '>=', now()->startOfMonth()->toDateString())], 'amount')
            ->withCount(['subscriptions as payments' => fn ($q) => $q->whereNotNull('paid_on')]);
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'type', 'label' => 'Kind', 'type' => 'select', 'default' => 'bank', 'options' => array_map('__', self::TYPES), 'rules' => ['required', Rule::in(array_keys(self::TYPES))]],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3'], 'help' => 'Three letters, for example USD'],
            ['name' => 'number', 'label' => 'Account or wallet number', 'rules' => ['nullable', 'string', 'max:100'], 'help' => 'For example the HesabPay number. Businesses see it and can copy it.'],
            ['name' => 'qr', 'label' => 'QR code to scan', 'type' => 'file', 'virtual' => true, 'accept' => 'image/*', 'rules' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'help' => $record?->qr_path ? 'A QR code is already saved. Choose another picture only to replace it.' : 'Optional picture. Businesses scan it on their Subscription page.'],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true, 'help' => 'Accounts in use are shown to businesses as the ways to pay.'],
            ['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12, 'help' => 'Businesses see this on their Subscription page as the way to pay you: write the bank name, account name and number, or the wallet number.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (PlatformAccount $a) => new HtmlString('<a class="fw-semibold text-decoration-none" href="'.e(route('admin.accounts.show', ['id' => $a->id])).'">'.e($a->name).'</a>'),
            'Kind' => fn (PlatformAccount $a) => __(self::TYPES[$a->type] ?? $a->type),
            'Account or wallet number' => fn (PlatformAccount $a) => $a->number,
            'QR code' => fn (PlatformAccount $a) => $a->qr_path ? __('Yes') : __('No'),
            'Payments' => fn (PlatformAccount $a) => $a->payments,
            'This month' => fn (PlatformAccount $a) => money($a->received_month ?? 0, $a->currency),
            'Received' => fn (PlatformAccount $a) => money($a->received ?? 0, $a->currency),
            'In use' => fn (PlatformAccount $a) => $a->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Statement'), 'icon' => 'list', 'url' => route('admin.accounts.show', ['id' => $record->id])]];
    }

    /** Confirmed payments that were not put in any account: the list says where to fix them. */
    protected function headerLinks(Request $request): array
    {
        $loose = Subscription::whereNotNull('paid_on')->whereNull('platform_account_id')->count();

        return $loose ? [['label' => trans_choice('{1} 1 payment without an account|[2,*] :count payments without an account', $loose, ['count' => $loose]), 'url' => route('admin.subscriptions.index', ['account' => 'none'])]] : [];
    }

    /** The statement of an account: every payment received in it, who paid, when and for what. */
    public function show(Request $request, int $id): View
    {
        $account = PlatformAccount::findOrFail($id);
        $from = $request->query('from');
        $to = $request->query('to');

        $paid = fn () => Subscription::where('platform_account_id', $account->id)->whereNotNull('paid_on');
        $sum = fn ($query) => $query->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all();
        $filtered = fn () => $paid()
            ->when($from, fn (Builder $q) => $q->where('paid_on', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('paid_on', '<=', $to));

        return view('admin.account', [
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'month' => $sum($paid()->where('paid_on', '>=', now()->startOfMonth()->toDateString())),
            'year' => $sum($paid()->where('paid_on', '>=', now()->startOfYear()->toDateString())),
            'all' => $sum($paid()),
            'filtered' => $sum($filtered()),
            'rows' => $filtered()->with('tenant', 'plan')->orderByDesc('paid_on')->orderByDesc('id')->paginate(30)->withQueryString(),
            'payers' => $paid()->with('tenant')->selectRaw('tenant_id, currency, sum(amount) as total, count(*) as payments')
                ->groupBy('tenant_id', 'currency')->orderByDesc('total')->limit(8)->get(),
        ]);
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if ($file = $request->file('qr')) {
            if ($record->qr_path) {
                Storage::disk('local')->delete($record->qr_path);
            }
            $record->update(['qr_path' => $file->store('accounts', 'local')]);
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->subscriptions()->exists() ? __('Payments were received in this account. Switch it off instead.') : null;
    }
}
