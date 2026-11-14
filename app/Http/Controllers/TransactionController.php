<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Income and expenses: the same screen under /money/income and /money/expense. */
class TransactionController extends CrudController
{
    protected string $model = Transaction::class;

    protected string $route = 'money';

    protected string $title = 'Income';

    protected string $singular = 'Income';

    protected string $icon = 'arrow-trend-up';

    protected array $search = ['description', 'client.name'];

    protected array $with = ['category', 'client'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    private function type(): string
    {
        return request()->route('type') === 'expense' ? 'expense' : 'income';
    }

    protected function routeParams(): array
    {
        return ['type' => $this->type()];
    }

    /** The names on the screen follow the type in the address. */
    public function callAction($method, $parameters)
    {
        [$this->title, $this->singular, $this->icon] = $this->type() === 'income'
            ? ['Income', 'Income', 'arrow-trend-up']
            : ['Expenses', 'Expense', 'receipt'];

        return parent::callAction($method, $parameters);
    }

    /** The list with its filters, newest first. */
    protected function query(Request $request): Builder
    {
        return $this->filtered($request)->orderByDesc('date');
    }

    private function filtered(Request $request): Builder
    {
        return Transaction::query()->where('type', $this->type())
            ->when($request->query('category'), fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when(picked_currency(), fn (Builder $q, $code) => $q->where('currency', $code))
            ->when($request->query('from'), fn (Builder $q, $date) => $q->where('date', '>=', $date))
            ->when($request->query('to'), fn (Builder $q, $date) => $q->where('date', '<=', $date));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'category', 'label' => __('Category'), 'options' => Category::options($this->type()), 'value' => $request->query('category')],
            ['name' => 'currency', 'label' => __('Currency'), 'options' => currencies(), 'value' => picked_currency()],
            ['name' => 'from', 'label' => __('From'), 'type' => 'date', 'value' => $request->query('from')],
            ['name' => 'to', 'label' => __('To'), 'type' => 'date', 'value' => $request->query('to')],
        ];
    }

    protected function totals(Request $request): array
    {
        $totals = $this->filtered($request)->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all();

        return $totals ? ['Total' => sums($totals)] : [];
    }

    protected function fields(?Model $record): array
    {
        $income = $this->type() === 'income';
        $fields = [
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => today_iso(), 'rules' => ['required', 'date']],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'currency', 'label' => 'Currency', 'type' => 'select', 'default' => tenant()->currency, 'options' => currencies(), 'rules' => ['required', Rule::in(array_keys(currencies()))]],
            ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => Category::options($this->type()), 'rules' => ['required', own('categories')],
                'quick' => [$income ? 'New income category' : 'New expense category', 'categories.create', ['type' => $this->type()]]],
        ];
        if ($income) {
            $fields[] = ['name' => 'client_id', 'label' => 'Client', 'type' => 'select', 'search' => true, 'options' => Client::options(), 'rules' => ['nullable', own('clients')], 'quick' => ['New client', 'clients.create']];
            $fields[] = ['name' => 'service_id', 'label' => 'Service or product', 'type' => 'select', 'options' => Service::options(), 'rules' => ['nullable', own('services')], 'quick' => ['New service or product', 'services.create']];
        }
        $fields[] = ['name' => 'description', 'label' => 'Description', 'rules' => ['nullable', 'string', 'max:255'], 'col' => 12];

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Description' => fn (Transaction $t) => $t->description ?: ($t->category?->name ?? '—'),
            'Date' => fn (Transaction $t) => fmt_date($t->date),
            'Category' => fn (Transaction $t) => $t->category?->name,
            'Client' => fn (Transaction $t) => $t->client?->name,
            'Amount' => fn (Transaction $t) => money($t->amount, $t->currency),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data + ['type' => $this->type()];
    }

    /** The income of a contract payment and the payment itself stay the same amount and date. */
    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        $record->payment?->update(['amount' => $record->amount, 'paid_on' => $record->date, 'currency' => $record->currency]);
    }
}
