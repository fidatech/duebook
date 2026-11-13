<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** People the owner lends to or borrows from, and the money that moved between them. */
class PersonController extends CrudController
{
    protected string $model = Person::class;

    protected string $route = 'people';

    protected string $title = 'People & Loans';

    protected string $singular = 'Person';

    protected string $icon = 'handshake';

    protected array $search = ['name', 'phone'];

    /** @var array<int, array<string, float>>|null */
    private ?array $balances = null;

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50']],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Person $p) => new HtmlString('<a class="fw-semibold text-decoration-none" href="'.e(route('people.show', ['id' => $p->id])).'">'.e($p->name).'</a>'),
            'Phone' => fn (Person $p) => $p->phone,
            'Balance' => fn (Person $p) => self::balanceCell(($this->balances ??= PersonEntry::balances())[$p->id] ?? []),
        ];
    }

    /** "owes you 500.00 USD" in green, "you owe 200.00 USD" in red. */
    public static function balanceCell(array $balances): HtmlString
    {
        $parts = [];
        foreach ($balances as $currency => $balance) {
            if ($balance > 0) {
                $parts[] = '<span class="bal-due fw-semibold d-block">'.e(__('owes you :amount', ['amount' => money($balance, $currency)])).'</span>';
            } elseif ($balance < 0) {
                $parts[] = '<span class="bal-owe fw-semibold d-block">'.e(__('you owe :amount', ['amount' => money(-$balance, $currency)])).'</span>';
            }
        }

        return new HtmlString($parts ? implode('', $parts) : '<span class="text-muted">'.e(__('Settled')).'</span>');
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Open'), 'url' => route('people.show', ['id' => $record->id])]];
    }

    protected function afterStoreUrl(Model $record, Request $request): string
    {
        return route('people.show', ['id' => $record->id]);
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->entries()->exists() ? __('This person has entries. Delete the entries first.') : null;
    }

    public function show(int $id): View
    {
        $person = Person::findOrFail($id);

        return view('people.show', [
            'person' => $person,
            'balances' => $person->balances(),
            'entries' => $person->entries()->orderByDesc('date')->orderByDesc('id')->paginate(40),
        ]);
    }

    public function storeEntry(Request $request, int $id): RedirectResponse
    {
        $person = Person::findOrFail($id);
        $data = $request->validate([
            'direction' => ['required', Rule::in(array_keys(PersonEntry::DIRECTIONS))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::in(array_keys(currencies()))],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $person->entries()->create($data);

        return redirect()->route('people.show', ['id' => $person->id])->with('success', __('Entry saved.'));
    }

    public function destroyEntry(int $id, int $entry): RedirectResponse
    {
        $person = Person::findOrFail($id);
        $person->entries()->findOrFail($entry)->delete();

        return redirect()->route('people.show', ['id' => $person->id])->with('success', __('Entry deleted.'));
    }
}
