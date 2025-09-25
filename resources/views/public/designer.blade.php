<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* base (desktop-safe) */
.np-hidden { display: none !important; }
.font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
.font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
.font-oswald{font-family:'Oswald', Arial, sans-serif;}
.font-impact{font-family:Impact, 'Arial Black', sans-serif;}
.np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; }
.np-stage img { width:100%; height:auto; border-radius:6px; }
.np-overlay { position:absolute; color:#D4AF37; font-weight:700; text-transform:uppercase; letter-spacing:2px; white-space:nowrap; text-shadow:0 2px 6px rgba(0,0,0,0.35); }
.np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
.max-count{ display:none; }
.col-md-3.np-col > #np-controls {
  min-height: 520px;
  display:flex;
  align-items:flex-start;
  padding: 18px !important;
  box-sizing: border-box;
}
.vertical-tabs { display:flex; gap:18px; align-items:flex-start; }
.vt-icons { flex:0 0 64px; display:flex; flex-direction:column; gap:16px; align-items:center; }
.vt-btn { width:64px; height:64px; border-radius:8px; border:1px solid #ddd; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.vt-btn .vt-ico { font-size:18px; display:inline-block; line-height:1; }
.vt-btn.active { background:#f0f4ff; border-color:#aac6ff; }
.vt-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(100,150,255,0.12); }
.vt-panel .np-field-wrap { margin-bottom: 16px; }
.vt-panel .np-field-wrap input.form-control,
.vt-panel .np-field-wrap select.form-select,
.vt-panel .form-control-color { min-height:44px; }
/* panels */
 .vt-panels { flex:1 1 auto; padding-left:10px; }
.vt-panel { display:none; opacity:0; transition:all 0.3s ease; }
.vt-panel h6 { margin-bottom:12px; font-size:15px; font-weight:600; }
 .vt-panel.active { display:block; opacity:1; }

/* small screens: keep icons small & stacked (we won't change mobile behavior) */
@media (max-width:767px){
      .vertical-tabs{display:block;}
      .vt-icons{flex-direction:row;gap:8px;margin-bottom:8px;}
      .vt-btn{width:40px;height:40px;}
      .vt-panel{display:block!important;opacity:1!important;}
    }

/* ===== MOBILE-ONLY STYLES (paste inside your <style>) ===== */
@media (max-width: 767px) {

  /* 1) body stadium background + full-screen tint (below UI) */
  body {
    background-image: url('/images/stadium-bg.jpg'); /* change path if needed */
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    min-height: 100vh;
    position: relative;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35); /* tweak 0.22-0.36 */
    z-index: 5;
    pointer-events: none;
  }

  /* ensure UI sits above body tint */
  .container, .row, .np-stage, header, main, footer {
    position: relative;
    z-index: 10;
  }

  /* 2) np-stage & image frame (visible pale border around t-shirt) */
  .np-stage {
    padding: 12px;
    background: transparent;
    box-sizing: border-box;
    border-radius: 10px;
    z-index: 12;
    position: relative;
  }
  /* style the base image to look like framed box */
  .np-stage img#np-base {
    display:block;
    width:100%;
    height:auto;
    border-radius:8px;
    background-color:#f6f6f6;
    box-shadow: 0 6px 18px rgba(0,0,0,0.35);
    border: 3px solid rgba(255,255,255,0.12);
    position: relative;
    z-index: 14;
  }
  /* subtle overlay inside frame to keep overlays readable */
  .np-stage::after {
    content: "";
    position: absolute;
    left: 12px; right: 12px; top: 12px; bottom: 12px;
    border-radius: 8px;
    background: rgba(0,0,0,0.06);
    z-index: 15;
    pointer-events: none;
  }

  /* 3) mobile-only small header shown over image */
  .np-mobile-head {
    display: block !important;
    position: absolute;
    top: 8px;
    left: 14px;
    right: 14px;
    z-index: 22;
    color: #fff;
    text-shadow: 0 3px 8px rgba(0,0,0,0.7);
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    pointer-events: none;
  }

  /* 4) overlays (name & number) default centered */
  #np-prev-name, #np-prev-num {
    z-index: 24;
    position: absolute;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: 90% !important;
    text-align: center !important;
    color: #fff;
    text-shadow: 0 3px 8px rgba(0,0,0,0.7);
    pointer-events: none;
  }

  /* 5) INPUTS: name & number styles (underline only, centered, MAX tag on right) */
  .np-field-wrap.name-input,
  .np-field-wrap.number-input {
    position: relative;
    text-align: center;
    margin: 18px 0;
  }

  .np-field-wrap.name-input input.form-control,
  .np-field-wrap.number-input input.form-control {
    background: transparent;
    border: none;
    border-bottom: 2px solid #fff;
    border-radius: 0;
    color: #fff;
    text-align: center;
    width: 100%;
    letter-spacing: 2px;
    padding: 6px 0;
    font-weight: 700;
    text-transform: uppercase;
    box-shadow: none;
  }

  /* font-size: number larger than name */
  .np-field-wrap.number-input input.form-control { font-size: 20px; line-height:1; }
  .np-field-wrap.name-input   input.form-control { font-size: 20px; line-height:1; }

  /* placeholder color */
  .np-field-wrap.name-input input.form-control::placeholder,
  .np-field-wrap.number-input input.form-control::placeholder {
    color: rgba(255,255,255,0.45);
    font-weight: 400;
  }

  /* MAX label (right below input, aligned right) */
  .np-field-wrap.name-input .max-count,
  .np-field-wrap.number-input .max-count {
    display: block;
    position: absolute;
    right: 6px;
    bottom: -18px;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    opacity: 0.9;
  }

  /* 6) keep helper text legible */
  .np-field-wrap .form-text, .small-delivery { color: rgba(255,255,255,0.9); display : none}

  /* 7) hide desktop-only bits with this class */
  .hide-on-mobile { display: none !important; }

  /* 8) make Add to Cart visible */
  #np-atc-btn { display:block !important; z-index: 30; width:100% !important; }

  /* 9) mobile large overlay styles (apply via JS .mobile-style) */
  #np-prev-name.mobile-style {
    top: 18px !important;
    font-weight: 800 !important;
    font-size: clamp(18px, 5.6vw, 34px) !important;
    letter-spacing: 1.5px !important;
  }
  #np-prev-num.mobile-style {
    top: 52% !important;
    transform: translate(-50%,-50%) !important;
    font-weight: 900 !important;
    font-size: clamp(28px, 8.4vw, 56px) !important;
  }

  /* small utility */
  .mobile-display { display: none; } /* your elements with mobile-display will show */
  .color-display  { color: #fff; }
}


  </style>
