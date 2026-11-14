<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Contracts: what each client pays for, how much, how often, and when it is due next. */
class ContractController extends CrudController
{
    protected string $model = Contract::class;

    protected string $route = 'contracts';

    protected string $title = 'Contracts';

    protected string $singular = 'Contract';

    protected string $icon = 'file-signature';

    protected array $search = ['title', 'reference', 'client.name', 'client.company'];

    protected array $with = ['client', 'service'];

    protected string $orderBy = 'next_due_date';

    protected function query(Request $request): Builder
    {
        return Contract::query()
            ->when($request->query('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($request->query('service'), fn (Builder $q, $id) => $q->where('service_id', $id))
            ->when($request->query('cycle'), fn (Builder $q, $cycle) => $q->where('cycle', $cycle));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'status', 'label' => __('Status'), 'options' => array_map('__', Contract::STATUSES), 'value' => $request->query('status')],
            ['name' => 'service', 'label' => __('Service'), 'options' => Service::options(), 'value' => $request->query('service')],
            ['name' => 'cycle', 'label' => __('Paid'), 'options' => array_map('__', config('duebook.cycle_labels')), 'value' => $request->query('cycle')],
        ];
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Client', 'type' => 'select', 'search' => true, 'options' => Client::options(), 'default' => request()->query('client_id'), 'rules' => ['required', own('clients')], 'quick' => ['New client', 'clients.create']],
            ['name' => 'service_id', 'label' => 'Service or product', 'type' => 'select', 'options' => Service::options(), 'rules' => ['nullable', own('services')], 'quick' => ['New service or product', 'services.create']],
            ['name' => 'title', 'label' => 'What is it for', 'rules' => ['required', 'string', 'max:200'], 'help' => 'For example: example.com hosting and domain, or Accounting system yearly support.'],
            ['name' => 'reference', 'label' => 'Reference', 'rules' => ['nullable', 'string', 'max:200'], 'help' => 'Domain, cPanel user, contract number: anything that helps you find it.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'currency', 'label' => 'Currency', 'type' => 'select', 'default' => tenant()->currency, 'options' => currencies(), 'rules' => ['required', Rule::in(array_keys(currencies()))]],
            ['name' => 'cycle', 'label' => 'Paid', 'type' => 'select', 'default' => 'yearly', 'options' => array_map('__', config('duebook.cycle_labels')), 'rules' => ['required', Rule::in(array_keys(config('duebook.cycles')))]],
            ['name' => 'start_date', 'label' => 'Contract date', 'type' => 'date', 'rules' => ['nullable', 'date']],
            ['name' => 'next_due_date', 'label' => 'Next due date', 'type' => 'date', 'rules' => ['nullable', 'required_if:status,active', 'date'], 'help' => 'The day the next money should come. It moves on by itself each time you receive a payment.'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'default' => 'active', 'options' => array_map('__', Contract::STATUSES), 'rules' => ['required', Rule::in(array_keys(Contract::STATUSES))]],
            ['name' => 'notify_client', 'label' => 'Also email the client before the due date', 'type' => 'checkbox', 'default' => false, 'help' => 'The client needs an email address.'],
            ['name' => 'file', 'label' => 'Scan of the signed contract', 'type' => 'file', 'virtual' => true, 'accept' => '.pdf,image/*', 'rules' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
                'help' => $record?->file_path ? 'A file is already saved. Choose another one only to replace it.' : 'PDF or photo, up to 10 MB.'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12],
        ];
    }

    protected function columns(): array
    {
        return [
            'Contract' => fn (Contract $c) => new HtmlString('<span class="fw-semibold">'.e($c->title).'</span>'.($c->reference ? '<span class="d-block small text-muted">'.e($c->reference).'</span>' : '')),
            'Client' => fn (Contract $c) => new HtmlString('<a class="text-decoration-none" href="'.e(route('clients.show', ['id' => $c->client_id])).'">'.e($c->client?->name).'</a>'),
            'Amount' => fn (Contract $c) => money($c->amount, $c->currency),
            'Paid' => fn (Contract $c) => $c->cycleLabel(),
            'Next due date' => fn (Contract $c) => self::dueCell($c),
        ];
    }

    /** "14 Nov 2026" with the coloured "3 days late" badge. */
    public static function dueCell(Contract $contract): HtmlString
    {
        [$text, $tone] = $contract->dueBadge();
        $badge = [
            'danger' => 'bg-danger-subtle text-danger-emphasis',
            'warning' => 'bg-warning-subtle text-warning-emphasis',
            'success' => 'bg-success-subtle text-success-emphasis',
            'secondary' => 'bg-secondary-subtle text-secondary-emphasis',
        ][$tone];

        return new HtmlString('<span class="text-nowrap">'.e(fmt_date($contract->next_due_date)).'</span> <span class="badge '.$badge.'">'.e($text).'</span>');
    }

    protected function rowLinks(Model $record): array
    {
        $links = [];
        if ($record->status === 'active' && $record->next_due_date) {
            $links[] = ['label' => __('Receive'), 'icon' => 'hand-holding-dollar', 'class' => 'btn-soft-success', 'url' => route('contracts.pay', ['id' => $record->id])];
        }
        if ($record->file_path) {
            $links[] = ['label' => __('File'), 'icon' => 'paperclip', 'url' => route('contracts.file', ['id' => $record->id]), 'blank' => true];
        }

        return $links;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if ($file = $request->file('file')) {
            if ($record->file_path) {
                Storage::disk('local')->delete($record->file_path);
            }
            $record->update(['file_path' => $file->store('contracts/'.$record->tenant_id, 'local')]);
        }
    }

    protected function afterStoreUrl(Model $record, Request $request): string
    {
        return route('clients.show', ['id' => $record->client_id]);
    }

    /** The scan of the signed contract. */
    public function file(int $id): StreamedResponse
    {
        $contract = Contract::findOrFail($id);
        abort_unless($contract->file_path && Storage::disk('local')->exists($contract->file_path), 404);

        return Storage::disk('local')->response($contract->file_path);
    }

    public function pay(int $id): View
    {
        $contract = Contract::with('client', 'service')->findOrFail($id);

        return view('contracts.pay', ['contract' => $contract, 'after' => $contract->dueAfter()]);
    }

    public function storePayment(Request $request, int $id): RedirectResponse
    {
        $contract = Contract::findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        Payment::receive($contract, $data + ['advance' => $request->boolean('advance')]);

        return redirect()->route('dues.index')->with('success', __('Payment of :amount received from :client.', ['amount' => money($data['amount'], $contract->currency), 'client' => $contract->client?->name]));
    }
}
