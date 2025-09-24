{{-- resources/views/partials/footer.blade.php --}}
@php
    // Defaults / placeholders - pass real values from controller if available
    $sectionSettings = $sectionSettings ?? [
        'padding_full_width' => 20,
        'container' => 'lg',
        'banner_animation' => null,
        'show_footer_top_bg_img' => false,
    ];

    // $footerBlocks should be an array of blocks (each block: ['type'=>'text'|'link_list'|'newsletter', 'settings'=>[...] , 'content'=>...])
    $footerBlocks = $footerBlocks ?? [];
    $footerBottom = $footerBottom ?? null; // array|null
    $hasLogoImage = $hasLogoImage ?? false;

    // Simple helpers for newsletter/text placeholders (if you don't implement dynamic blocks)
    $newsletterBlock = $newsletterBlock ?? null;
    $textBlock = $textBlock ?? null;
@endphp

{{-- If theme CSS/JS already loaded in header, ok — otherwise pass $themeCssUrl / $themeJsUrl from controller --}}
<link rel="stylesheet" href="{{ $themeCssUrl ?? '/path/to/theme.css' }}" />
<link rel="stylesheet" href="{{ $componentListPaymentCss ?? '/path/to/component-list-payment.css' }}" />

<style>
/* Simplified/ported CSS from original. A few numeric values come from $textBlock / $newsletterBlock if provided */
.footer-7 .footer__content-text{padding-top:35px;padding-bottom:35px;text-align:center}
@media (min-width: 1025px) {
    .footer-7.has-logo-image .footer-block__details .image_logo { margin-top: {{ $textBlock['settings']['logo_margin_top'] ?? 0 }}px; }
    .footer-7 .footer-block__newsletter .footer-block__subheading { margin-top: {{ $newsletterBlock['settings']['mg_top_des'] ?? 30 }}px; margin-bottom: {{ $newsletterBlock['settings']['mg_bottom_des'] ?? 0 }}px; }
}
@media (max-width: 767px) {
    .footer-7 .footer__content-text .footer_text-wrapper{display:block}.footer-7 .footer__content-text .footer_text-wrapper a{margin:0}.footer-7 .footer__content-text{padding-top:15px}
}
/* You may want to move this CSS to your theme CSS file and scope it carefully. */
</style>

<footer class="footer footer-7 {{ $hasLogoImage ? 'has-logo-image' : '' }} {{ ($sectionSettings['banner_animation'] ?? null) == 'effect_fade_up' ? 'scroll-trigger animate--slide-in' : '' }}"
        style="--spacing-l-r: {{ $sectionSettings['padding_full_width'] ?? 20 }}px"
        {{ ($sectionSettings['banner_animation'] ?? null) == 'effect_fade_up' ? 'data-cascade' : '' }}>

  @if(count($footerBlocks) > 0)
    <div class="footer__content-top {{ ($sectionSettings['show_footer_top_bg_img'] ?? false) ? 'footer__content-bg' : '' }}">
      <div class="container container-{{ $sectionSettings['container'] ?? 'lg' }}">
        <div class="halo-row column-{{ $sectionSettings['columns'] ?? count($footerBlocks) }}">
            {{-- Loop through supplied blocks. Provide fallbacks if you don't supply sub-partials --}}
            @foreach($footerBlocks as $i => $block)
                @php $type = $block['type'] ?? 'text'; @endphp

                @if($type === 'text')
                    {{-- If you have a partial for text-column, include it. Otherwise fallback simple rendering --}}
                    @if(View::exists('partials.footer-text-column'))
                        @include('partials.footer-text-column', ['block' => $block])
                    @else
                        <div class="footer-block__item footer-block__text">
                            @if(!empty($block['settings']['logo']))
                                <div class="image_logo">
                                    <img src="{{ $block['settings']['logo'] }}" alt="{{ $block['settings']['logo_alt'] ?? '' }}" style="max-width:160px;height:auto;">
                                </div>
                            @endif
                            <div class="footer-text">
                                {!! $block['content'] ?? ($block['settings']['text'] ?? '') !!}
                            </div>
                        </div>
                    @endif

                @elseif($type === 'link_list')
                    @if(View::exists('partials.footer-links-column'))
                        @include('partials.footer-links-column', ['block' => $block])
                    @else
                        <div class="footer-block__item footer-block__links">
                            <h4>{{ $block['settings']['title'] ?? 'Links' }}</h4>
                            <ul>
                                @foreach($block['items'] ?? [] as $li)
                                    <li><a href="{{ $li['url'] ?? '#' }}">{{ $li['title'] ?? 'Link' }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                @elseif($type === 'newsletter')
                    @if(View::exists('partials.footer-newsletter-column'))
                        @include('partials.footer-newsletter-column', ['block' => $block])
                    @else
                        <div class="footer-block__item footer-block__newsletter">
                            <div class="footer-block__newsletter-form">
                                <form action="{{ $newsletterAction ?? route('newsletter.subscribe', [], false) }}" method="post">
                                    @csrf
                                    <div class="field">
                                        <input type="email" name="email" placeholder="{{ $block['settings']['placeholder'] ?? 'Enter your email' }}" required class="field__input">
                                        <button type="submit" class="newsletter-form__button">{{ $block['settings']['button_text'] ?? 'Subscribe' }}</button>
                                    </div>
                                </form>
                                <p class="footer-block__newsletter-sub">{{ $block['settings']['subtext'] ?? '' }}</p>
                            </div>
                        </div>
                    @endif
                @else
                    {{-- unknown block type fallback --}}
                    <div class="footer-block__item">
                        {!! $block['content'] ?? '' !!}
                    </div>
                @endif

            @endforeach
        </div>
      </div>
    </div>

    {{-- Optional footer bottom block --}}
    @if(!empty($footerBottom) && View::exists('partials.footer-bottom'))
        @include('partials.footer-bottom', ['block' => $footerBottom])
    @elseif(!empty($footerBottom))
        <div class="footer-bottom">
            <div class="container container-{{ $sectionSettings['container'] ?? 'lg' }}">
                <small>{!! $footerBottom['content'] ?? ($footerBottom['settings']['text'] ?? '') !!}</small>
            </div>
        </div>
    @endif

  @endif
</footer>

<script type="text/javascript">
  function initDropdownColumnsFooter() {
    var footerColumnTitle = document.querySelectorAll('.footer-7 [data-toggle-column-footer]');
    if (footerColumnTitle.length > 0) {
      for (var i = 0; i < footerColumnTitle.length; i++) {
        (function (i) {
          footerColumnTitle[i].addEventListener('click', function (event) {
            var el = event.currentTarget;
            el.classList.toggle('is-clicked');
          });
        })(i);
      }
    }
  }
  document.addEventListener('DOMContentLoaded', initDropdownColumnsFooter);
</script>
