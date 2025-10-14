{{-- resources/views/designer.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* --- keep your existing styles --- */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; border-radius:8px; padding:8px; box-sizing: border-box; overflow: visible; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position: absolute; color: #D4AF37; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; text-align: center; text-shadow: 0 3px 10px rgba(0,0,0,0.65); pointer-events: none; white-space: nowrap; line-height: 1; transform-origin: center center; z-index: 9999; }
    .np-user-image { position: absolute; pointer-events: auto; object-fit: cover; display: block; transform-origin: center center; z-index: 300; box-shadow: 0 6px 18px rgba(0,0,0,0.25); border-radius: 4px; }
    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    body { background-color: #929292; }
    /* minimal responsive rules kept (copy your full styles if needed) */
  </style>
</head>
<body class="body-padding">

@php
  // preview image (product)
  $img = $product->preview_src ?? ($product->image_url ?? asset('images/placeholder.png'));
  // normalized slots for JS (you may already provide)
  $slotsForJs = $slotsForJs ?? [];
  $originalSlotsForJs = $originalSlotsForJs ?? [];
@endphp

<div class="container">
  <div class="row g-4">
    {{-- Preview stage --}}
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" crossorigin="anonymous" src="{{ $img }}" alt="Preview" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    {{-- Controls (left) --}}
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
      @if(!empty($showUpload))
      <div class="mb-2" id="np-upload-block" style="margin-top:6px;">
        <input id="np-upload-image" type="file" accept="image/*" class="form-control" />
        <div style="margin-top:6px;">
          <button id="np-user-image-reset" type="button" class="btn btn-sm btn-outline-light" style="display:none;margin-right:6px;">Remove Image</button>
          <label for="np-user-image-scale" style="display:none;" id="np-user-image-scale-label">Scale</label>
          <input id="np-user-image-scale" type="range" min="50" max="200" value="100" style="vertical-align: middle; display:none;" />
        </div>
      </div>
      @endif
    </div>

    {{-- Right layout: product + form --}}
    <div class="col-md-3 np-col order-3 order-md-3 right-layout">
      <h4 class="desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        {{-- hidden values sent to server/cart --}}
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" id="np-uploaded-logo-url" name="uploaded_logo_url" value="">
        <input type="hidden" name="preview_data" id="np-preview-hidden">
        <input type="hidden" name="product_id" id="np-product-id" value="{{ $product->id ?? $product->local_id ?? '' }}">
        <input type="hidden" name="shopify_product_id" id="np-shopify-product-id" value="{{ $product->shopify_product_id ?? $product->shopify_id ?? '' }}">
        <input type="hidden" name="variant_id" id="np-variant-id" value="">
        <input type="hidden" name="design_order_id" id="np-design-order-id" value="">

        {{-- size options --}}
        @php
          $sizeOptions = [];
          if (!empty($product) && $product->relationLoaded('variants') && $product->variants->count()) {
              $sizeOptions = $product->variants->pluck('option_value')->map(fn($x)=>trim((string)$x))->unique()->values()->all();
          }
        @endphp

        <div class="mb-2">
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            @foreach($sizeOptions as $opt)
              <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-2">
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        {{-- Save customization button (user asked for) --}}
        <div class="d-flex gap-2">
          <button id="np-save-btn" type="button" class="btn btn-warning">Save Customization</button>

          {{-- Add to cart hidden until saved (style display none initially) --}}
          <button id="np-atc-btn" type="submit" class="btn btn-primary" style="display:none;">Add to Cart</button>

          <a href="#" class="btn btn-success" id="btn-add-team" style="margin-left:8px;">Add Team Players</a>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- export slots & flags to window for JS usage --}}
<script>
  window.layoutSlots = {!! json_encode($slotsForJs ?? [], JSON_NUMERIC_CHECK) !!};
  window.originalLayoutSlots = {!! json_encode($originalSlotsForJs ?? [], JSON_NUMERIC_CHECK) !!};
  window.showUpload = {{ !empty($showUpload) ? 'true' : 'false' }};
  window.hasArtworkSlot = {{ !empty($hasArtworkSlot) ? 'true' : 'false' }};
  window.variantMap = {!! json_encode($variantMap ?? [], JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!} || {};
  window.shopfrontUrl = "{{ env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in') }}";
</script>

{{-- html2canvas used for preview capture --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
/*
  Main JS for:
   - live preview overlay
   - Save customization (POST /design-order)
   - enabling Add to Cart after save
   - existing Add to Cart posting to /cart/add (Shopify)
*/

(function(){
  const $ = id => document.getElementById(id);

  // inputs / elements
  const nameInputs = Array.from(document.querySelectorAll('#np-name'));
  const numInputs  = Array.from(document.querySelectorAll('#np-num'));
  const fontEl = $('np-font');
  const colorEl = $('np-color');
  const pvName = $('np-prev-name'), pvNum = $('np-prev-num');
  const baseImg = $('np-base'), stage = $('np-stage');

  const saveBtn = $('np-save-btn'), atcBtn = $('np-atc-btn');
  const designOrderIdInput = $('np-design-order-id');
  const nameHidden = $('np-name-hidden'), numHidden = $('np-num-hidden'), fontHidden = $('np-font-hidden'), colorHidden = $('np-color-hidden');
  const previewHidden = $('np-preview-hidden');
  const uploadedLogoUrl = $('np-uploaded-logo-url');
  const sizeSelect = $('np-size'), variantInput = $('np-variant-id'), qtyInput = $('np-qty'), productIdInput = $('np-product-id'), shopifyProductInput = $('np-shopify-product-id');

  const NAME_RE = /^[A-Za-z ]{1,12}$/;
  const NUM_RE = /^\d{1,3}$/;

  // small helpers
  function syncPreviewText(){
    const nameVal = (nameInputs[0]?.value || '').toUpperCase() || 'NAME';
    const numVal = (numInputs[0]?.value || '').replace(/\D/g,'') || '09';
    if (pvName) pvName.textContent = nameVal;
    if (pvNum) pvNum.textContent = numVal;
  }

  function syncHidden(){
    if (nameHidden) nameHidden.value = (nameInputs[0]?.value || '').toUpperCase().trim();
    if (numHidden) numHidden.value = (numInputs[0]?.value || '').replace(/\D/g,'').trim();
    if (fontHidden) fontHidden.value = fontEl?.value || '';
    if (colorHidden) colorHidden.value = colorEl?.value || '';
    // set variant id from variantMap (size->variant)
    const sizeVal = sizeSelect?.value || '';
    if (sizeVal && window.variantMap) {
      const v = window.variantMap[sizeVal] || window.variantMap[sizeVal.toUpperCase()] || window.variantMap[sizeVal.toLowerCase()] || '';
      if (variantInput) variantInput.value = v;
    } else {
      if (variantInput) variantInput.value = '';
    }
  }

  // wire inputs (name/number) - keep desktop & mobile in sync (if duplicates exist)
  nameInputs.forEach(i => i.addEventListener('input', (e) => {
    nameInputs.forEach(x => { if (x !== i) x.value = i.value; });
    syncPreviewText(); syncHidden();
  }));

  numInputs.forEach(i => i.addEventListener('input', (e) => {
    e.target.value = (e.target.value || '').replace(/\D/g,'').slice(0,3);
    numInputs.forEach(x => { if (x !== i) x.value = e.target.value; });
    syncPreviewText(); syncHidden();
  }));

  fontEl?.addEventListener('change', ()=>{ syncHidden(); /* you can add font mapping to preview classes here */ });
  colorEl?.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

  // size -> variant map
  sizeSelect?.addEventListener('change', ()=>{ syncHidden(); });

  // initially hide Add to Cart until saved (user requested)
  (function initState(){
    if (atcBtn) atcBtn.style.display = 'none';
    // if editing an existing saved design and design_order_id is prefilled, show atc
    if (designOrderIdInput && designOrderIdInput.value) {
      if (atcBtn) atcBtn.style.display = '';
      saveBtn.classList.remove('btn-warning'); saveBtn.classList.add('btn-success'); saveBtn.textContent = 'Saved ✓'; saveBtn.disabled = true;
    }
    syncPreviewText(); syncHidden();
  })();

  // capture preview as dataURL using html2canvas
  async function capturePreviewData(){
    if (!stage) return '';
    try {
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
      return canvas.toDataURL('image/png');
    } catch(err) {
      console.warn('preview capture failed', err);
      return previewHidden?.value || '';
    }
  }

  // Save customization: POST to /design-order (expects JSON { success:true, order_id: id })
  async function saveCustomization(){
    syncHidden();
    // validate basics
    const name = nameHidden?.value || '';
    const number = numHidden?.value || '';
    const variant = variantInput?.value || '';
    if (!NAME_RE.test(name)) { alert('Please enter a valid name (letters only, max length).'); return null; }
    if (!NUM_RE.test(number)) { alert('Please enter a valid numeric number.'); return null; }
    if (!variant || !/^\d+$/.test(variant)) { alert('Please select a size (variant).'); return null; }

    saveBtn.disabled = true;
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';

    // capture preview
    const previewData = await capturePreviewData();
    if (previewHidden) previewHidden.value = previewData;

    // assemble payload
    const payload = {
      product_id: productIdInput?.value || null,
      shopify_product_id: shopifyProductInput?.value || null,
      variant_id: variant,
      name: name,
      number: number,
      font: fontHidden?.value || '',
      color: colorHidden?.value || '',
      size: sizeSelect?.value || '',
      quantity: parseInt(qtyInput?.value || '1', 10),
      preview_src: previewData || '',
      uploaded_logo_url: uploadedLogoUrl?.value || window.lastUploadedLogoUrl || null,
      players: window.currentPlayers || null,
      shopify_order_id: null
    };

    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch('/design-order', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(payload)
      });

      const json = await res.json().catch(()=>null);
      if (!res.ok || !json || !json.success) {
        console.warn('save failed', json);
        alert('Customization save failed. Please try again or contact admin.');
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
        return null;
      }

      // success
      const orderId = json.order_id || null;
      if (designOrderIdInput) designOrderIdInput.value = orderId;
      // show Add to Cart button now
      if (atcBtn) atcBtn.style.display = '';
      // update save button visual
      saveBtn.classList.remove('btn-warning'); saveBtn.classList.add('btn-success');
      saveBtn.textContent = 'Saved ✓';
      saveBtn.disabled = true;

      alert('Customization saved. You can now Add to Cart.');
      return orderId;
    } catch(err) {
      console.error('save request error', err);
      alert('Error saving customization. Check console or contact admin.');
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
      return null;
    }
  }

  saveBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    saveCustomization();
  });

  // modify Add to Cart submit so it includes DesignOrderId property
  const form = $('np-atc-form');
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();
    // ensure saved (has design_order_id)
    if (!designOrderIdInput?.value) {
      const ok = confirm('You have not saved the customization yet. Save now?');
      if (!ok) return;
      const saved = await saveCustomization();
      if (!saved) return; // stop if save failed
    }
    // now proceed to build cart add request (Shopify /cart/add)
    try {
      syncHidden();
      // capture preview again (optional)
      try {
        const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
        previewHidden.value = canvas.toDataURL('image/png');
      } catch(e){ /* ignore */ }

      const props = {
        'Name': nameHidden?.value || '',
        'Number': numHidden?.value || '',
        'Font': fontHidden?.value || '',
        'Color': colorHidden?.value || ''
      };
      if (designOrderIdInput?.value) props['DesignOrderId'] = designOrderIdInput.value;

      const variantId = variantInput?.value || '';
      const qty = Math.max(1, parseInt(qtyInput?.value || '1', 10));
      const bodyArr = [];
      bodyArr.push('id=' + encodeURIComponent(variantId));
      bodyArr.push('quantity=' + encodeURIComponent(qty));
      for (const k in props) bodyArr.push('properties[' + encodeURIComponent(k) + ']=' + encodeURIComponent(props[k]));
      const body = bodyArr.join('&');

      // post to Shopify cart/add
      const resp = await fetch('/cart/add', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, credentials: 'same-origin' });
      const shopfront = (window.shopfrontUrl || '').replace(/\/+$/,'');
      window.location.href = shopfront + '/cart/' + variantId + ':' + qty;
    } catch(err) {
      console.error('Add to cart error', err);
      alert('Something went wrong adding to cart.');
    }
  });

  // show preview sync on load and basic layout functions (you already had detailed placement code; keep it if needed)
  document.fonts?.ready.then(()=> { setTimeout(()=>{ if (pvName) pvName.style.color = colorEl?.value || '#D4AF37'; syncPreviewText(); syncHidden(); },120); });
})();
</script>

</body>
</html>
