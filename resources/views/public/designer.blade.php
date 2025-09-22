<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->title ?? 'Product' }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">

  <style>
    /* copy the same styles you used in product.blade.php — trimmed here for brevity */
    /* paste the full CSS from product.blade.php in your real file */
    .np-hidden { display: none !important; }
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
    .np-stage { position: relative; width: 100%; max-width: 562px; margin: 0 auto; min-height: 220px; overflow: visible; background: #fff; }
    .np-stage img { width: 100%; height: auto; display:block; border-radius:6px; }
    .np-overlay { position:absolute; left:0; top:0; color:#D4AF37; text-shadow: 0 2px 6px rgba(0,0,0,0.35); white-space:nowrap; pointer-events:none; font-weight:700; text-transform:uppercase; letter-spacing:2px; display:flex; align-items:center; justify-content:center; user-select:none; line-height:1;}
    /* copy full CSS as needed */
  </style>
</head>
<body class="py-4">
@php
  // $product, $view, $areas should be passed by PublicDesignerController::show
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
  // Convert $areas to JS-ready structure: map slot type (name/number) if admins set template_id or slot_key
  // We'll pass an array of areas to JS — JS will find slots by name or template_id (your logic)
@endphp

<div class="container">
  <div class="row g-4">
    {{-- CENTER */}
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" src="{{ $img }}" alt="Preview"
               onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          {{-- overlays — placed & sized by JS --}}
          <div id="np-prev-name" class="np-overlay np-name font-bebas"></div>
          <div id="np-prev-num" class="np-overlay np-num font-bebas"></div>
        </div>
      </div>
    </div>

    {{-- LEFT controls (same as product.blade) --}}
    <div class="col-md-3 np-col order-2 order-md-1">
      <div class="border rounded p-3">
        <h6 class="mb-3">Customize</h6>
        <div id="np-status" class="small text-muted mb-2">Checking methods…</div>
        <div id="np-note" class="small text-muted mb-3 d-none">Personalization not available for this product.</div>

        <div id="np-controls" class="np-hidden">
          <div class="mb-3">
            <label for="np-num" class="form-label">Number (1–3 digits)</label>
            <input id="np-num" type="text" inputmode="numeric" maxlength="3" class="form-control" placeholder="e.g. 10" autocomplete="off">
            <div id="np-num-help" class="form-text">Digits only. 1–3 digits.</div>
            <div id="np-num-err"  class="text-danger small d-none">Enter 1–3 digits only.</div>
          </div>

          <div class="mb-3">
            <label for="np-name" class="form-label">Name (max 12)</label>
            <input id="np-name" type="text" maxlength="12" class="form-control" placeholder="e.g. SACHIN" autocomplete="off">
            <div id="np-name-help" class="form-text">Only A–Z and spaces. 1–12 chars.</div>
            <div id="np-name-err"  class="text-danger small d-none">Enter 1–12 letters/spaces only.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Select Font</label>
            <select id="np-font" class="form-select">
              <option value="bebas">Bebas Neue (Bold)</option>
              <option value="anton">Anton</option>
              <option value="oswald">Oswald</option>
              <option value="impact">Impact</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label d-block">Text Color</label>
            <div class="d-flex gap-2 flex-wrap">
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

    {{-- RIGHT product info (light) --}}
    <div class="col-md-3 np-col order-3 order-md-3">
      <h4 class="mb-1">{{ $product->title }}</h4>
      <div class="text-muted mb-3">Vendor: {{ $product->vendor ?? '—' }} • ₹ {{ number_format((float)($product->min_price ?? 0), 2) }}</div>

      <form id="np-atc-form" method="post" action="#">
        @csrf
        <input type="hidden" id="np-product-id" value="{{ $product->id }}">
        <input type="hidden" id="np-shopify-id" value="{{ $product->shopify_product_id ?? '' }}">
        <input type="hidden" id="np-method" value="ADD TEXT">

        <input type="hidden" name="name_text"     id="np-name-hidden">
        <input type="hidden" name="number_text"   id="np-num-hidden">
        <input type="hidden" name="selected_font" id="np-font-hidden">
        <input type="hidden" name="text_color"    id="np-color-hidden">

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled aria-busy="false">Add to Cart</button>
      </form>
      <div class="small text-muted mt-2">Button enables when both Name &amp; Number are valid.</div>
    </div>
  </div>
</div>

<script>
(function(){
  const $ = id => document.getElementById(id);
  const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const ctrls = $('np-controls'), note = $('np-note'), status = $('np-status'), btn = $('np-atc-btn');

  // Convert server-passed areas into JS object.
  // We'll render `window.layoutSlots` from server blade below.
  const layout = window.layoutSlots || {};

  // validators
  const NAME_RE = /^[A-Za-z ]{1,12}$/;
  const NUM_RE  = /^\d{1,3}$/;

  function validate(){
    const okName = NAME_RE.test((nameEl.value||'').trim());
    const okNum  = NUM_RE.test((numEl.value||'').trim());
    document.getElementById('np-name-err')?.classList.toggle('d-none', okName);
    document.getElementById('np-num-err')?.classList.toggle('d-none', okNum);
    if (!ctrls.classList.contains('np-hidden')) btn.disabled = !(okName && okNum);
    return okName && okNum;
  }

  function applyFont(val){
    pvName.classList.remove('font-bebas','font-anton','font-oswald','font-impact');
    pvNum.classList.remove('font-bebas','font-anton','font-oswald','font-impact');
    const map={bebas:'font-bebas',anton:'font-anton',oswald:'font-oswald',impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    pvName.classList.add(cls); pvNum.classList.add(cls);
  }

  function syncPreview(){
    pvName.textContent = (nameEl.value||'').toUpperCase();
    pvNum.textContent = (numEl.value||'').replace(/\D/g,'').toUpperCase();
  }

  function syncHidden(){
    $('np-name-hidden').value = (nameEl.value||'').toUpperCase().trim();
    $('np-num-hidden').value = (numEl.value||'').replace(/\D/g,'').trim();
    $('np-font-hidden').value = fontEl.value;
    $('np-color-hidden').value = colorEl.value;
  }

  // basic listeners
  nameEl?.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
  numEl?.addEventListener('input', e=>{ e.target.value=e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
  fontEl?.addEventListener('change', ()=>{ applyFont(fontEl.value); syncPreview(); syncHidden(); });
  colorEl?.addEventListener('input', ()=>{ pvName.style.color=colorEl.value; pvNum.style.color=colorEl.value; syncHidden(); });

  document.querySelectorAll('.np-swatch')?.forEach(b=>{
    b.addEventListener('click', ()=>{
      document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      colorEl.value = b.dataset.color;
      pvName.style.color = b.dataset.color; pvNum.style.color = b.dataset.color;
      syncHidden();
    });
  });

  applyFont(fontEl.value);
  pvName.style.color=colorEl.value; pvNum.style.color=colorEl.value;
  syncPreview(); syncHidden();

  // ---------- Placement using server-provided $areas ----------
  function placeOverlay(el, slot){
    if(!slot) return;
    const W = stage.clientWidth;
    const H = baseImg.clientHeight || baseImg.naturalHeight || (stage.clientWidth * 1.0);
    const left = (slot.left_pct/100) * W;
    const top  = (slot.top_pct/100) * H;
    const w    = (slot.width_pct/100) * W;
    const h    = (slot.height_pct/100) * H;

    el.style.position = 'absolute';
    el.style.left = left + 'px';
    el.style.top  = top + 'px';
    el.style.width = Math.max(10, w) + 'px';
    el.style.height = Math.max(10, h) + 'px';
    el.style.display = 'flex';
    el.style.alignItems = 'center';
    el.style.justifyContent = 'center';

    // set font size relative to area height
    let fontSize = Math.max(8, Math.floor(h * 0.6));
    el.style.fontSize = fontSize + 'px';
    el.style.transform = `rotate(${slot.rotation||0}deg)`;
    el.style.whiteSpace = 'nowrap';
    el.style.overflow = 'hidden';

    // shrink to fit
    while (el.scrollWidth > el.clientWidth && fontSize > 8) {
      fontSize -= 1;
      el.style.fontSize = fontSize + 'px';
    }
  }

  function applyLayout(){
    // layout slots object may look like { name: {...}, number: {...} } depending on admin data
    if (layout.name) placeOverlay(pvName, layout.name);
    if (layout.number) placeOverlay(pvNum, layout.number);
  }

  // Reapply when image loads and on resize
  baseImg.addEventListener('load', applyLayout);
  window.addEventListener('resize', applyLayout);
  // initial apply (if image already loaded)
  setTimeout(applyLayout, 50);

  // show controls if layout says personalization supported (server passes that flag)
  if (window.personalizationSupported) {
    status.textContent = 'Personalization supported.';
    note.classList.add('d-none');
    ctrls.classList.remove('np-hidden');
    btn.disabled = true;
  } else {
    status.textContent = 'Personalization not available.';
    note.classList.remove('d-none');
    ctrls.classList.add('np-hidden');
    btn.disabled = false;
  }

  // Form submit validation
  document.getElementById('np-atc-form').addEventListener('submit', function(e){
    if (!ctrls.classList.contains('np-hidden')) {
      if (!validate()) {
        e.preventDefault();
        alert('Please enter a valid Name (A–Z, 1–12) and Number (1–3 digits).');
        return;
      }
    }
    btn.setAttribute('aria-busy','true');
    syncHidden();
  });

})();
</script>

{{-- SERVER → CLIENT: layoutSlots + personalizationSupported values --}}
<script>
  // Build layout map from server $areas passed to blade
  window.layoutSlots = {};
  window.personalizationSupported = false;

  @if(!empty($areas) && count($areas))
    // mark supports true — we have areas
    window.personalizationSupported = true;
    @foreach($areas as $a)
      // You may have a slot_key or template id to know if this area is for 'name' or 'number'.
      // If admin used slot_key 'name' or 'number' use that. Otherwise fallback to template_id mapping.
      (function(){
        var slot = {
          id: {{ json_encode($a->id) }},
          template_id: {{ json_encode($a->template_id) }},
          left_pct: {{ floatval($a->left_pct) }},
          top_pct: {{ floatval($a->top_pct) }},
          width_pct: {{ floatval($a->width_pct) }},
          height_pct: {{ floatval($a->height_pct) }},
          rotation: {{ intval($a->rotation ?? 0) }},
          name: {!! json_encode($a->name ?? '') !!},
          slot_key: {!! json_encode($a->slot_key ?? '') !!}
        };

        // heuristics to map admin area → "name" or "number":
        var key = (slot.slot_key || '').toString().toLowerCase();
        if (key === 'name' || key === 'number') {
          window.layoutSlots[key] = slot;
          return;
        }

        // fallback: if name contains 'name' or 'number'
        if ((slot.name || '').toString().toLowerCase().indexOf('name') !== -1) {
          window.layoutSlots['name'] = slot; return;
        }
        if ((slot.name || '').toString().toLowerCase().indexOf('num') !== -1 ||
            (slot.name || '').toString().toLowerCase().indexOf('no') !== -1) {
          window.layoutSlots['number'] = slot; return;
        }

        // if no indicator: use template_id mapping you know. e.g. template_id 1 => name, 2 => number
        if (slot.template_id == 1 && !window.layoutSlots['name']) window.layoutSlots['name'] = slot;
        else if (slot.template_id == 2 && !window.layoutSlots['number']) window.layoutSlots['number'] = slot;
        else {
          // if still not mapped -> push into name if empty, else number
          if (!window.layoutSlots['name']) window.layoutSlots['name'] = slot;
          else if (!window.layoutSlots['number']) window.layoutSlots['number'] = slot;
        }
      })();
    @endforeach
  @endif
</script>

</body>
</html>
