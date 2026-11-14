<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BooksTest extends TestCase
{
    use RefreshDatabase;

    private function contract(array $attributes = []): Contract
    {
        $client = Client::create(['name' => 'Ahmad', 'email' => 'ahmad@example.com']);

        return Contract::create($attributes + [
            'client_id' => $client->id,
            'title' => 'example.af hosting',
            'amount' => 60,
            'currency' => 'USD',
            'cycle' => 'yearly',
            'next_due_date' => '2026-11-20',
            'status' => 'active',
        ]);
    }

    public function test_one_business_never_sees_the_clients_of_another(): void
    {
        $acme = $this->business('Acme', 'owner@acme.test');
        $other = $this->business('Other', 'owner@other.test');

        $this->signIn($other);
        $this->post('/clients', ['name' => 'Secret client', 'is_active' => 1])->assertRedirect();
        $secret = Client::firstOrFail();
        $this->assertSame($other->id, $secret->tenant_id);

        $this->signIn($acme);
        $this->get('/clients')->assertOk()->assertDontSee('Secret client');
        $this->get('/clients/'.$secret->id)->assertNotFound();
        $this->get('/clients/'.$secret->id.'/edit')->assertNotFound();
        // a contract cannot be hung on a client of another business
        $this->post('/contracts', ['client_id' => $secret->id, 'title' => 'x', 'amount' => 1, 'currency' => 'USD', 'cycle' => 'yearly', 'next_due_date' => '2026-12-01', 'status' => 'active'])
            ->assertSessionHasErrors('client_id');
    }

    public function test_receiving_a_payment_writes_income_and_moves_the_due_date(): void
    {
        $this->signIn($this->business());
        $contract = $this->contract();

        $this->get('/dues?when=week')->assertOk()->assertSee('Ahmad');

        $this->post('/contracts/'.$contract->id.'/pay', ['amount' => '60', 'paid_on' => '2026-11-14', 'advance' => 1])->assertRedirect('/dues');

        $this->assertSame('2027-11-20', $contract->fresh()->next_due_date->toDateString());
        $income = Transaction::where('type', 'income')->firstOrFail();
        $this->assertEquals(60, $income->amount);
        $this->assertSame($contract->client_id, $income->client_id);
        $this->assertNotNull($income->category_id);

        // deleting that income takes the payment back and the contract is due again
        $this->delete('/money/income/'.$income->id)->assertRedirect();
        $this->assertSame(0, Payment::count());
        $this->assertSame('2026-11-20', $contract->fresh()->next_due_date->toDateString());
    }

    public function test_a_part_payment_leaves_the_due_date(): void
    {
        $this->signIn($this->business());
        $contract = $this->contract();

        $this->post('/contracts/'.$contract->id.'/pay', ['amount' => '20', 'paid_on' => '2026-11-14', 'advance' => 0])->assertRedirect();

        $this->assertSame('2026-11-20', $contract->fresh()->next_due_date->toDateString());
        $this->assertSame(1, Payment::count());
    }

    public function test_a_one_time_contract_ends_when_paid(): void
    {
        $this->signIn($this->business());
        $contract = $this->contract(['cycle' => 'one_time']);

        $this->post('/contracts/'.$contract->id.'/pay', ['amount' => '60', 'paid_on' => '2026-11-14', 'advance' => 1])->assertRedirect();

        $this->assertSame('ended', $contract->fresh()->status);
        $this->assertNull($contract->fresh()->next_due_date);
    }

    public function test_late_contracts_are_counted_and_listed(): void
    {
        $this->signIn($this->business());
        $this->contract(['next_due_date' => '2026-11-01']);

        $this->assertSame(1, Contract::overdue()->count());
        $this->get('/dues')->assertOk()->assertSee('13 days late');
        $this->get('/dashboard')->assertOk();
        $this->get('/reports')->assertOk()->assertSee('Late (should have come already)');
    }

    public function test_the_balance_with_a_person_follows_what_was_given_and_received(): void
    {
        $this->signIn($this->business());
        $person = Person::create(['name' => 'Karim']);

        $this->post('/people/'.$person->id.'/entries', ['direction' => 'gave', 'amount' => '1,000', 'currency' => 'USD', 'date' => '2026-11-01'])->assertRedirect();
        $this->post('/people/'.$person->id.'/entries', ['direction' => 'received', 'amount' => '300', 'currency' => 'USD', 'date' => '2026-11-10'])->assertRedirect();

        $this->assertEquals(['USD' => 700.0], $person->balances());
        $this->get('/people')->assertOk()->assertSee('owes you 700.00 USD');
        $this->get('/people/'.$person->id)->assertOk();
    }

    public function test_income_and_expense_screens_work(): void
    {
        $this->signIn($this->business());
        $category = \App\Models\Category::where('type', 'expense')->firstOrFail();

        $this->get('/money/expense/create')->assertOk();
        $this->post('/money/expense', ['date' => '2026-11-14', 'amount' => '250', 'currency' => 'USD', 'category_id' => $category->id, 'description' => 'Server'])->assertRedirect('/money/expense');
        $this->get('/money/expense')->assertOk()->assertSee('Server')->assertSee('250.00 USD');
        $this->get('/money/income')->assertOk()->assertDontSee('Server');
        $this->get('/categories/expense')->assertOk();
        $this->get('/services')->assertOk();
        $this->get('/contracts')->assertOk();
        $this->get('/settings')->assertOk();
        $this->get('/team')->assertOk();
    }

    public function test_a_record_can_be_added_from_a_pop_up_beside_a_pick_list(): void
    {
        $this->signIn($this->business());

        // the pick lists of the contract form offer "+ New"
        $this->get('/contracts/create')->assertOk()->assertSee('data-quick-add="'.route('clients.create').'"', false)->assertSee('data-quick-add="'.route('services.create').'"', false);
        $this->get('/money/expense/create')->assertOk()->assertSee('data-quick-add="'.route('categories.create', ['type' => 'expense']).'"', false);

        // the pop-up gets only the form, and saving answers with the new record
        $this->get('/clients/create?modal=1')->assertOk()->assertSee('data-quick-form', false)->assertDontSee('app-sidebar', false);
        $this->postJson('/clients', ['name' => 'Quick client', '_quick' => 1])->assertOk()->assertJsonPath('names.0', 'Quick client')->assertJsonCount(1, 'ids');
        $this->postJson('/clients', ['name' => '', '_quick' => 1])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/categories/expense', ['name' => 'Fuel', 'is_active' => 1, '_quick' => 1])->assertOk();

        $this->get('/contracts/create')->assertOk()->assertSee('Quick client');
        $this->get('/money/expense/create')->assertOk()->assertSee('Fuel');
    }

    public function test_several_currencies_are_kept_apart_and_can_be_filtered_on_every_page(): void
    {
        $tenant = $this->business();
        $this->signIn($tenant);

        // the owner ticks AFN and PKR beside the main currency
        $this->put('/settings', ['name' => 'Acme', 'timezone' => 'UTC', 'currency' => 'USD', 'picked' => ['AFN', 'PKR'], 'currencies' => 'omr', 'remind_days' => '30, 7, 1, 0', 'remind_hour' => 8])->assertSessionHasNoErrors();
        $this->assertSame(['USD', 'AFN', 'PKR', 'OMR'], $tenant->fresh()->currencies);

        // reminder days are ticked, with room for others typed by hand
        $this->get('/settings')->assertOk()->assertSee('Afghan afghani')->assertSee('Pakistani rupee');
        $this->put('/settings', ['name' => 'Acme', 'timezone' => 'UTC', 'currency' => 'USD', 'picked' => ['AFN', 'PKR', 'OMR'], 'days' => [7, 1, 0], 'remind_days' => '45', 'remind_hour' => 9])->assertSessionHasNoErrors();
        $this->assertSame([45, 7, 1, 0], $tenant->fresh()->remind_days);
        $this->put('/settings', ['name' => 'Acme', 'timezone' => 'UTC', 'currency' => 'USD', 'remind_hour' => 9])->assertSessionHasErrors('remind_days');

        $this->contract(['title' => 'Dollar hosting', 'amount' => 60, 'currency' => 'USD']);
        $afn = $this->contract(['title' => 'Afghani support', 'amount' => 5000, 'currency' => 'AFN']);
        $this->contract(['title' => 'Rupee website', 'amount' => 30000, 'currency' => 'PKR']);
        $this->post('/contracts/'.$afn->id.'/pay', ['amount' => '5,000', 'paid_on' => '2026-11-14', 'advance' => 1])->assertRedirect();
        $person = Person::create(['name' => 'Karim']);
        $person->entries()->create(['direction' => 'gave', 'amount' => 10000, 'currency' => 'PKR', 'date' => '2026-11-01']);
        $person->entries()->create(['direction' => 'received', 'amount' => 20, 'currency' => 'USD', 'date' => '2026-11-02']);

        // all currencies side by side, never added together
        $this->get('/dues?when=all')->assertOk()->assertSee('Dollar hosting')->assertSee('Rupee website')->assertSee('60.00 USD')->assertSee('30,000.00 PKR');
        $this->get('/dashboard')->assertOk()->assertSee('5,000.00 AFN')->assertSee('10,000.00 PKR')->assertSee('20.00 USD');

        // one currency at a time
        $this->get('/dues?when=all&currency=PKR')->assertOk()->assertSee('Rupee website')->assertDontSee('Dollar hosting');
        $this->get('/contracts?currency=afn')->assertOk()->assertSee('Afghani support')->assertDontSee('Rupee website');
        $this->get('/money/income?currency=AFN')->assertOk()->assertSee('Afghani support');
        $this->get('/money/income?currency=USD')->assertOk()->assertDontSee('Afghani support');
        $this->get('/dashboard?currency=PKR')->assertOk()->assertSee('10,000.00 PKR')->assertDontSee('5,000.00 AFN')->assertDontSee('20.00 USD');
        $this->get('/people?currency=PKR')->assertOk()->assertSee('owes you 10,000.00 PKR')->assertDontSee('you owe 20.00 USD');
        $this->get('/people/'.$person->id.'?currency=USD')->assertOk()->assertSee('20.00 USD');
        $this->get('/reports?currency=AFN')->assertOk()->assertSee('AFN')->assertDontSee('30,000.00 PKR');
        $this->get('/services?currency=PKR')->assertOk();

        // a code the business does not use is ignored
        $this->get('/dues?when=all&currency=XYZ')->assertOk()->assertSee('Dollar hosting')->assertSee('Rupee website');
    }
}
