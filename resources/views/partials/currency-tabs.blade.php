{{--
    The currency switch above a list or a report: All, then every currency the business works with.
    The chosen one is in the address (?currency=AFN) and is read with picked_currency().
    Nothing is shown to a business with a single currency.
--}}
@php
    $currencyCodes = currencies();
    $currencyNow = picked_currency();
@endphp
@if (count($currencyCodes) > 1)
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="seg-label"><x-icon name="coins" class="me-1" />{{ __('Currency') }}</span>
        <div class="seg" role="group" aria-label="{{ __('Currency') }}">
            <a href="{{ request()->fullUrlWithQuery(['currency' => null, 'page' => null]) }}" class="{{ $currencyNow ? '' : 'active' }}">{{ __('All') }}</a>
            @foreach ($currencyCodes as $code)
                <a href="{{ request()->fullUrlWithQuery(['currency' => $code, 'page' => null]) }}" class="{{ $currencyNow === $code ? 'active' : '' }}">{{ $code }}</a>
            @endforeach
        </div>
    </div>
@endif
