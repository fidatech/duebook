<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends CrudController
{
    public const INTERVALS = ['monthly' => 'Monthly', 'yearly' => 'Yearly', 'lifetime' => 'Lifetime'];

    protected string $model = Plan::class;

    protected string $route = 'admin.plans';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Plans';

    protected string $singular = 'Plan';

    protected string $icon = 'layer-group';

    protected string $orderBy = 'sort';

    protected function query(Request $request): Builder
    {
        return Plan::query()->withCount('tenants');
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'interval', 'label' => 'Paid', 'type' => 'select', 'default' => 'yearly', 'options' => array_map('__', self::INTERVALS), 'rules' => ['required', Rule::in(array_keys(self::INTERVALS))]],
            ['name' => 'price', 'label' => 'Price', 'type' => 'decimal', 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3'], 'help' => 'Three letters, for example USD'],
            ['name' => 'max_clients', 'label' => 'Clients allowed', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1'], 'help' => 'Leave empty for no limit.'],
            ['name' => 'max_users', 'label' => 'Users allowed', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1'], 'help' => 'Leave empty for no limit.'],
            ['name' => 'sort', 'label' => 'Order in lists', 'type' => 'number', 'default' => 0, 'rules' => ['required', 'integer', 'min:0', 'max:9999'], 'help' => 'New businesses start on the first plan that is in use.'],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Plan $p) => $p->name,
            'Price' => fn (Plan $p) => money($p->price, $p->currency).' / '.__(self::INTERVALS[$p->interval] ?? $p->interval),
            'Clients allowed' => fn (Plan $p) => $p->max_clients ?? __('No limit'),
            'Users allowed' => fn (Plan $p) => $p->max_users ?? __('No limit'),
            'Businesses' => fn (Plan $p) => $p->tenants_count,
            'In use' => fn (Plan $p) => $p->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->tenants()->exists() ? __('Businesses are on this plan. Switch it off instead.') : null;
    }
}
