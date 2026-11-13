<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        return PlatformAccount::query()->withSum(['subscriptions as received' => fn ($q) => $q->whereNotNull('paid_on')], 'amount');
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'type', 'label' => 'Kind', 'type' => 'select', 'default' => 'bank', 'options' => array_map('__', self::TYPES), 'rules' => ['required', Rule::in(array_keys(self::TYPES))]],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3'], 'help' => 'Three letters, for example USD'],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
            ['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12, 'help' => 'Bank name, branch, the last digits of the account: enough to recognise it.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (PlatformAccount $a) => $a->name,
            'Kind' => fn (PlatformAccount $a) => __(self::TYPES[$a->type] ?? $a->type),
            'Received' => fn (PlatformAccount $a) => money($a->received ?? 0, $a->currency),
            'In use' => fn (PlatformAccount $a) => $a->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->subscriptions()->exists() ? __('Payments were received in this account. Switch it off instead.') : null;
    }
}
