{{-- resources/views/public/designer.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->title ?? 'Designer' }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">

  <style>
  /* --- same styling as your product.blade version (trimmed) --- */
  .np-hidden { display: none !important; }
  .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
  .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
  .font-oswald{font-family:'Oswald', Arial, sans-serif;}
  .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
  .border.rounded.p-3 { background:#fff;border:1px solid #e6e9ee;border-radius:8px;padding:1rem!important;box-shadow:0 6px 18px rgba(15,23,42,0.04); }
  h6.mb-3{font-weight:700;color:#111827;font-size:2rem;margin-bottom:.75rem!important;}
  #np-status,#np-note{font-size:.85rem;color:#6b7280}
  .form-label{font-weight:600;color:#374151;font-size:1.3rem}
  .form-text{color:#6b7280;font-size:1rem;margin-top:.25rem}
  .np-swatch{width:28px;height:28px;border-radius:6px;border:1px solid rgba(0,0,0,0.06);cursor:pointer;display:inline-block;box-shadow:0 4px 10px rgba(16,24,40,0.03)}
  .np-swatch.active{box-shadow:0 0 0 4px rgba(59,130,246,0.14);border-color:rgba(59,130,246,0.9)}
  .np-stage{position:relative;width:100%;max-width:562px;margin:0 auto;min-height:220px;overflow:visible;background:#fff}
  .np-stage img{width:100%;height:auto;display:block;border-radius:6px}
  .np-overlay{position:absolute;left:50%;transform:translateX(-50%);color:#D4AF37;text-shadow:0 2px 6px rgba(0,0,0,0.35);pointer-events:none;font-weight:700;text-transform:uppercase;letter-spacing:2px;display:flex;align-items:center;justify-content:center;user-select:none;line-height:1}
  .np-name{font-size:26px;top:18%}
  .np-num{font-size:64px;top:42%}
  @media (max-width:767.98px){
    .col-md-3.np-col{display:none!important}
    .col-md-6.np-col{order:1!important;flex:0 0 100%!important;max-width:100%!important}
    .np-mobile-inputs input{background:transparent;border:none;border-bottom:2px solid rgba(255,255,255,0.95);text-align:center;color:#fff;font-size:20px}
  }
  </style>
</head>
<body class="py-4">
@php
  // Build default image and slots array from $product, $view, $areas
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));

  // Prepare slots: map by slot_key if available, otherwise use first two as name/number
  $slots = [];
  if (!empty($areas) && is_iterable($areas)) {
    foreach ($areas as $a) {
      // area could be array or model - normalize
      $left  = floatval($a->left_pct ?? ($a['left_pct'] ?? 0));
      $top   = floatval($a->top_pct ?? ($a['top_pct'] ?? 0));
      $w     = floatval($a->width_pct ?? ($a['width_pct'] ?? 0));
      $h     = floatval($a->height_pct ?? ($a['height_pct'] ?? 0));
      $rot   = floatval($a->rotation ?? ($a['rotation'] ?? 0));
      $slotKey = $a->slot_key ?? ($a['slot_key'] ?? null);
      $name = $slotKey ?: null;

      $data = [
        'left_pct' => $left,
        'top_pct'  => $top,
        'width_pct'=> $w,
        'height_pct'=> $h,
        'rotation' => $rot
      ];

      if ($name) {
        $slots[$name] = $data;
      } else {
        // push to numeric keys for fallback mapping
        $slots[] = $data;
      }
    }
  }

  // If no named slots, map first two -> name/number
  if (empty($slots)) {
    $slots = [
      'name' => ['left_pct'=>30,'top_pct'=>18,'width_pct'=>40,'height_pct'=>10,'rotation'=>0],
      'number' => ['left_pct'=>30,'top_pct'=>42,'width_pct'=>40,'height_pct'=>20,'rotation'=>0]
    ];
  } else {
    // ensure 'name' and 'number' exist
    if (!isset($slots['name'])) {
      // pick first numeric
      $first = null;
      foreach ($slots as $k=>$v) { if (is_int($k) || is_string($k) && preg_match('/^\d+$/',$k)) { $first = $v; break; } }
      if ($first) $slots['name'] = $first;
      elseif (!isset($slots['name'])) $slots['name'] = ['left_pct'=>30,'top_pct'=>18,'width_pct'=>40,'height_pct'=>10,'rotation'=>0];
    }
    if (!isset($slots['number'])) {
      $second = null; $i = 0;
      foreach ($slots as $k=>$v) { if ($k==='name') continue; $i++; if ($i===1) { $second=$v; break; } }
      if ($second) $slots['number'] = $second;
      else $slots['number'] = ['left_pct'=>30,'top_pct'=>42,'width_pct'=>40,'height_pct'=>20,'rotation'=>0];
    }
  }

  $layout = [
    'image' => $img,
    'slots' => $slots
  ];
@endphp

<div class="container">
  <div class="row g-4">
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage">
          <img id="np-base" src="{{ $layout['image'] }}" alt="Preview" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay np-name font-bebas"></div>
          <div id="np-prev-num"  class="np-overlay np-num font-bebas" style="font-size:64px;"></div>
        </div>
      </div>

      <div class="np-mobile-inputs d-md-none">
        <div><input type="text" id="np-mobile-name" maxlength="11" placeholder="YOUR NAME"><small>MAX. 11</small></div>
        <div><input type="text" id="np-mobile-num" maxlength="3" placeholder="YOUR NUMBER"><small>MAX. 3</small></div>
      </div>

      <div class="np-mobile-controls d-md-none">
        <div class="mt-3">
          <label for="np-mobile-font">Font</label>
          <select id="np-mobile-font" class="form-select">
            <option value="bebas">Bebas Neue (Bold)</option>
            <option value="anton">Anton</option>
            <option value="oswald">Oswald</option>
            <option value="impact">Impact</option>
          </select>
        </div>
        <div class="mt-3">
          <label>Text Color</label>
          <div class="swatches d-flex justify-content-center gap-2 flex-wrap mt-2">
            <button type="button" data-color="#FFFFFF" style="background:#FFFFFF;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
            <button type="button" data-color="#000000" style="background:#000000;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
            <button type="button" data-color="#FFD700" style="background:#FFD700;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
            <button type="button" data-color="#FF0000" style="background:#FF0000;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
            <button type="button" data-color="#1E90FF" style="background:#1E90FF;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
          </div>
        </div>
      </div>
    </div>

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

    <div class="col-md-3 np-col order-3 order-md-3">
      <h4 class="mb-1">{{ $product->title ?? 'Product' }}</h4>
      <div class="text-muted mb-3">Vendor: {{ $product->vendor ?? '—' }} • ₹ {{ number_format((float)($product->min_price ?? 0), 2) }}</div>

      <form id="np-atc-form" method="post" action="#" onsubmit="return false;">
        @csrf
        <input type="hidden" id="np-product-id" value="{{ $product->id ?? '' }}">
        <input type="hidden" id="np-shopify-id" value="{{ $product->shopify_product_id ?? '' }}">
        <input type="hidden" id="np-method" value="ADD TEXT">

        <input type="hidden" name="name_text"     id="np-name-hidden">
        <input type="hidden" name="number_text"   id="np-num-hidden">
        <input type="hidden" name="selected_font" id="np-font-hidden">
        <input type="hidden" name="text_color"    id="np-color-hidden">

        <button id="np-atc-btn" type="button" class="btn btn-primary w-100" disabled>Save</button>
      </form>
      <div class="small text-muted mt-2">Button enables when both Name &amp; Number are valid.</div>
    </div>
  </div>
</div>

<script>
(function(){
  const $ = id=>document.getElementById(id);
  const ctrls = $('np-controls'), note = $('np-note'), status = $('np-status'), btn = $('np-atc-btn');
  const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base');
  const mobName = $('np-mobile-name'), mobNum = $('np-mobile-num');

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
    pvName.classList.add(map[val]||'font-bebas');
    pvNum.classList.add(map[val]||'font-bebas');
  }

  function syncPreview(){
    pvName.textContent = (nameEl.value||'').toUpperCase();
    pvNum.textContent  = (numEl.value||'').replace(/\D/g,'').toUpperCase();
  }
  function syncHidden(){
    $('np-name-hidden').value = (nameEl.value||'').toUpperCase().trim();
    $('np-num-hidden').value  = (numEl.value||'').replace(/\D/g,'').trim();
    $('np-font-hidden').value = fontEl.value;
    $('np-color-hidden').value= colorEl.value;
  }

  nameEl?.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
  numEl?.addEventListener('input', e=>{ e.target.value=e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
  fontEl?.addEventListener('change', ()=>{ applyFont(fontEl.value); syncPreview(); syncHidden(); });
  colorEl?.addEventListener('input', ()=>{ pvName.style.color=colorEl.value; pvNum.style.color=colorEl.value; syncHidden(); });

  document.querySelectorAll('.np-swatch')?.forEach(b=>{
    b.addEventListener('click', ()=>{ document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active')); b.classList.add('active'); colorEl.value=b.dataset.color; pvName.style.color=b.dataset.color; pvNum.style.color=b.dataset.color; syncHidden(); });
  });

  mobName?.addEventListener('input', ()=>{ nameEl.value = mobName.value; nameEl.dispatchEvent(new Event('input')); });
  mobNum?.addEventListener('input', ()=>{ numEl.value = mobNum.value; numEl.dispatchEvent(new Event('input')); });

  applyFont(fontEl.value); pvName.style.color=colorEl.value; pvNum.style.color=colorEl.value;
  syncPreview(); syncHidden();

  // Layout injected from server (blade)
  const serverLayout = @json($layout);

  function place(el, slot){
    if(!slot || !el) return;
    const stage = document.querySelector('.np-stage');
    const W = stage.clientWidth;
    const H = baseImg.clientHeight || (stage.clientHeight || 300);
    const left = (slot.left_pct/100) * W;
    const top  = (slot.top_pct/100)  * H;
    const w    = (slot.width_pct/100) * W;
    const h    = (slot.height_pct/100) * H;

    el.style.position = 'absolute';
    el.style.left = left + 'px';
    el.style.top  = top  + 'px';
    el.style.width = Math.max(10, w) + 'px';
    el.style.height = Math.max(10, h) + 'px';
    el.style.display = 'flex';
    el.style.alignItems = 'center';
    el.style.justifyContent = 'center';
    let fontSize = Math.max(8, Math.floor(h * 0.7));
    el.style.fontSize = fontSize + 'px';
    el.style.transform = `rotate(${slot.rotation||0}deg)`;
    el.style.whiteSpace = 'nowrap';
    el.style.overflow = 'hidden';

    // shrink-to-fit
    const shrinkToFit = ()=>{
      while (el.scrollWidth > el.clientWidth && fontSize > 8) { fontSize -= 1; el.style.fontSize = fontSize + 'px'; }
    };
    shrinkToFit();
  }

  async function initPersonalization(){
    // If you have a public API to detect methods/layout, keep it.
    // By default use serverLayout prepared by blade.
    try {
      if (serverLayout && serverLayout.image) {
        // image already set by blade but ensure it's loaded
        baseImg.src = serverLayout.image;
      }

      // determine if personalisation is supported:
      // simple heuristic: if server supplied slot sizes -> show controls
      const slots = serverLayout.slots || {};
      if (!slots) {
        status.textContent = 'Personalization not available.';
        note.classList.remove('d-none');
        ctrls.classList.add('np-hidden');
        btn.disabled = false;
        return;
      }

      // show controls
      status.textContent = 'Personalization supported.';
      note.classList.add('d-none');
      ctrls.classList.remove('np-hidden');
      btn.disabled = true;

      // place overlays using slots.name and slots.number
      function apply(){ place(pvName, slots.name); place(pvNum, slots.number); }
      apply();
      baseImg.addEventListener('load', apply);
      window.addEventListener('resize', apply);

    } catch (e) {
      console.warn('layout fallback', e);
      status.textContent = 'Could not verify personalization. Proceeding without it.';
      note.classList.remove('d-none');
      ctrls.classList.add('np-hidden');
      btn.disabled = false;
    }
  }

  initPersonalization();

  // Save button behaviour (you can change to send to your admin endpoint)
  btn.addEventListener('click', async ()=>{
    if (!ctrls.classList.contains('np-hidden') && !validate()) { alert('Please enter valid Name & Number'); return; }
    btn.disabled = true; btn.textContent = 'Saving...';

    // Example: POST to admin route (must be authenticated) — adjust URL if needed.
    const payload = {
      product_id: $('np-product-id').value,
      shopify_id: $('np-shopify-id').value,
      name: $('np-name-hidden').value,
      number: $('np-num-hidden').value,
      font: $('np-font-hidden').value,
      color: $('np-color-hidden').value,
      view_id: {{ $view->id ?? 'null' }},
    };

    try {
      // Replace this URL with the endpoint you want. If admin endpoint, ensure user logged in.
      const url = '/admin/customize/save'; // <<-- adjust or implement this route on server
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type':'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept':'application/json'
        },
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        const txt = await res.text();
        console.error('Save failed', txt);
        alert('Save failed. Check console and logs.');
      } else {
        alert('Saved!');
      }
    } catch(err){
      console.error(err); alert('Save failed (network).');
    } finally {
      btn.disabled = false; btn.textContent = 'Save';
    }
  });

})();
</script>
</body>
</html>
