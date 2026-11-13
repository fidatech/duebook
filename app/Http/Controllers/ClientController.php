<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class ClientController extends CrudController
{
    protected string $model = Client::class;

    protected string $route = 'clients';

    protected string $title = 'Clients';

    protected string $singular = 'Client';

    protected string $icon = 'users';

    protected array $search = ['name', 'company', 'phone', 'email'];

    protected function query(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return Client::query()->withCount(['contracts as active_contracts' => fn ($q) => $q->where('status', 'active')]);
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'company', 'label' => 'Company', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50'], 'help' => 'With the country code, for example +93 79 123 4567. It is used for the WhatsApp button.'],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255'], 'help' => 'Needed only if this client should get reminder emails.'],
            ['name' => 'address', 'label' => 'Address', 'rules' => ['nullable', 'string', 'max:255'], 'col' => 12],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12],
            ['name' => 'is_active', 'label' => 'Active client', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Client $c) => new HtmlString('<a class="fw-semibold text-decoration-none" href="'.e(route('clients.show', ['id' => $c->id])).'">'.e($c->name).'</a>'),
            'Company' => fn (Client $c) => $c->company,
            'Phone' => fn (Client $c) => $c->phone,
            'Contracts' => fn (Client $c) => $c->active_contracts,
        ];
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Open'), 'url' => route('clients.show', ['id' => $record->id])]];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $limit = tenant()?->plan?->max_clients;
        if (! $record && $limit && Client::count() >= $limit) {
            throw new UserError(__('Your plan allows :count clients. Please upgrade your plan to add more.', ['count' => $limit]));
        }

        return $data;
    }

    protected function afterStoreUrl(Model $record, Request $request): string
    {
        return route('clients.show', ['id' => $record->id]);
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->contracts()->exists() ? __('This client has contracts. Delete the contracts first, or switch the client off.') : null;
    }

    public function show(int $id): View
    {
        $client = Client::findOrFail($id);

        return view('clients.show', [
            'client' => $client,
            'contracts' => $client->contracts()->with('service')->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")->orderBy('next_due_date')->get(),
            'payments' => $client->payments()->with('contract')->orderByDesc('paid_on')->orderByDesc('id')->limit(30)->get(),
            'paid' => $client->payments()->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->all(),
        ]);
    }
}
