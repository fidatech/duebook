<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Income categories and expense categories: the same screen under /categories/income and /categories/expense. */
class CategoryController extends CrudController
{
    protected string $model = Category::class;

    protected string $route = 'categories';

    protected string $title = 'Categories';

    protected string $singular = 'Category';

    protected string $icon = 'folder-tree';

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
        $this->title = $this->type() === 'income' ? 'Income categories' : 'Expense categories';
        $this->singular = $this->type() === 'income' ? 'Income category' : 'Expense category';

        return parent::callAction($method, $parameters);
    }

    protected function query(Request $request): Builder
    {
        return Category::query()->where('type', $this->type())->withCount('transactions');
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Category $c) => $c->name,
            'Entries' => fn (Category $c) => $c->transactions_count,
            'In use' => fn (Category $c) => $c->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data + ['type' => $this->type()];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->transactions()->exists() ? __('This category already has entries. Switch it off instead.') : null;
    }
}
