@extends('layouts.app', ['title' => $person->name])

@section('content')
<x-page-header :title="$person->name" icon="handshake" :subtitle="$person->phone" :crumbs="[[__('People & Loans'), route('people.index')], [$person->name]]">
    <a class="btn btn-outline-soft" href="{{ route('people.edit', ['id' => $person->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card h-auto">
            <div class="card-body">
                <div class="small fw-semibold text-muted mb-2">{{ __('Where you stand') }}</div>
                <div class="fs-5">{{ \App\Http\Controllers\PersonController::balanceCell($balances) }}</div>
                @if ($person->notes)
                    <p class="small text-muted mt-3 mb-0" style="white-space:pre-line">{{ $person->notes }}</p>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('people.entries.store', ['id' => $person->id]) }}" class="card h-auto">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">{{ __('New entry') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="btn-group w-100" role="group" aria-label="{{ __('Direction') }}">
                            <input type="radio" class="btn-check" name="direction" id="dir-gave" value="gave" autocomplete="off" @checked(old('direction', 'gave') === 'gave')>
                            <label class="btn btn-outline-primary" for="dir-gave"><x-icon name="arrow-up" class="me-1" /> {{ __('I gave money') }}</label>
                            <input type="radio" class="btn-check" name="direction" id="dir-received" value="received" autocomplete="off" @checked(old('direction') === 'received')>
                            <label class="btn btn-outline-primary" for="dir-received"><x-icon name="arrow-down" class="me-1" /> {{ __('I received money') }}</label>
                        </div>
                        <div class="form-text">{{ __('"I gave": you lent money, or paid back what you borrowed. "I received": you borrowed money, or they paid you back.') }}</div>
                        @error('direction')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-7">
                        <label class="form-label" for="amount">{{ __('Amount') }} <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" dir="ltr" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" autocomplete="off" required>
                        @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-5">
                        <label class="form-label" for="currency">{{ __('Currency') }}</label>
                        <select class="form-select" id="currency" name="currency" data-plain>
                            @foreach (currencies() as $code)
                                <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <x-date name="date" id="date" :value="old('date', today_iso())" required />
                        @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="due_date">{{ __('To be settled by') }}</label>
                        <x-date name="due_date" id="due_date" :value="old('due_date')" />
                        @error('due_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="note">{{ __('Note') }}</label>
                        <input type="text" class="form-control" id="note" name="note" value="{{ old('note') }}" maxlength="255">
                        <div class="form-text">{{ __('With a "to be settled by" date, the daily email reminds you on that day.') }}</div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
        </form>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Entries') }}</h5></div>
            <div class="card-body">
                @if ($entries->isEmpty())
                    <div class="empty-state">
                        <div class="es-icon"><x-icon name="handshake" /></div>
                        <h3>{{ __('Nothing here yet') }}</h3>
                        <p>{{ __('Write down every time money moves between you and :name.', ['name' => $person->name]) }}</p>
                    </div>
                @else
                    <table class="table table-hover w-100">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('What') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                                    <td>{{ $entry->direction === 'gave' ? __('I gave') : __('I received') }}</td>
                                    <td>{{ $entry->note }}@if ($entry->due_date)<span class="d-block small text-muted">{{ __('to be settled by :date', ['date' => fmt_date($entry->due_date)]) }}</span>@endif</td>
                                    <td class="text-end fw-semibold text-nowrap {{ $entry->direction === 'gave' ? 'bal-due' : 'bal-owe' }}" dir="ltr">{{ $entry->direction === 'gave' ? '+' : '−' }}{{ money($entry->amount, $entry->currency) }}</td>
                                    <td class="text-end">
                                        <div class="act-group">
                                            <form method="POST" action="{{ route('people.entries.destroy', ['id' => $person->id, 'entry' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-act kind="delete" />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $entries->links('partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
