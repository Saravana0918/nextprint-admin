<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* small styles (same as you had) */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; overflow: visible; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position: absolute; color: #D4AF37; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; text-align:center; text-shadow:0 3px 10px rgba(0,0,0,0.65); pointer-events:none; white-space:nowrap; line-height:1; transform-origin:center center; z-index:9999; }
    .np-swatch{ width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block;}
    .np-swatch.active{ outline:2px solid rgba(0,0,0,0.08); box-shadow:0 2px 6px rgba(0,0,0,0.06); }
    body{ background-color:#929292; } .body-padding{ padding-top:100px; } .right-layout{ padding-top:350px; }
    input:focus, select:focus { outline: 3px solid rgba(13,110,253,0.12); }
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
          <img id="np-base" crossorigin="anonymous" src="{{ $img }}" alt="Preview" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <!-- controls -->
    <div class="col-md-3 np-col order-2 order-md-1" id="np-controls">
      <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
      <input id="np-num"  type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
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
    <div class="col-md-3 np-col order-3 order-md-3 right-layout mobile-layout">
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
        <input type="hidden" name="variant_id" id="np-variant-id" value="">
        <div class="mb-2">
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            <option value="XS">XS</option><option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option><option value="2XL">2XL</option><option value="3XL">3XL</option>
          </select>
        </div>
        <div class="mb-2">
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary">Add to Cart</button>
        <a href="#" class="btn btn-success" id="btn-add-team" style="margin-left:8px;">Add Team Players</a>
      </form>
    </div>
  </div>
</div>

<!-- server-side layout slots -->
<script> window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!}; </script>

<!-- server variant map injection (controller must pass $variantMap) -->
<script>
  window.variantMap = {!! json_encode($variantMap ?? [], JSON_UNESCAPED_SLASHES) !!};
  console.log('Injected variantMap:', window.variantMap);
</script>

<!-- force storefront origin: IMPORTANT - change if your storefront domain differs -->
<script>
  window.STOREFRONT_ORIGIN = "{{ url('/') }}"; // example: https://nextprint.in
  console.log('STOREFRONT_ORIGIN', window.STOREFRONT_ORIGIN);
</script>

<!-- helper functions -->
<script>
function toGidIfNeeded(v){
  if(!v) return '';
  v = v.toString().trim();
  if(v.startsWith('gid://')) return v;
  const numeric = v.replace(/[^\d]/g,'');
  if(!numeric) return '';
  return 'gid://shopify/ProductVariant/' + numeric;
}

