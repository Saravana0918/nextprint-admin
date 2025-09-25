<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .np-hidden { display: none !important; }
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; min-height: 220px; overflow: visible; background: #fff; border-radius:8px; padding:8px; }
    .np-stage img { width: 100%; height: auto; display:block; border-radius:6px; }
    .np-overlay { position:absolute; color:#D4AF37; text-shadow: 0 2px 6px rgba(0,0,0,0.35); white-space:nowrap; pointer-events:none; font-weight:700; text-transform:uppercase; letter-spacing:2px; display:flex; align-items:center; justify-content:center; user-select:none; line-height:1; }
    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .max-count{ display:none; }

    /* MOBILE-SPECIFIC STADIUM BACKGROUND */
    @media (max-width: 767px) {
      body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center; min-height:100vh; }

      /* shrink the main stage on mobile so it still shows properly */
      .np-stage { max-width:340px; margin:12px auto; background:#fff; padding:10px; border-radius:10px; }

      /* the panel that contains controls (which shows over stadium bg on mobile) */
      .np-mobile-bg {
        position: relative; /* parent for mobile overlays */
        background: transparent; /* let stadium background show through */
        padding: 12px;
        border-radius: 8px;
      }

      /* Mobile-only overlay that sits on top of the mobile controls area (i.e., on stadium BG) */
      .np-mobile-overlay {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: absolute;
        left: 0;
        right: 0;
        pointer-events: none;
        z-index: 40;
      }

      /* specific positions — tweak top% to move overlays vertically over stadium area */
      #np-mobile-prev-name { top: 10%; }
      #np-mobile-prev-num  { top: 28%; }

      /* overlay appearance on mobile: bigger, uppercase, centered */
      .np-mobile-overlay .np-overlay {
        width: 100%;
        text-align: center;
        padding: 0 8px;
      }

      .np-field-wrap input.form-control {
        background: transparent;
        border: none;
        border-bottom: 2px solid rgba(255,255,255,0.65);
        color:#fff;
        text-align:center;
        font-weight:700;
        text-transform:uppercase;
        letter-spacing:2px;
        font-size:18px;
        padding:12px 8px;
        border-radius:0;
      }
      .form-select { background:#fff; color:#222; border-radius:8px; margin-bottom:16px; }
      #np-atc-btn { display:block !important; width:100% !important; font-size:16px !important; padding:12px 14px !important; margin-top:10px; }

      /* hide desktop overlays inside stage on mobile to avoid duplicate */
      #np-prev-name, #np-prev-num { display: none !important; }
    }

    /* desktop keeps original look */
    @media (min-width: 768px) {
      /* ensure mobile overlays are hidden on desktop */
      #np-mobile-prev-name, #np-mobile-prev-num { display: none !important; }
    }

  </style>
</head>
<body class="py-4">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" crossorigin="anonymous" src="{{ $img }}" alt="Preview"
            onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay np-name font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay np-num  font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <div class="col-md-3 np-col order-2 order-md-1">
      <div class="border rounded p-3 np-mobile-bg">
        <h6 class="mb-3">Customize</h6>
        <div id="np-status" class="small text-muted mb-2">Checking methods…</div>
        <div id="np-note" class="small text-muted mb-3 d-none">Personalization not available for this product.</div>

        <!-- MOBILE OVERLAYS: visible only on mobile, positioned relative to .np-mobile-bg -->
        <div class="np-mobile-overlay" aria-hidden="true">
          <div id="np-mobile-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-mobile-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
        </div>

        <div id="np-controls" class="np-hidden">
          <div class="mb-3 np-field-wrap">
            <label for="np-num" class="form-label">Your Number</label>
            <input id="np-num" type="text" inputmode="numeric" maxlength="3" class="form-control" placeholder="Your number" autocomplete="off">
            <span class="max-count">MAX. 2</span>
            <div id="np-num-help" class="form-text">Digits only. 1–3 digits.</div>
            <div id="np-num-err" class="text-danger small d-none">Enter 1–3 digits only.</div>
          </div>

          <div class="mb-3 np-field-wrap">
            <label for="np-name" class="form-label">Your Name</label>
            <input id="np-name" type="text" maxlength="12" class="form-control" placeholder="Your name" autocomplete="off">
            <span class="max-count">MAX. 11</span>
            <div id="np-name-help" class="form-text">Only A–Z and spaces. 1–12 chars.</div>
            <div id="np-name-err" class="text-danger small d-none">Enter 1–12 letters/spaces only.</div>
          </div>

          <div class="mb-3">
            <label class="form-label font-label">Font</label>
            <select id="np-font" class="form-select">
              <option value="bebas">Bebas Neue (Bold)</option>
              <option value="anton">Anton</option>
              <option value="oswald">Oswald</option>
              <option value="impact">Impact</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label d-block color-label">Text Color</label>
            <div class="d-flex gap-2 flex-wrap mb-2">
              <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFFFFF"></button>
              <button type="button" class="np-swatch" data-color="#000000" style="background:#000000"></button>
              <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
              <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
              <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
            </div>
            <input id="np-color" type="color" class="form-control form-control-color mt-2" value="#D4AF37" title="Pick color">
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3 np-col order-3 order-md-3">
      <h4 class="mb-1">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <div class="text-muted mb-3">Vendor: {{ $product->vendor ?? '—' }} • ₹ {{ number_format((float)($displayPrice ?? ($product->min_price ?? 0)), 2) }}</div>

      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" id="np-product-id" name="product_id" value="{{ $product->id }}">
        <input type="hidden" id="np-shopify-id" name="shopify_product_id" value="{{ $product->shopify_product_id ?? '' }}">
        <input type="hidden" name="variant_id" id="np-variant-id" value="">
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" name="preview_data" id="np-preview-hidden">

        <div class="mb-3">
          <label class="form-label">Size</label>
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
            <option value="XXL">XXL</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Quantity</label>
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled>Add to Cart</button>
      </form>

      <div class="small-delivery text-muted mt-2">Button enables when both Name & Number are valid.</div>
    </div>
  </div>
</div>

<script>
  window.layoutSlots = {!! json_encode($layoutSlots, JSON_NUMERIC_CHECK) !!};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
</script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  function init(){
    const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
    const mobilePvName = $('np-mobile-prev-name'), mobilePvNum = $('np-mobile-prev-num');
    const ctrls = $('np-controls'), note = $('np-note'), status = $('np-status'), btn = $('np-atc-btn');
    const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};

    const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;
    function validate(){
      const okName = nameEl ? NAME_RE.test((nameEl.value||'').trim()) : true;
      const okNum = numEl ? NUM_RE.test((numEl.value||'').trim()) : true;
      if (nameEl) document.getElementById('np-name-err')?.classList.toggle('d-none', okName);
      if (numEl)  document.getElementById('np-num-err')?.classList.toggle('d-none', okNum);
      if (ctrls && !ctrls.classList.contains('np-hidden')) {
        if (btn) btn.disabled = !(okName && okNum && document.getElementById('np-size')?.value);
      } else {
        if (btn) btn.disabled = !(document.getElementById('np-size')?.value);
      }
      return okName && okNum;
    }

    function applyFontToElements(val, elements){
      const classes = ['font-bebas','font-anton','font-oswald','font-impact'];
      elements.forEach(el=>{
        if(!el) return;
        el.classList.remove(...classes);
      });
      const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
      const c = map[val] || 'font-bebas';
      elements.forEach(el=> el && el.classList.add(c));
    }

    function computeStageSize(){
      const imgW = (baseImg && baseImg.naturalWidth) ? baseImg.naturalWidth : (baseImg? baseImg.width : stage.clientWidth);
      const imgH = (baseImg && baseImg.naturalHeight) ? baseImg.naturalHeight : (baseImg? baseImg.clientHeight : stage.clientWidth);
      const stageW = stage.clientWidth || 300;
      const stageH = imgW ? Math.round((imgH * stageW)/imgW) : (baseImg? baseImg.clientHeight : 300);
      return {imgW, imgH, stageW, stageH};
    }

    function placeOverlay(el, slot, slotKey){
      if(!el || !slot || !stage) return;
      el.style.position = 'absolute';
      el.style.left = (slot.left_pct||0) + '%';
      el.style.top  = (slot.top_pct||0) + '%';
      el.style.width = (slot.width_pct||10) + '%';
      el.style.height = (slot.height_pct||10) + '%';
      el.style.display = 'flex';
      el.style.alignItems = 'center';
      el.style.justifyContent = 'center';
      el.style.boxSizing = 'border-box';
      el.style.padding = '0 4px';
      el.style.transform = 'rotate(' + ((slot.rotation||0)) + 'deg)';
      el.style.whiteSpace = 'nowrap';
      el.style.overflow = 'hidden';
      el.style.pointerEvents = 'none';
      el.style.zIndex = (slotKey === 'number' ? 60 : 50);

      const {imgW, imgH, stageW, stageH} = computeStageSize();
      const areaWpx = Math.max(8, Math.round(((slot.width_pct || 10)/100) * stageW));
      const areaHpx = Math.max(8, Math.round(((slot.height_pct || 10)/100) * stageH));

      const text = (el.textContent || '').toString().trim() || 'TEXT';
      const chars = Math.max(1, text.length);

      const isMobile = window.innerWidth <= 767;
      const heightFactorName = 0.86;
      const heightFactorNumber = isMobile ? 0.95 : 0.9;
      const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? heightFactorNumber : heightFactorName));

      const avgCharRatio = 0.55;
      const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));

      let numericShrink = 1.0;
      if (slotKey === 'number') numericShrink = isMobile ? 1.0 : 0.98;

      let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * numericShrink);
      const maxAllowed = Math.max(14, Math.floor(stageW * (isMobile ? 0.34 : 0.18)));
      fontSize = Math.max(8, Math.min(fontSize, maxAllowed));
      el.style.fontSize = fontSize + 'px';
      el.style.lineHeight = '1';
      el.style.fontWeight = '700';

      let attempts = 0;
      while (el.scrollWidth > el.clientWidth && fontSize > 7 && attempts < 30) {
        fontSize = Math.max(7, Math.floor(fontSize * 0.92));
        el.style.fontSize = fontSize + 'px';
        attempts++;
      }
    }

    function applyLayout(){
      if (!baseImg) return;
      if (!baseImg.complete || !baseImg.naturalWidth) return;
      if (layout.name) placeOverlay(pvName, layout.name, 'name');
      if (layout.number) placeOverlay(pvNum, layout.number, 'number');
    }

    function syncPreview(){
      if (pvName && nameEl) {
        const txt = (nameEl.value||'').toUpperCase();
        pvName.textContent = txt || 'YOUR NAME';
      }
      if (pvNum && numEl) {
        const numTxt = (numEl.value||'').replace(/\D/g,'');
        pvNum.textContent = numTxt || 'YOUR NUMBER';
      }

      // MOBILE overlays: show name/num over the stadium-bg area (only used on mobile)
      const isMobile = window.innerWidth <= 767;
      if (isMobile) {
        if (mobilePvName) {
          const txt = (nameEl && nameEl.value) ? nameEl.value.toUpperCase() : '';
          mobilePvName.textContent = txt || 'YOUR NAME';
        }
        if (mobilePvNum) {
          const n = (numEl && numEl.value) ? numEl.value.replace(/\D/g,'') : '';
          mobilePvNum.textContent = n || 'YOUR NUMBER';
        }
        // apply sizing for mobile overlays (approximate: full width containers)
        if (mobilePvName) {
          // mobile overlay sizing: base on parent width
          const parent = mobilePvName.parentElement;
          const parentW = parent ? parent.clientWidth : window.innerWidth;
          const baseFont = Math.max(12, Math.floor(parentW * 0.06));
          mobilePvName.style.fontSize = baseFont + 'px';
          mobilePvName.style.lineHeight = '1';
        }
        if (mobilePvNum) {
          const parent = mobilePvNum.parentElement;
          const parentW = parent ? parent.clientWidth : window.innerWidth;
          const baseFont = Math.max(18, Math.floor(parentW * 0.08));
          mobilePvNum.style.fontSize = baseFont + 'px';
          mobilePvNum.style.lineHeight = '1';
          mobilePvNum.style.fontWeight = '900';
        }
      }

      applyLayout();
    }

    function syncHidden(){
      document.getElementById('np-name-hidden').value  = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
      document.getElementById('np-num-hidden').value   = (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim();
      document.getElementById('np-font-hidden').value  = fontEl ? fontEl.value : '';
      document.getElementById('np-color-hidden').value = colorEl ? colorEl.value : '';
    }

    if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
    if (numEl)  numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
    if (fontEl) fontEl.addEventListener('change', ()=>{ 
      applyFontToElements(fontEl.value, [pvName, pvNum, mobilePvName, mobilePvNum]); 
      syncPreview(); syncHidden(); 
    });
    if (colorEl) colorEl.addEventListener('input', ()=>{ 
      if (pvName) pvName.style.color = colorEl.value; 
      if (pvNum) pvNum.style.color = colorEl.value; 
      if (mobilePvName) mobilePvName.style.color = colorEl.value;
      if (mobilePvNum) mobilePvNum.style.color = colorEl.value;
      syncHidden(); 
    });

    document.querySelectorAll('.np-swatch')?.forEach(b=>{
      b.addEventListener('click', ()=>{
        document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        if (colorEl) colorEl.value = b.dataset.color;
        if (pvName) pvName.style.color = b.dataset.color;
        if (pvNum) pvNum.style.color = b.dataset.color;
        if (mobilePvName) mobilePvName.style.color = b.dataset.color;
        if (mobilePvNum) mobilePvNum.style.color = b.dataset.color;
        syncHidden();
      });
    });

    applyFontToElements(fontEl ? fontEl.value : 'bebas', [pvName, pvNum, mobilePvName, mobilePvNum]);
    if (pvName && colorEl) pvName.style.color = colorEl.value;
    if (pvNum && colorEl) pvNum.style.color = colorEl.value;
    if (mobilePvName && colorEl) mobilePvName.style.color = colorEl.value;
    if (mobilePvNum && colorEl) mobilePvNum.style.color = colorEl.value;
    syncPreview(); syncHidden();

    if (baseImg) baseImg.addEventListener('load', applyLayout);
    window.addEventListener('resize', function(){ applyLayout(); syncPreview(); });
    setTimeout(applyLayout, 200);
    setTimeout(applyLayout, 800);

    // show/hide controls based on personalizationSupported flag
    if (typeof window.personalizationSupported !== 'undefined' && ctrls) {
      if (window.personalizationSupported) {
        status.textContent = 'Personalization supported.';
        note.classList.add('d-none');
        ctrls.classList.remove('np-hidden');
        if (btn) btn.disabled = true;
      } else {
        status.textContent = 'Personalization not available.';
        note.classList.remove('d-none');
        ctrls.classList.add('np-hidden');
        if (btn) btn.disabled = false;
      }
    }

    // wire up size change to revalidate
    document.getElementById('np-size')?.addEventListener('change', validate);
    document.getElementById('np-qty')?.addEventListener('input', validate);
  } // init

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
(function(){
  const atcForm = document.getElementById('np-atc-form');
  const btn = document.getElementById('np-atc-btn');

  function syncHiddenFields(){
    const nameEl = document.getElementById('np-name'), numEl = document.getElementById('np-num'),
          fontEl = document.getElementById('np-font'), colorEl = document.getElementById('np-color');

    document.getElementById('np-name-hidden').value  = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
    document.getElementById('np-num-hidden').value   = (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim();
    document.getElementById('np-font-hidden').value  = fontEl ? fontEl.value : '';
    document.getElementById('np-color-hidden').value = colorEl ? colorEl.value : '';
  }

  if (atcForm) {
    atcForm.addEventListener('submit', async function(evt){
      // validate size
      const size = document.getElementById('np-size')?.value || '';
      if (!size) {
        evt.preventDefault();
        alert('Please select a size.');
        return;
      }

      // if personalization visible, validate name & number with same rules
      const controlsHidden = document.getElementById('np-controls')?.classList.contains('np-hidden');
      if (!controlsHidden) {
        const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;
        const name = document.getElementById('np-name')?.value || '';
        const num  = document.getElementById('np-num')?.value || '';
        if (!NAME_RE.test(name.trim()) || !NUM_RE.test(num.trim())) {
          evt.preventDefault();
          alert('Please enter valid Name (A–Z, 1–12) and Number (1–3 digits).');
          return;
        }
      }

      evt.preventDefault();
      syncHiddenFields();

      if (btn) { btn.disabled = true; btn.setAttribute('aria-busy','true'); btn.innerText = 'Preparing...'; }

      try {
        const stage = document.getElementById('np-stage');
        const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('np-preview-hidden').value = dataUrl;

        // submit native POST to Laravel
        atcForm.submit();
      } catch (err) {
        console.error('Preview capture failed', err);
        alert('Failed to prepare preview. Try again.');
        if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerText = 'Add to Cart'; }
      }
    });
  }
})();
</script>

</body>
</html>
