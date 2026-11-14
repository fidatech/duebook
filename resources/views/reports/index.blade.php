@extends('layouts.app', ['title' => __('Reports')])

@php
    $monthName = fn (int $m) => date('M', mktime(0, 0, 0, $m, 1));
@endphp

@section('content')
<x-page-header :title="__('Reports')" icon="chart-column">
    <form method="GET">
        @if (picked_currency())<input type="hidden" name="currency" value="{{ picked_currency() }}">@endif
        <select class="form-select" name="year" onchange="this.form.submit()" aria-label="{{ __('Year') }}" data-plain>
            @foreach ($years as $option)
                <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
            @endforeach
        </select>
    </form>
</x-page-header>

@include('partials.currency-tabs')

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ __('Money you can expect') }}</h5>
        <div class="card-subtitle small text-muted">{{ __('From active contracts, by their due dates, for the next 12 months.') }}</div>
    </div>
    <div class="card-body">
        @if (! collect($forecast)->filter()->count())
            <p class="text-muted mb-0">{{ __('No active contracts with a due date yet.') }}</p>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Expected') }}</th></tr></thead>
                <tbody>
                    @foreach ($forecast as $key => $totals)
                        @continue($key === 'late' && ! $totals)
                        <tr>
                            <td class="{{ $key === 'late' ? 'bal-owe fw-semibold' : '' }}">{{ $key === 'late' ? __('Late (should have come already)') : date('F Y', strtotime($key.'-01')) }}</td>
                            <td class="text-end fw-semibold" dir="ltr">{{ $totals ? sums($totals) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

@forelse ($months as $currency => $byMonth)
    @php
        $in = 0;
        $out = 0;
    @endphp
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Income and expenses of :year', ['year' => $year]) }} · {{ $currency }}</h5></div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Income') }}</th><th class="text-end">{{ __('Expenses') }}</th><th class="text-end">{{ __('Left') }}</th></tr></thead>
                <tbody>
                    @for ($m = 1; $m <= 12; $m++)
                        @php
                            $income = $byMonth[$m]['income'] ?? 0;
                            $expense = $byMonth[$m]['expense'] ?? 0;
                            $in += $income;
                            $out += $expense;
                        @endphp
                        @continue(! $income && ! $expense)
                        <tr>
                            <td>{{ $monthName($m) }}</td>
                            <td class="text-end" dir="ltr">{{ money($income) }}</td>
                            <td class="text-end" dir="ltr">{{ money($expense) }}</td>
                            <td class="text-end fw-semibold {{ $income - $expense < 0 ? 'bal-owe' : 'bal-due' }}" dir="ltr">{{ money($income - $expense) }}</td>
                        </tr>
                    @endfor
                    <tr class="fw-bold">
                        <td>{{ __('Whole year') }}</td>
                        <td class="text-end" dir="ltr">{{ money($in) }}</td>
                        <td class="text-end" dir="ltr">{{ money($out) }}</td>
                        <td class="text-end {{ $in - $out < 0 ? 'bal-owe' : 'bal-due' }}" dir="ltr">{{ money($in - $out) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card"><div class="card-body"><p class="text-muted mb-0">{{ __('No income or expenses in :year.', ['year' => $year]) }}</p></div></div>
@endforelse

@if ($categories->isNotEmpty())
    <div class="row g-3">
        @foreach (['income' => 'Income by category', 'expense' => 'Expenses by category'] as $type => $heading)
            <div class="col-12 col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __($heading) }} · {{ $year }}</h5></div>
                    <div class="card-body">
                        <table class="table table-hover w-100">
                            <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                            <tbody>
                                @forelse ($categories->where('type', $type) as $row)
                                    <tr><td>{{ $row->category ?: __('No category') }}</td><td class="text-end fw-semibold" dir="ltr">{{ money($row->total, $row->currency) }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted">{{ __('Nothing recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