function ensureVariantNumeric(){
  // returns numeric id string or ''
  const size = (document.getElementById('np-size')?.value || '').toString().trim();
  let mapped = '';

  if (window.variantMap && size) {
    if (window.variantMap[size]) mapped = window.variantMap[size];
    else {
      // try case-insensitive match
      for (const k of Object.keys(window.variantMap || {})) {
        if (k.toString().toLowerCase() === size.toLowerCase()) { mapped = window.variantMap[k]; break; }
      }
    }
  }

  // normalize mapped -> numeric
  if (mapped && mapped.toString().startsWith('gid://')) mapped = mapped.toString().split('/').pop();
  else if (mapped) mapped = mapped.toString().replace(/[^\d]/g,'');

  // fallback: hidden field
  const hidden = document.getElementById('np-variant-id');
  let fallback = hidden ? hidden.value : '';
  if (fallback && fallback.toString().startsWith('gid://')) fallback = fallback.split('/').pop();
  else fallback = (fallback||'').toString().replace(/[^\d]/g,'');

  const numeric = (mapped || fallback || '').toString();
  if (hidden) hidden.value = numeric ? ('gid://shopify/ProductVariant/' + numeric) : '';
  console.log('ensureVariantNumeric ->', { size, mapped, fallback, numeric });
  return numeric;
}
</script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  const nameEl  = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName  = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const btn = $('np-atc-btn'), form = $('np-atc-form'), addTeam = $('btn-add-team');

  function applyFont(val){
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    [pvName, pvNum].forEach(el => { if(el) el.className = 'np-overlay ' + cls; });
  }

  function applyLayout(){
    if (!baseImg || !baseImg.complete) return;
    // place default positions (simple)
    pvName.style.left='50%'; pvName.style.top='45%'; pvName.style.transform='translate(-50%,-50%)';
    pvNum.style.left='50%'; pvNum.style.top='65%'; pvNum.style.transform='translate(-50%,-50%)';
  }

  function syncPreview(){
    if (pvName && nameEl) pvName.textContent = (nameEl.value || 'NAME').toUpperCase();
    if (pvNum && numEl) pvNum.textContent = (numEl.value || '09').replace(/\D/g,'');
    applyLayout();
  }

  function syncHidden(){
    const n = $('np-name-hidden'), nm = $('np-num-hidden'), f = $('np-font-hidden'), c = $('np-color-hidden');
    if (n) n.value = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
    if (nm) nm.value = (numEl ? (numEl.value||'') : '').replace(/\D/g,'').trim();
    if (f) f.value = fontEl ? fontEl.value : '';
    if (c) c.value = colorEl ? colorEl.value : '';
    ensureVariantNumeric();
  }

  // events
  if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); syncHidden(); });
  if (numEl) numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); syncHidden(); });
  if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncHidden(); syncPreview(); });
  if (colorEl) colorEl.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });
  const sizeEl = $('np-size'); sizeEl?.addEventListener('change', ()=> { ensureVariantNumeric(); });

  document.querySelectorAll('.np-swatch').forEach(b=>{
    b.addEventListener('click', ()=>{
      document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      if (colorEl) colorEl.value = b.dataset.color;
      if (pvName) pvName.style.color = b.dataset.color;
      if (pvNum) pvNum.style.color = b.dataset.color;
      syncHidden();
    });
  });

  // init
  applyFont(fontEl?.value || 'bebas');
  if (pvName && colorEl) pvName.style.color = colorEl.value;
  if (pvNum && colorEl) pvNum.style.color = colorEl.value;
  syncPreview();
  syncHidden();

  // team button -> redirect to team.create route with query params
  if (addTeam) addTeam.addEventListener('click', function(e){ e.preventDefault();
    const params = new URLSearchParams();
    if ($('np-product-id')?.value) params.set('product_id', $('np-product-id').value);
    if (nameEl?.value) params.set('prefill_name', nameEl.value);
    if (numEl?.value) params.set('prefill_number', numEl.value);
    if (fontEl?.value) params.set('prefill_font', fontEl.value);
    if (colorEl?.value) params.set('prefill_color', colorEl.value);
    if ($('np-size')?.value) params.set('prefill_size', $('np-size').value);
    const base = "{{ route('team.create') }}";
    window.location.href = base + '?' + params.toString();
  });

  /* ---------- add-to-cart submit ---------- */
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();
    if (btn) { btn.disabled = true; btn.textContent = 'Preparing…'; }

    const size = document.getElementById('np-size')?.value || '';
    const nameVal = (document.getElementById('np-name')?.value||'').trim();
    const numVal  = (document.getElementById('np-num')?.value||'').trim();

    if (!size) { alert('Please select a size.'); if(btn){btn.disabled=false;btn.textContent='Add to Cart';} return; }
    if (!/^[A-Za-z ]{1,12}$/.test(nameVal) || !/^\d{1,3}$/.test(numVal)) { alert('Please enter valid Name and Number'); if(btn){btn.disabled=false;btn.textContent='Add to Cart';} return; }

    // ensure numeric variant id
    const numericVariant = ensureVariantNumeric();
    if (!numericVariant || !/^\d+$/.test(numericVariant)) {
      alert('Variant id missing or invalid. Please reselect size.');
      if(btn){btn.disabled=false;btn.textContent='Add to Cart';}
      return;
    }

    // optional: capture preview (try but continue if fails)
    let previewData = '';
    try {
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
      previewData = canvas.toDataURL('image/png');
      if (previewData.length > 120000) previewData = '[preview-too-large]';
      document.getElementById('np-preview-hidden').value = previewData;
    } catch (err) {
      console.warn('html2canvas failed', err);
    }

    // build properties
    const properties = {
      "Name": nameVal.toUpperCase(),
      "Number": numVal,
      "Font": (document.getElementById('np-font')?.value||''),
      "Color": (document.getElementById('np-color')?.value||'')
    };
    if (previewData) properties["Preview"] = previewData;

    // prepare body
    const params = new URLSearchParams();
    params.append('id', numericVariant);
    params.append('quantity', (document.getElementById('np-qty')?.value || '1'));
    for (const k in properties) {
      if (!Object.prototype.hasOwnProperty.call(properties,k)) continue;
      params.append(`properties[${k}]`, properties[k]);
    }

    const origin = (window.STOREFRONT_ORIGIN && window.STOREFRONT_ORIGIN.startsWith('http')) ? window.STOREFRONT_ORIGIN.replace(/\/+$/,'') : '';
    const addUrl = origin ? (origin + '/cart/add.js') : '/cart/add.js';

    try {
      const resp = await fetch(addUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: params.toString()
      });

      const json = await resp.json().catch(()=>null);
      console.log('add-to-cart response', resp.status, json);

      if (!resp.ok) {
        alert((json && (json.description || json.message)) || 'Add to cart failed — see console');
        if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
        return;
      }

      // on success, go to checkout (change to '/cart' if you want cart page instead)
      const checkoutUrl = origin ? (origin + '/checkout') : '/checkout';
      window.location.href = checkoutUrl;

    } catch (err) {
      console.error('Add-to-cart exception', err);
      alert('Something went wrong — see console');
      if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
    }
  });

})();
</script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</body>
</html>
