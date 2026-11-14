<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends CrudController
{
    protected string $model = Service::class;

    protected string $route = 'services';

    protected string $title = 'Services & Products';

    protected string $singular = 'Service or product';

    protected string $icon = 'box';

    protected array $with = ['category'];

    protected function query(Request $request): Builder
    {
        return Service::query()->when(picked_currency(), fn (Builder $q, $code) => $q->where('currency', $code));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'currency', 'label' => __('Currency'), 'options' => currencies(), 'value' => picked_currency()]];
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150'], 'help' => 'For example: Web hosting, Domain, ERP system, Yearly support, an ebook.'],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'default' => 'service', 'options' => array_map('__', Service::KINDS), 'rules' => ['required', Rule::in(array_keys(Service::KINDS))]],
            ['name' => 'price', 'label' => 'Usual price', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'currency', 'label' => 'Currency', 'type' => 'select', 'default' => tenant()->currency, 'options' => currencies(), 'rules' => ['nullable', Rule::in(array_keys(currencies()))]],
            ['name' => 'cycle', 'label' => 'Usually paid', 'type' => 'select', 'default' => 'yearly', 'options' => array_map('__', config('duebook.cycle_labels')), 'rules' => ['required', Rule::in(array_keys(config('duebook.cycles')))]],
            ['name' => 'category_id', 'label' => 'Income category', 'type' => 'select', 'options' => Category::options('income'), 'rules' => ['nullable', own('categories')], 'help' => 'Money received for this goes into this category.', 'quick' => ['New income category', 'categories.create', ['type' => 'income']]],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Service $s) => $s->name,
            'Kind' => fn (Service $s) => __(Service::KINDS[$s->kind] ?? $s->kind),
            'Usual price' => fn (Service $s) => $s->price !== null ? money($s->price, $s->currency) : '',
            'Usually paid' => fn (Service $s) => __(config('duebook.cycle_labels')[$s->cycle] ?? $s->cycle),
            'Income category' => fn (Service $s) => $s->category?->name,
            'In use' => fn (Service $s) => $s->is_active ? __('Yes') : __('No'),
        ];
    }
}