</head>
<body class="py-4">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">

    <!-- Preview -->
    <div class="col-md-6 order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" src="{{ $img }}" alt="Preview" crossorigin="anonymous" 
               onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay font-bebas"></div>
          <div id="np-prev-num" class="np-overlay font-bebas"></div>
        </div>
      </div>
    </div>

   <div class="col-md-3 order-2 order-md-1">
      <div id="np-controls" class="border rounded p-3">
        <div class="vertical-tabs">
          <nav class="vt-icons">
            <button class="vt-btn" data-panel="panel-number"><span>①</span></button>
            <button class="vt-btn" data-panel="panel-name"><span>②</span></button>
            <button class="vt-btn" data-panel="panel-font"><span>𝙰</span></button>
            <button class="vt-btn" data-panel="panel-color"><span>⚪</span></button>
          </nav>

          <div class="vt-panels">
            <!-- Number -->
            <div id="panel-number" class="vt-panel">
              <h6>Number</h6>
              <input id="np-num" type="text" maxlength="3" class="form-control mb-2" placeholder="Your Number">
              <div class="form-text small">Digits only. 1–3 digits.</div>
            </div>

            <!-- Name -->
            <div id="panel-name" class="vt-panel">
              <h6>Name</h6>
              <input id="np-name" type="text" maxlength="12" class="form-control mb-2" placeholder="YOUR NAME">
              <div class="form-text small">Only A–Z and spaces. 1–12 chars.</div>
            </div>

            <!-- Font -->
            <div id="panel-font" class="vt-panel">
              <h6>Font</h6>
              <select id="np-font" class="form-select">
                <option value="bebas">Bebas Neue</option>
                <option value="anton">Anton</option>
                <option value="oswald">Oswald</option>
                <option value="impact">Impact</option>
              </select>
            </div>

            <!-- Color -->
            <div id="panel-color" class="vt-panel">
              <h6>Text Color</h6>
              <div class="d-flex gap-2 flex-wrap mb-2">
                <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFF"></button>
                <button type="button" class="np-swatch" data-color="#000000" style="background:#000"></button>
                <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
                <button type="button" class="np-swatch" data-color="#FF0000" style="background:#F00"></button>
                <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
              </div>
              <input id="np-color" type="color" class="form-control form-control-color" value="#D4AF37">
            </div>
          </div>
        </div>
      </div>
    </div>


     <div class="col-md-3 order-3">
      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">

        <div class="mb-3">
          <label>Size</label>
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            <option>S</option><option>M</option><option>L</option><option>XL</option><option>XXL</option>
          </select>
        </div>

        <div class="mb-3">
          <label>Quantity</label>
          <input id="np-qty" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled>Add to Cart</button>
      </form>
    </div>
  </div>
