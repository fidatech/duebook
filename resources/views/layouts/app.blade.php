@php
    // days of free trial left, shown as a bar on every page until the business subscribes
    $trialLeft = tenant()?->status === \App\Models\Tenant::TRIAL && tenant()->isUsable() ? tenant()->daysLeft() : null;
    $quick = [
        ['Receive a payment', 'hand-holding-dollar', route('dues.index', ['when' => 'all']), 'success'],
        ['New client', 'user-plus', route('clients.create'), 'primary'],
        ['New contract', 'file-signature', route('contracts.create'), 'info'],
        ['Add income', 'arrow-trend-up', route('money.create', ['type' => 'income']), 'teal'],
        ['Add expense', 'receipt', route('money.create', ['type' => 'expense']), 'danger'],
        ['People & Loans', 'handshake', route('people.index'), 'warning'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-sidebar="light" data-layout="vertical" data-primary="blue" data-width="fluid" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route('dashboard') }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="app-wrapper">
    @include('partials.sidebar')
    <div class="app-main">
        @include('partials.topbar')
        <main class="app-content">
            <div class="content-container">
                @if ($trialLeft !== null && ! request()->routeIs('billing.*'))
                    <a href="{{ route('billing.show') }}" class="alert alert-warning d-flex align-items-center gap-2 text-decoration-none py-2">
                        <x-icon name="hourglass-half" />
                        <span class="flex-grow-1">{{ $trialLeft > 0 ? trans_choice('{1} Your free trial ends tomorrow.|[2,*] :count days of your free trial are left.', $trialLeft, ['count' => $trialLeft]) : __('Your free trial ends today.') }} <strong>{{ __('Subscribe') }}</strong></span>
                        <x-icon name="chevron-right" />
                    </a>
                @endif
                @yield('content')
            </div>
        </main>
        <footer class="app-footer">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <nav><span class="text-muted">{{ __('app.by') }} <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></span></nav>
        </footer>
    </div>
</div>

{{-- the bar at the bottom of a phone or tablet: the system feels like an installed app --}}
<nav class="app-tabbar" aria-label="{{ __('app.nav.menu') }}">
    <a href="{{ route('dashboard') }}" class="tab-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><x-icon name="house" /><em>{{ __('app.nav.home') }}</em></a>
    <a href="{{ route('dues.index') }}" class="tab-item {{ request()->routeIs('dues.*') ? 'active' : '' }}"><x-icon name="bell" /><em>{{ __('app.nav.due') }}</em></a>
    <button type="button" class="tab-item tab-main" data-bs-toggle="modal" data-bs-target="#addModal"><span><x-icon name="plus" /></span><em>{{ __('app.nav.add') }}</em></button>
    <a href="{{ route('money.index', ['type' => 'income']) }}" class="tab-item {{ request()->routeIs('money.*') ? 'active' : '' }}"><x-icon name="wallet" /><em>{{ __('app.nav.money') }}</em></a>
    <button type="button" class="tab-item" id="tabMenu"><x-icon name="grip" /><em>{{ __('app.nav.menu') }}</em></button>
</nav>

<div class="modal fade" id="addModal" tabindex="-1" aria-label="{{ __('app.nav.add') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('What do you want to do?') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.actions.cancel') }}"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    @foreach ($quick as [$label, $icon, $url, $tone])
                        <div class="col-6">
                            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                                <div class="card-body text-center py-3">
                                    <span class="sc-icon icon-soft {{ $tone }} mx-auto mb-2" style="width:44px;height:44px;font-size:18px"><x-icon :name="$icon" /></span>
                                    <div class="small fw-semibold">{{ __($label) }}</div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="searchModal" tabindex="-1" aria-label="{{ __('app.nav.search') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable search-dialog">
        <div class="modal-content">
            <div class="search-input"><x-icon name="magnifying-glass" /><input type="text" id="searchInput" placeholder="{{ __('app.nav.search_placeholder') }}" autocomplete="off"><kbd>Esc</kbd></div>
            <div class="modal-body p-2" id="searchResults"></div>
        </div>
    </div>
</div>

<form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>
<form id="noticesReadForm" method="POST" action="{{ route('notices.read') }}" hidden>@csrf</form>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
