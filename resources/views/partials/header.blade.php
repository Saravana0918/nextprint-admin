{{-- resources/views/partials/header.blade.php --}}
{{-- Converted from header-basic.liquid (simplified & placeholders) --}}
@php
    // Replace these with real values if you have them.
    $rootUrl = config('app.url') ?? '/';
    $shopName = config('app.name') ?? 'NextPrint';
    // If you want dynamic cart count, pass $cartItemCount from controller (default 0)
    $cartItemCount = $cartItemCount ?? 0;
    // If you have customer object pass it; else null
    $customer = $customer ?? null;
    // Section/settings data: you can either pass an array $sectionSettings or leave defaults
    $sectionSettings = $sectionSettings ?? [
        'enable_transparent' => false,
        'padding_full_width' => 20,
        'gradient' => null,
        'background' => '#ffffff',
        'padding_top' => 8,
        'padding_bottom' => 8,
        'container' => 'lg',
    ];
@endphp

<link rel="stylesheet" href="{{ $themeCssUrl ?? '/path/to/theme.css' }}" />
<header class="header header-basic{{ $sectionSettings['enable_transparent'] ? ' header-basic--transparent' : '' }}"
        style="--spacing-l-r: {{ $sectionSettings['padding_full_width'] }}px;
               --bg-color: {{ $sectionSettings['gradient'] ?? $sectionSettings['background'] }};
               --p-top: {{ $sectionSettings['padding_top'] }}px;
               --p-bottom: {{ $sectionSettings['padding_bottom'] }}px">
    <div class="container container-{{ $sectionSettings['container'] }}">
        <div class="header-basic__content">
            {{-- NOTE: Shopify had blocks iteration. Here we build three regions: left, center, right --}}
            <div class="header-basic__item header-basic__item--conversion_group">
                <div class="header-top--left header__language_currency clearfix" style="--la-cu-color: #333; --text-color: #333">
                    <div class="header-top-right-group header-language_currency">
                        {{-- LANGUAGE / CURRENCY -- replace with your implementations or include partials --}}
                        {{-- Example placeholder: --}}
                        <div class="top-language-currency">
                            {{-- If you have language selector include a view --}}
                            {{-- @include('partials.top-language') --}}
                        </div>

                        {{-- Customer service text placeholder (pass via $customerServiceText) --}}
                        @if(!empty($customerServiceText ?? ''))
                            <div class="customer-service-text">{{ $customerServiceText }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="header-basic__item header-basic__item--logo">
                <div class="header-top--center clearfix">
                    <div class="header__logo" style="--logo-width: auto; --logo-svg-width: auto; --logo-font-size: 20px; --logo-font-weight: 600; --logo-color: #000;">
                        <div class="header__heading">
                            <a href="{{ $rootUrl }}" class="header__heading-link focus-inset">
                                {{-- If you have a logo URL pass $logoUrl from controller --}}
                                @if(!empty($logoUrl ?? ''))
                                    <img src="{{ $logoUrl }}" alt="{{ $logoAlt ?? $shopName }}" class="header__heading-logo" style="max-width:200px;height:auto;">
                                @else
                                    <span class="h2">{{ $shopName }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="header-basic__item header-basic__item--function_group">
                <div class="header-top--right header__icons clearfix">
                    {{-- Search (simple form) --}}
                    <div class="header__iconItem header__search">
                        <form action="{{ $searchUrl ?? url('/search') }}" method="get" role="search" class="search search-modal__form">
                            <div class="field">
                                <input class="search__input field__input" id="Search-In-Modal-Basic" type="search" name="q" value="{{ request('q') }}" placeholder="Search products" autocomplete="off">
                                <input type="hidden" name="options[prefix]" value="last">
                                <input type="hidden" name="type" value="product">
                                <button class="button search__button field__button" aria-label="Search">
                                    <!-- simple svg icon fallback -->
                                    🔍
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Customer / Wishlist / Cart icons --}}
                    <div class="header__iconItem header__group">
                        {{-- Account link --}}
                        @if($customer)
                            <div class="header__icon header__icon--account">
                                <a href="{{ route('account') ?? '/account' }}" class="link link--text">
                                    <!-- icon -->
                                    <span aria-hidden="true">👤</span>
                                    <span class="visually-hidden">Account</span>
                                </a>
                            </div>
                        @else
                            <div class="header__icon header__icon--account">
                                <a href="{{ route('login') ?? '/account/login' }}" class="link link--text">
                                    <span aria-hidden="true">👤</span>
                                    <span class="visually-hidden">Log in</span>
                                </a>
                            </div>
                        @endif

                        {{-- Wishlist placeholder --}}
                        <a href="{{ $wishlistUrl ?? '/wish-list' }}" class="header__icon header__icon--wishlist link link--text focus-inset">
                            ❤️
                        </a>

                        {{-- Cart --}}
                        <a href="{{ $cartUrl ?? '/cart' }}" class="header__icon header__icon--cart link link--text focus-inset" id="cart-icon-bubble">
                            🛒
                            <div class="cart-count-bubble">
                                @if($cartItemCount < 100)
                                    <span class="text" aria-hidden="true" data-cart-count>{{ $cartItemCount }}</span>
                                @endif
                                <span class="visually-hidden">Cart count: {{ $cartItemCount }}</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</header>

<script defer src="{{ $themeJsUrl ?? '/path/to/theme.js' }}"></script>
<script>
    // jQuery-dependent mobile menu adjustments in original; keep if jQuery is available
    function appendPrependMenuMobile() {
        if (window.innerWidth < 1025) {
            try {
                $('.header-top--wrapper .header-top--left .customer-service-text').appendTo('#navigation-mobile .site-nav-mobile.nav-account .wrapper-links');
            } catch(e) {}
        } else {
            try {
                $('#navigation-mobile .site-nav-mobile.nav-account .customer-service-text').appendTo('.header-top--wrapper .header-top--left .header-language_currency');
            } catch(e) {}
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        appendPrependMenuMobile();
        window.addEventListener('resize', appendPrependMenuMobile);
    });

    window.addEventListener('load', function() {
        var header = document.querySelector('.header-05');
        if (header) header.classList.add('loading-css');
    });

    if (document.body.classList.contains('template-index')) {
        var hb = document.querySelector('.header-basic--transparent');
        if (hb) hb.closest('.section-header-basic')?.classList.add('shb-transparent');
    }
</script>