</div>

{{-- server-provided layoutSlots --}}
<script>
  window.layoutSlots = {!! json_encode($layoutSlots, JSON_NUMERIC_CHECK) !!};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
</script>

<script>
/* --- Tabs logic --- */
(function(){
  const btns = document.querySelectorAll('.vt-btn');
  const panels = document.querySelectorAll('.vt-panel');
  function closeAll(){ btns.forEach(b=>b.classList.remove('active')); panels.forEach(p=>p.classList.remove('active')); }
  btns.forEach(btn=>{
    btn.addEventListener('click',()=>{
      const panelId = btn.dataset.panel;
      if(document.getElementById(panelId).classList.contains('active')){
        closeAll();
      } else {
        closeAll();
        btn.classList.add('active');
        document.getElementById(panelId).classList.add('active');
      }
    });
  });
})();

/* --- Preview binding --- */
(function(){
  const nameEl=document.getElementById('np-name'),
        numEl=document.getElementById('np-num'),
        fontEl=document.getElementById('np-font'),
        colorEl=document.getElementById('np-color'),
        pvName=document.getElementById('np-prev-name'),
        pvNum=document.getElementById('np-prev-num'),
        btn=document.getElementById('np-atc-btn');

  function sync(){
    pvName.textContent=(nameEl.value||'YOUR NAME').toUpperCase();
    pvNum.textContent=(numEl.value||'YOUR NUMBER');
    document.getElementById('np-name-hidden').value=nameEl.value;
    document.getElementById('np-num-hidden').value=numEl.value;
    document.getElementById('np-font-hidden').value=fontEl.value;
    document.getElementById('np-color-hidden').value=colorEl.value;
    pvName.style.color=colorEl.value;
    pvNum.style.color=colorEl.value;
    btn.disabled=!(nameEl.value && numEl.value && document.getElementById('np-size').value);
  }

  nameEl.addEventListener('input',sync);
  numEl.addEventListener('input',e=>{e.target.value=e.target.value.replace(/\D/g,'').slice(0,3);sync();});
  fontEl.addEventListener('change',()=>{pvName.className='np-overlay font-'+fontEl.value;pvNum.className='np-overlay font-'+fontEl.value;sync();});
  colorEl.addEventListener('input',sync);
  document.querySelectorAll('.np-swatch').forEach(s=>s.addEventListener('click',()=>{colorEl.value=s.dataset.color;sync();}));
})();
</script>
{{-- core preview + UI JS (validation + preview layout) --}}
<script>
(function(){
  const $ = id => document.getElementById(id);

  function init(){
    const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
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

    function applyFont(val){
      const classes = ['font-bebas','font-anton','font-oswald','font-impact'];
      if (pvName) pvName.classList.remove(...classes);
      if (pvNum) pvNum.classList.remove(...classes);
      const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
      const c = map[val] || 'font-bebas';
      if (pvName) pvName.classList.add(c);
      if (pvNum) pvNum.classList.add(c);
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
    if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncPreview(); syncHidden(); });
    if (colorEl) colorEl.addEventListener('input', ()=>{ if (pvName) pvName.style.color = colorEl.value; if (pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

    document.querySelectorAll('.np-swatch')?.forEach(b=>{
      b.addEventListener('click', ()=>{
        document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        if (colorEl) colorEl.value = b.dataset.color;
        if (pvName) pvName.style.color = b.dataset.color;
        if (pvNum) pvNum.style.color = b.dataset.color;
        syncHidden();
      });
    });

    applyFont(fontEl ? fontEl.value : 'bebas');
    if (pvName && colorEl) pvName.style.color = colorEl.value;
    if (pvNum && colorEl) pvNum.style.color = colorEl.value;
    syncPreview(); syncHidden();

    if (baseImg) baseImg.addEventListener('load', applyLayout);
    window.addEventListener('resize', applyLayout);
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

{{-- html2canvas + single submit handler --}}
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
