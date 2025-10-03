<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* fonts */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    /* preview stage */
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position:absolute; font-weight:700; text-transform:uppercase; letter-spacing:2px; white-space:nowrap; display:flex; align-items:center; justify-content:center; pointer-events:none; }

    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
    body { background-color : #929292; }

    /* desktop helpers */
    .desktop-display{ color:white; font-family: "Roboto Condensed", sans-serif; font-weight: bold; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:350px; }
    .hide-on-mobile { display: none !important; }

    /* MOBILE rules */
    @media (max-width: 767px) {
      body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center center; background-repeat: no-repeat; min-height: 100vh; position: relative; margin-top: -70px; }
      body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 5; pointer-events: none; }
      .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }

      /* stage tweaks */
      .np-stage { padding: 12px; background: transparent; box-sizing: border-box; border-radius: 10px; z-index: 100; position: relative !important; }
      .np-stage img#np-base { display:block; width:100%; height:auto; border-radius:8px; background-color:#f6f6f6; box-shadow: 0 6px 18px rgba(0,0,0,0.35); border: 3px solid rgba(255,255,255,0.12); position: relative; z-index: 14; }

      /* floating add-to-cart */
      #np-atc-btn { position: fixed !important; top: 12px !important; right: 12px !important; z-index: 100020 !important; width: 130px !important; height: 44px !important; border-radius: 28px !important; box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important; }

      /* mobile overlay controls (single set only) */
      .np-mobile-controls {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 92%;
        max-width: 420px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: center;
        z-index: 100030;
        pointer-events: auto;
      }
      .np-mobile-controls .mobile-input {
        width: 100%;
        box-sizing: border-box;
        background: rgba(255,255,255,0.95);
        border: none;
        border-radius: 6px;
        padding: 10px 12px;
        font-weight: 700;
        text-transform: uppercase;
        text-align: center;
        letter-spacing: 1px;
        font-size: 16px;
        color: #222;
      }
      .np-mobile-controls .mobile-num { font-size: 28px; padding: 10px 12px; }
      .np-mobile-controls .max-count { font-size: 12px; font-weight: 800; color: rgba(255,255,255,0.95); background: rgba(0,0,0,0.35); padding: 4px 8px; border-radius: 12px; }

      /* ensure controls above overlays */
      #np-name, #np-num, #np-font, #np-color, .np-swatch, #np-size, #np-qty { z-index: 100040 !important; position: relative !important; }

      /* make stage overlays non-interactive */
      .np-stage .np-overlay { pointer-events: none !important; }
    }
  </style>
</head>
<body class="body-padding">

@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <!-- preview -->
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" crossorigin="anonymous" src="{{ $img }}" alt="Preview"
               onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
          <!-- Mobile overlay container injected by JS (only one) -->
        </div>
      </div>
    </div>

    <!-- controls -->
    <div class="col-md-3 np-col order-2 order-md-1" id="np-controls">
      <!-- Desktop controls; these remain in DOM but mobile injects its own single inputs and syncs them -->
      <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
      <input id="np-num" type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
      <select id="np-font" class="form-select mb-2">
        <option value="bebas">Bebas Neue</option>
        <option value="anton">Anton</option>
        <option value="oswald">Oswald</option>
        <option value="impact">Impact</option>
      </select>
      <div class="mb-2">
        <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFF"></button>
        <button type="button" class="np-swatch" data-color="#000000" style="background:#000"></button>
        <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
        <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
        <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
      </div>
      <input id="np-color" type="color" class="form-control form-control-color mt-2" value="#D4AF37">
    </div>

    <!-- purchase + team -->
    <div class="col-md-3 np-col order-3 order-md-3 right-layout">
      <h4 class="desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>

      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" name="preview_data" id="np-preview-hidden">
        <input type="hidden" name="product_id" id="np-product-id" value="{{ $product->id ?? $product->local_id ?? '' }}">
        <input type="hidden" name="shopify_product_id" id="np-shopify-product-id" value="{{ $product->shopify_product_id ?? $product->shopify_id ?? '' }}">
        <!-- IMPORTANT: variant_gid holds full Shopify gid like 'gid://shopify/ProductVariant/123456789' -->
        <input type="hidden" name="variant_gid" id="np-variant-gid" value="">

        <div class="mb-2">
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            <option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option>
          </select>
        </div>
        <div class="mb-2">
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary" disabled>Add to Cart</button>
        <a href="#" class="btn btn-success" id="btn-add-team" style="margin-left:8px;">Add Team Players</a>
      </form>
    </div>
  </div>
