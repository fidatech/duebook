{{--
    Currency chips above a list or a report: All, then every currency the business works with.
    The chosen one is in the address (?currency=AFN) and is read with picked_currency().
    Nothing is shown to a business with a single currency.
--}}
@php
    $currencyCodes = currencies();
    $currencyNow = picked_currency();
@endphp
@if (count($currencyCodes) > 1)
    <div class="d-flex flex-nowrap align-items-center gap-2 mb-3 overflow-auto pb-1" role="group" aria-label="{{ __('Currency') }}">
        <span class="small text-muted text-nowrap"><x-icon name="coins" class="me-1" />{{ __('Currency') }}</span>
        <a href="{{ request()->fullUrlWithQuery(['currency' => null, 'page' => null]) }}" class="btn btn-sm text-nowrap {{ $currencyNow ? 'btn-light-secondary' : 'btn-primary' }}">{{ __('All') }}</a>
        @foreach ($currencyCodes as $code)
            <a href="{{ request()->fullUrlWithQuery(['currency' => $code, 'page' => null]) }}" class="btn btn-sm text-nowrap {{ $currencyNow === $code ? 'btn-primary' : 'btn-light-secondary' }}">{{ $code }}</a>
        @endforeach
    </div>
@endif
