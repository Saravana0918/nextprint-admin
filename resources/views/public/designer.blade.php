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
    /* fonts */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    /* stage */
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; overflow: visible; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }

    /* overlays */
    .np-overlay {
      position: absolute;
      color: #D4AF37;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      text-align: center;
      text-shadow: 0 3px 10px rgba(0,0,0,0.65);
      pointer-events: none;
      white-space: nowrap;
      line-height: 1;
      transform-origin: center center;
      z-index: 9999;
    }

    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

    body { background-color: #929292; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:350px; }

    input:focus, select:focus { outline: 3px solid rgba(13,110,253,0.12); }
    .desktop-display{ color : white ;}
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
            <option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option><option value="2XL">2XL</option>
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

<script> window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!}; </script>

<script>
  // storefront base URL for cart adds — adjust if your storefront is different
  window.STOREFRONT_ORIGIN = "{{ url('/') }}"; // example: https://nextprint.in
</script>

<!-- Robust helpers -->
<script>
function toGidIfNeeded(v){
  if(!v) return '';
  v = v.toString().trim();
  if(v.startsWith('gid://')) return v;
  const numeric = v.replace(/[^\d]/g,'');
  if(!numeric) return '';
  return 'gid://shopify/ProductVariant/' + numeric;
}

function ensureVariantGid(){
  const size = (document.getElementById('np-size')?.value || '').toString().trim();
  let mapped = '';

  // try window.variantMap first (case-insensitive)
  if (window.variantMap && size) {
    if (window.variantMap[size]) mapped = window.variantMap[size];
    else {
      // try different casings
      const keys = Object.keys(window.variantMap || {});
      for (const k of keys) {
        if (k.toString().toLowerCase() === size.toLowerCase()) { mapped = window.variantMap[k]; break; }
      }
    }
  }

  // normalize mapped -> numeric
  if (mapped && mapped.toString().startsWith('gid://')) {
    mapped = mapped.toString().split('/').pop();
  } else if (mapped) {
    mapped = mapped.toString().replace(/[^\d]/g,'');
  }

  // also allow hidden field to contain gid or numeric fallback
  const hidden = document.getElementById('np-variant-id');
  let fallback = hidden ? hidden.value : '';
  if (fallback && fallback.toString().startsWith('gid://')) fallback = fallback.split('/').pop();
  else fallback = (fallback || '').toString().replace(/[^\d]/g,'');

  const numeric = (mapped || fallback || '').toString();
  const gid = numeric ? 'gid://shopify/ProductVariant/' + numeric : '';
  if (hidden) hidden.value = gid;
  // return numeric (string) — easiest for cart post
  return numeric;
}
</script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  const nameEl  = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName  = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const btn = $('np-atc-btn'), form = $('np-atc-form'), addTeam = $('btn-add-team');
  const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};

  const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

  function applyFont(val){
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    [pvName, pvNum].forEach(el => { if(el) el.className = 'np-overlay ' + cls; });
  }

  function computeStageSize(){
    if (!baseImg || !stage) return null;
    const stageRect = stage.getBoundingClientRect();
    const imgRect = baseImg.getBoundingClientRect();
    return {
      offsetLeft: Math.round(imgRect.left - stageRect.left),
      offsetTop: Math.round(imgRect.top - stageRect.top),
      imgW: Math.max(1,imgRect.width), imgH: Math.max(1,imgRect.height),
      stageW: Math.max(1, stageRect.width), stageH: Math.max(1, stageRect.height)
    };
  }

  function placeOverlay(el, slot, slotKey){
    if(!el || !slot) return;
    const s = computeStageSize();
    if(!s) return;
    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||0)/100) * s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const centerY = Math.round(s.offsetTop  + ((slot.top_pct||0)/100)  * s.imgH + ((slot.height_pct||0)/200)*s.imgH);
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
    el.style.padding = '0 6px';
    el.style.whiteSpace = 'nowrap';
    el.style.overflow = 'hidden';
    el.style.pointerEvents = 'none';
    el.style.zIndex = (slotKey === 'number' ? 60 : 50);

    const text = (el.textContent || '').toString().trim() || (slotKey === 'number' ? '09' : 'NAME');
    const chars = Math.max(1, text.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile?1.05:1) : 1));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let numericShrink = (slotKey === 'number') ? (isMobile ? 1.0 : 0.98) : 1.0;
    let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * numericShrink);
    const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
    fontSize = Math.max(8, Math.min(fontSize, maxAllowed));
    fontSize = Math.floor(fontSize * 1.10);
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
    if (!baseImg || !baseImg.complete) return;
    if (layout && layout.name) placeOverlay(pvName, layout.name, 'name'); else { pvName.style.left='50%'; pvName.style.top='45%'; pvName.style.transform='translate(-50%,-50%)'; }
    if (layout && layout.number) placeOverlay(pvNum, layout.number, 'number'); else { pvNum.style.left='50%'; pvNum.style.top='65%'; pvNum.style.transform='translate(-50%,-50%)'; }
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
    ensureVariantGid();
  }

  // events
  if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); syncHidden(); });
  if (numEl) numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); syncHidden(); });
  if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncHidden(); syncPreview(); });
  if (colorEl) colorEl.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });
  const sizeEl = $('np-size');
  sizeEl?.addEventListener('change', ()=> { ensureVariantGid(); });

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
  applyLayout();
  baseImg.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 80));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  /* ============================
     Upload preview to server helper
     ============================ */
  async function uploadPreviewToServer(dataUrl) {
    try {
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const fd = new FormData();
      fd.append('preview', dataUrl);
      const resp = await fetch('{{ route('designer.upload_preview') }}', {
        method: 'POST',
        body: fd,
        headers: { 'X-CSRF-TOKEN': token },
        credentials: 'same-origin'
      });
      const json = await resp.json();
      if (!resp.ok) throw new Error(json.message || 'Upload failed');
      return json.url || null;
    } catch (err) {
      console.warn('uploadPreviewToServer failed', err);
      return null;
    }
  }

  /* ============================
     Submit to Shopify storefront /cart/add
     ============================ */
  function submitToShopifyStorefront({variantNumericId, quantity, props}) {
    const storeDomain = location.hostname; // will submit to current host domain (works for same-origin store)
    const form2 = document.createElement('form');
    form2.method = 'POST';
    form2.action = `https://${storeDomain}/cart/add`;
    form2.style.display = 'none';

    const addInput = (nameAttr, val) => {
      const i = document.createElement('input');
      i.type = 'hidden';
      i.name = nameAttr;
      i.value = val ?? '';
      form2.appendChild(i);
    };

    addInput('id', variantNumericId);
    addInput('quantity', quantity);

    if (props && typeof props === 'object') {
      for (const k in props) {
        if (!Object.prototype.hasOwnProperty.call(props, k)) continue;
        let v = String(props[k] ?? '');
        if (v.length > 60000) v = v.slice(0, 60000);
        addInput(`properties[${k}]`, v);
      }
    }

    document.body.appendChild(form2);
    form2.submit();
  }

  // handle form submit: create preview -> upload -> submit to cart
  form?.addEventListener('submit', async function(evt){
  evt.preventDefault();

  const size = document.getElementById('np-size')?.value || '';
  if (!size) { alert('Please select a size.'); return; }

  const nameVal = (document.getElementById('np-name')?.value||'').trim();
  const numVal  = (document.getElementById('np-num')?.value||'').trim();
  if (!/^[A-Za-z ]{1,12}$/.test(nameVal) || !/^\d{1,3}$/.test(numVal)) {
    alert('Please enter valid Name and Number'); return;
  }

  if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }

  // ensure variant numeric id
  const numericVariant = ensureVariantGid(); // returns numeric id string (no gid prefix)
  if (!numericVariant || !/^\d+$/.test(numericVariant)) {
    if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
    alert('Variant id missing or invalid. Please reselect size.');
    return;
  }

  // sync hidden preview value (optional)
  syncHidden();

  // capture preview (optional)
  let previewData = '';
  try {
    const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
    previewData = canvas.toDataURL('image/png');
    if (previewData.length > 70000) previewData = '[preview-too-large]';
    document.getElementById('np-preview-hidden').value = previewData;
  } catch (err) {
    console.warn('html2canvas error', err);
  }

  // build properties
  const properties = {
    "Name": nameVal.toUpperCase(),
    "Number": numVal,
    "Font": (document.getElementById('np-font')?.value||''),
    "Color": (document.getElementById('np-color')?.value||'')
  };
  if (previewData) properties["Preview"] = previewData;

  // create body
  const params = new URLSearchParams();
  params.append('id', numericVariant);
  params.append('quantity', (document.getElementById('np-qty')?.value || '1'));

  for (const k in properties) {
    if (!Object.prototype.hasOwnProperty.call(properties,k)) continue;
    params.append(`properties[${k}]`, properties[k]);
  }

  // decide storefront endpoint: use injected var or fallback
  const origin = (window.STOREFRONT_ORIGIN && window.STOREFRONT_ORIGIN.startsWith('http')) ? window.STOREFRONT_ORIGIN.replace(/\/+$/,'') : '';
  const addUrl = origin ? (origin + '/cart/add.js') : '/cart/add.js';

  try {
    const resp = await fetch(addUrl, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString()
    });

    if (!resp.ok) {
      const txt = await resp.text().catch(()=>null);
      console.error('Add to cart failed', resp.status, txt);
      alert('Unable to add to cart. Check console / network.');
      if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
      return;
    }

    // success -> redirect to checkout
    // if you want cart page instead use '/cart'
    const checkoutUrl = (origin ? (origin + '/checkout') : '/checkout');
    window.location.href = checkoutUrl;

  } catch (err) {
    console.error('Add-to-cart exception', err);
    alert('Something went wrong, see console.');
    if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
  }
});

})();
</script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</body>
</html>