</div>

{{-- server-provided layoutSlots and a variantMap (optional) --}}
<script> window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!}; window.variantMap = {!! json_encode($variantMap ?? null) !!}; window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }}; </script>

<!-- html2canvas -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
(function(){
  // helpers
  const $ = id => document.getElementById(id);
  const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const btn = $('np-atc-btn'), form = $('np-atc-form'), addTeam = $('btn-add-team');
  const sizeEl = $('np-size'), qtyEl = $('np-qty'), variantGidHidden = $('np-variant-gid');
  const layout = window.layoutSlots || {};

  const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

  function applyFont(val){
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    [pvName,pvNum].forEach(el=>{ if(el) el.className = 'np-overlay ' + cls; });
  }

  function computeStageSize(){
    if (!baseImg || !stage) return null;
    const stageRect = stage.getBoundingClientRect();
    const imgRect = baseImg.getBoundingClientRect();
    return {
      offsetLeft: Math.round(imgRect.left - stageRect.left),
      offsetTop:  Math.round(imgRect.top  - stageRect.top),
      imgW: Math.max(1, imgRect.width), imgH: Math.max(1, imgRect.height),
      stageW: Math.max(1, stageRect.width), stageH: Math.max(1, stageRect.height)
    };
  }

  function placeOverlay(el, slot, slotKey){
    if(!el || !slot) return;
    const s = computeStageSize(); if(!s) return;

    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||0)/100) * s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const centerY = Math.round(s.offsetTop + ((slot.top_pct||0)/100) * s.imgH + ((slot.height_pct||0)/200)*s.imgH);

    const areaWpx = Math.max(8, Math.round(((slot.width_pct||10)/100) * s.imgW));
    const areaHpx = Math.max(8, Math.round(((slot.height_pct||10)/100) * s.imgH));

    el.style.position = 'absolute';
    el.style.left = centerX + 'px';
    el.style.top  = centerY + 'px';
    el.style.width = areaWpx + 'px';
    el.style.height = areaHpx + 'px';
    el.style.transform = 'translate(-50%,-50%) rotate(' + ((slot.rotation||0)) + 'deg)';
    el.style.display = 'flex';
    el.style.alignItems = 'center';
    el.style.justifyContent = 'center';
    el.style.boxSizing = 'border-box';
    el.style.padding = '0 4px';
    el.style.whiteSpace = 'nowrap';
    el.style.overflow = 'hidden';
    el.style.pointerEvents = 'none';
    el.style.zIndex = (slotKey === 'number' ? 60 : 50);

    const text = (el.textContent || '').toString().trim() || 'TEXT';
    const chars = Math.max(1, text.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile?1.05:1) : 1));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let fontSize = Math.floor(Math.min(heightCandidate, widthCap));
    const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
    fontSize = Math.max(8, Math.min(fontSize, maxAllowed));
    el.style.fontSize = Math.floor(fontSize * 1.10) + 'px';
    el.style.lineHeight = '1';
    el.style.fontWeight = '700';

    // shrink if overflow
    let attempts=0;
    while (el.scrollWidth > el.clientWidth && fontSize > 7 && attempts < 30) {
      fontSize = Math.max(7, Math.floor(fontSize * 0.92));
      el.style.fontSize = fontSize + 'px';
      attempts++;
    }
  }

  function applyLayout(){
    if (!baseImg || !baseImg.complete) return;
    if (layout && layout.name) placeOverlay(pvName, layout.name, 'name'); else { pvName.style.left='50%'; pvName.style.top='30%'; pvName.style.transform='translate(-50%,-50%)'; }
    if (layout && layout.number) placeOverlay(pvNum, layout.number, 'number'); else { pvNum.style.left='50%'; pvNum.style.top='60%'; pvNum.style.transform='translate(-50%,-50%)'; }
  }

  function syncPreview(){
    if (pvName && nameEl) pvName.textContent = (nameEl.value||'NAME').toUpperCase();
    if (pvNum && numEl) pvNum.textContent = (numEl.value||'09').replace(/\D/g,'');
    applyLayout();
  }

  function syncHidden(){
    $('np-name-hidden').value = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
    $('np-num-hidden').value  = (numEl  ? (numEl.value||'') : '').replace(/\D/g,'').trim();
    $('np-font-hidden').value = fontEl ? fontEl.value : '';
    $('np-color-hidden').value = colorEl ? colorEl.value : '';

    // set variant gid if you have variantMap available on client
    const size = sizeEl?.value || '';
    if (window.variantMap && size) {
      const gid = window.variantMap[size] || '';
      if (gid) variantGidHidden.value = gid;
    }
  }

  // enable/disable add to cart
  function checkEnableATC(){
    const okName = NAME_RE.test((nameEl?.value||'').trim());
    const okNum = NUM_RE.test((numEl?.value||'').trim());
    if (btn) btn.disabled = !(okName && okNum && !!sizeEl?.value);
  }

  // wire events
  if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); syncHidden(); checkEnableATC(); });
  if (numEl)  numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); syncHidden(); checkEnableATC(); });
  if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncHidden(); });
  if (colorEl) colorEl.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

  document.querySelectorAll('.np-swatch').forEach(b => {
    b.addEventListener('click', ()=> {
      document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      if (colorEl) colorEl.value = b.dataset.color;
      if (pvName) pvName.style.color = b.dataset.color;
      if (pvNum) pvNum.style.color = b.dataset.color;
      syncHidden();
    });
  });

  // variant map -> populate variant gid on size change
  sizeEl?.addEventListener('change', ()=> { syncHidden(); checkEnableATC(); });

  applyFont(fontEl?.value || 'bebas');
  if (pvName && colorEl) pvName.style.color = colorEl.value;
  if (pvNum && colorEl) pvNum.style.color = colorEl.value;
  syncPreview(); syncHidden(); checkEnableATC();

  baseImg.addEventListener('load', ()=> setTimeout(applyLayout, 60));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('orientationchange', ()=> setTimeout(applyLayout, 200));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  // Add-to-cart submit handler: capture preview and post
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();
    // basic validation
    if (!sizeEl.value) { alert('Please select a size.'); return; }
    if (!(NAME_RE.test(nameEl.value||'') && NUM_RE.test(numEl.value||''))) { alert('Invalid name/number'); return; }

    syncHidden();
    btn.disabled = true; btn.textContent = 'Preparing...';
    try {
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
      const dataUrl = canvas.toDataURL('image/png');
      $('np-preview-hidden').value = dataUrl;

      // submit form normally (let backend handle the GraphQL request)
      // either use fetch or fallback to regular submit:
      const fd = new FormData(form);
      // include variant_gid if present
      if (variantGidHidden && variantGidHidden.value) fd.set('variant_gid', variantGidHidden.value);

      // post to server and expect JSON
      const token = document.querySelector('input[name="_token"]')?.value || '';
      const resp = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept':'application/json' } });
      if (resp.redirected) { window.location.href = resp.url; return; }
      const data = await resp.json().catch(()=>null);
      if (!resp.ok) {
        const msg = (data && (data.error||data.message)) ? (data.error||data.message) : 'Add to cart failed';
        alert(msg);
        return;
      }
      // success behaviour: either redirect or show message
      if (data && data.checkoutUrl) { window.location.href = data.checkoutUrl; }
      else alert('Added to cart.');
    } catch (err) {
      console.error('ATC error', err);
      alert('Something went wrong');
    } finally {
      btn.disabled = false; btn.textContent = 'Add to Cart';
    }
  });

  // Add Team nav:
  addTeam?.addEventListener('click', function(e){
    e.preventDefault();
    const qs = new URLSearchParams();
    if ($('np-product-id')?.value) qs.set('product_id', $('np-product-id').value);
    if (nameEl?.value) qs.set('prefill_name', nameEl.value);
    if (numEl?.value) qs.set('prefill_number', numEl.value);
    if (fontEl?.value) qs.set('prefill_font', fontEl.value);
    if (colorEl?.value) qs.set('prefill_color', colorEl.value);
    if (sizeEl?.value) qs.set('prefill_size', sizeEl.value);
    const base = "{{ route('team.create') }}";
    window.location.href = base + '?' + qs.toString();
  });

  // MOBILE: inject a single mobile overlay input set (syncs to desktop inputs)
  function mobileOverlaySetup(){
    if (window.innerWidth > 767) return;
    if (document.querySelector('.np-mobile-controls')) return; // already injected
    const cont = document.createElement('div');
    cont.className = 'np-mobile-controls';
    // name
    const nameInput = document.createElement('input');
    nameInput.type='text'; nameInput.id='np-mobile-name'; nameInput.maxLength=12;
    nameInput.placeholder='YOUR NAME'; nameInput.className='mobile-input';
    // number
    const numInput = document.createElement('input');
    numInput.type='text'; numInput.id='np-mobile-num'; numInput.maxLength=3;
    numInput.placeholder='09'; numInput.className='mobile-input mobile-num';
    // max badges
    const maxRow = document.createElement('div'); maxRow.style.width='100%'; maxRow.style.display='flex'; maxRow.style.justifyContent='flex-end';
    const maxName = document.createElement('div'); maxName.className='max-count'; maxName.textContent='MAX. 12'; maxName.style.marginRight='8px';
    const maxNum  = document.createElement('div'); maxNum.className='max-count'; maxNum.textContent='MAX. 3';
    maxRow.appendChild(maxName); maxRow.appendChild(maxNum);

    cont.appendChild(nameInput); cont.appendChild(numInput); cont.appendChild(maxRow);
    stage.appendChild(cont);

    // sync initial values
    if (nameEl) nameInput.value = nameEl.value || '';
    if (numEl)  numInput.value = numEl.value || '';

    // handlers: keep desktop hidden inputs in sync and update preview
    nameInput.addEventListener('input', e=>{
      const v = e.target.value.toUpperCase().replace(/[^A-Z ]/g,'').slice(0,12);
      e.target.value = v;
      if (nameEl) nameEl.value = v;
      if (pvName) pvName.textContent = v || 'NAME';
      syncHidden(); checkEnableATC();
    });
    numInput.addEventListener('input', e=>{
      const v = e.target.value.replace(/\D/g,'').slice(0,3);
      e.target.value = v;
      if (numEl) numEl.value = v;
      if (pvNum) pvNum.textContent = v || '09';
      syncHidden(); checkEnableATC();
    });

    // maintain position when keyboard opens (visualViewport)
    if (window.visualViewport) {
      const setPos = ()=>{
        const sRect = stage.getBoundingClientRect();
        const topPx = Math.max(8, Math.round(sRect.height * 0.55));
        cont.style.top = topPx + 'px';
      };
      setPos();
      window.visualViewport.addEventListener('resize', ()=>{
        setPos();
        const vhRatio = window.visualViewport.height / window.innerHeight;
        if (vhRatio < 0.75) { stage.style.position='fixed'; stage.style.top='12px'; stage.style.left='50%'; stage.style.transform='translateX(-50%)'; }
        else { stage.style.position=''; stage.style.top=''; stage.style.left=''; stage.style.transform=''; }
      });
      window.addEventListener('resize', setPos);
      window.addEventListener('orientationchange', ()=> setTimeout(setPos,150));
    }

    // set focus to name
    setTimeout(()=> nameInput.focus(), 250);
  }

  // init mobile overlay on load & on orientation/resize
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mobileOverlaySetup);
  else mobileOverlaySetup();
  window.addEventListener('load', ()=> setTimeout(mobileOverlaySetup, 200));
  window.addEventListener('resize', ()=> setTimeout(mobileOverlaySetup, 200));
})();
</script>
</body>
</html>
