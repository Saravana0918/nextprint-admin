<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Product – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* base fonts */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    /* preview stage */
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position:absolute; color:#D4AF37; font-weight:700; text-transform:uppercase; letter-spacing:2px; white-space:nowrap; text-shadow:0 2px 6px rgba(0,0,0,0.35); display:flex; align-items:center; justify-content:center; pointer-events:none; }

    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
    .max-count{ display:none; }

    .vertical-tabs { display:flex; gap:12px; align-items:flex-start;}
    .vt-icons { display:flex; flex-direction:column; gap:102px; flex:0 0 56px; align-items:center; padding-top:6px; }
    .vt-btn { display:flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:8px; border:1px solid #e6e6e6; background:#fff; cursor:pointer; }
    .vt-btn .vt-ico { font-size:18px; line-height:1; }
    .vt-btn.active { background:#f5f7fb; box-shadow:0 6px 18px rgba(10,20,40,0.04); border-color:#dbe7ff; }

    .vt-panels { flex:1 1 auto; min-width:0; position: relative; }
    .vt-panel h6 { margin: 0 0 6px 0; font-size:14px; font-weight:600; }

    body { background-color : #C0C0C0; }
    .desktop-display{ color:white; font-family: "Roboto Condensed", sans-serif; font-weight: bold; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:225px; }
    .hide-on-mobile { display: none !important; }

    @media (max-width: 767px) {
      .vt-icons { display: none; }
      .vt-panels { position: static; }
      .vt-panel { position: static; left:auto; width:100%; padding:8px 0; background:transparent; border:none; box-shadow:none; }
      .col-md-3.np-col > #np-controls { min-height: auto; padding: 12px !important; }

      body {
        background-image: url('/images/stadium-bg.jpg');
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
        min-height: 100vh;
        position: relative;
        margin-top: -70px;
      }
      body::before {
        content: "";
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        z-index: 5;
        pointer-events: none;
      }

      .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }

      .np-stage {
        padding: 12px;
        background: transparent;
        box-sizing: border-box;
        border-radius: 10px;
        z-index: 100;
        position: relative !important;
      }

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
      .np-stage::after {
        content: "";
        position: absolute;
        left: 12px; right: 12px; top: 12px; bottom: 12px;
        border-radius: 8px;
        background: rgba(0,0,0,0.06);
        z-index: 15;
        pointer-events: none;
      }

      .np-mobile-head { display:block !important; position:absolute; top:8px; left:14px; right:14px; z-index:22; color:#fff; text-shadow:0 3px 8px rgba(0,0,0,0.7); font-weight:700; font-size:13px; text-transform:uppercase; pointer-events:none; }

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

      .np-field-wrap.name-input, .np-field-wrap.number-input { position: relative; text-align:center; margin: 18px 0; }
      .np-field-wrap.name-input input.form-control, .np-field-wrap.number-input input.form-control {
        background: transparent; border: none; border-bottom: 2px solid #fff; color:#fff; text-align:center; text-transform:uppercase; font-weight:800; box-shadow:none;
      }

      .np-field-wrap.number-input input.form-control { font-size: clamp(18px, 5.6vw, 32px); }
      .np-field-wrap.name-input input.form-control   { font-size: clamp(18px, 5.6vw, 32px); }

      .np-field-wrap.name-input input.form-control::placeholder, .np-field-wrap.number-input input.form-control::placeholder { color: rgba(255,255,255,0.45); font-weight:400; }

      .np-field-wrap.name-input .max-count, .np-field-wrap.number-input .max-count {
        display:block; position:absolute; right:8px; bottom:-18px; color:#fff; font-weight:700; font-size:12px;
      }

      .np-field-wrap .form-text, .small-delivery { color: rgba(255,255,255,0.9); display:none; }
      .hide-on-mobile { display:none !important; }

      /* Mobile Add to Cart top-right */
      #np-atc-btn {
        position: fixed !important;
        top: 12px !important;
        right: 12px !important;
        z-index: 99999 !important;
        width: 130px !important;
        height: 44px !important;
        padding: 6px 12px !important;
        border-radius: 28px !important;
        box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
      }

      /* ---------- Fixes to allow controls to receive touches when stage is present ---------- */
      /* when stage has .covering it visually remains but does not block pointer events */
      .np-stage.covering { pointer-events: none; -webkit-user-select: none; }
      .np-stage.covering .np-overlay { pointer-events: none; }

      /* ensure swatches and font/select are above stage in stacking */
      .swatches-wrap, .vt-panels, .vt-panel, #panel-font {
        position: relative;
        z-index: 100000 !important;
      }
      #np-font, .np-swatch, #np-color {
        position: relative;
        z-index: 100001;
        -webkit-appearance: none;
      }

      /* special class applied when button is moved on top of stage */
      #np-atc-btn.mobile-fixed {
        position: absolute !important;
        top: -40px !important;
        right: -25px !important;
        z-index: 99999 !important;
        min-width: 110px !important;
        height: 40px !important;
        padding: 6px 12px !important;
        border-radius: 24px !important;
      }
      #np-atc-btn.mobile-fixed-outside {
        position: fixed !important;
        top: 12px !important;
        right: 12px !important;
      }
    } /* end mobile media */
    @media (min-width: 768px) {
      .vt-panels .vt-panel { display:block !important; opacity:1 !important; position:static !important; transform:none !important; width:100% !important; margin-bottom:12px; padding:12px !important; }
      .vt-icons { display: none !important; }
    }

    .col-md-3.np-col > #np-controls { padding: 16px !important; box-sizing: border-box; min-height: 360px; }
  </style>
</head>
<body class="body-padding">

<div class="container">
  <div class="row g-4">
    <!-- center preview -->
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" crossorigin="anonymous" src="https://via.placeholder.com/600x600/0a3/fff.png?text=TSHIRT" alt="Preview">
          <div id="np-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <!-- left controls -->
    <div class="col-md-3 np-col order-2 order-md-1">
      <div id="np-controls" class="border rounded p-3">
        <div class="vertical-tabs">
          <div class="vt-panels" aria-live="polite">
            <!-- Name panel -->
            <div id="panel-name" class="vt-panel" role="region">
              <h6 class="hide-on-mobile">Name</h6>
              <div>
                <div class="np-field-wrap name-input">
                  <input id="np-name" type="text" maxlength="12" class="form-control text-center" placeholder="YOUR NAME">
                  <div class="form-text small hide-on-mobile">Only A–Z and spaces. 1–12 chars.</div>
                  <span class="max-count">MAX. 12</span>
                </div>
              </div>
            </div>

            <!-- Number panel -->
            <div id="panel-number" class="vt-panel" role="region">
              <h6 class="hide-on-mobile">Number</h6>
              <div>
                <div class="np-field-wrap number-input">
                  <input id="np-num" type="text" maxlength="3" inputmode="numeric" class="form-control text-center" placeholder="YOUR NUMBER">
                  <div class="form-text small hide-on-mobile">Digits only. 1–3 digits.</div>
                  <span class="max-count">MAX. 3</span>
                </div>
              </div>
            </div>

            <!-- Font panel -->
            <div id="panel-font" class="vt-panel" role="region">
              <h6 class="hide-on-mobile">Font</h6>
              <div>
                <select id="np-font" class="form-select">
                  <option value="bebas">Bebas Neue</option>
                  <option value="anton">Anton</option>
                  <option value="oswald">Oswald</option>
                  <option value="impact">Impact</option>
                </select>
              </div>
            </div>

            <!-- Color panel -->
            <div id="panel-color" class="vt-panel" role="region">
              <h6 class="hide-on-mobile">Text Color</h6>
              <div class="swatches-wrap">
                <div class="d-flex gap-2 flex-wrap mb-2">
                  <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFFFFF"></button>
                  <button type="button" class="np-swatch" data-color="#000000" style="background:#000000"></button>
                  <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
                  <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
                  <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
                </div>
                <input id="np-color" type="color" class="form-control form-control-color" value="#D4AF37">
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- right purchase column -->
    <div class="col-md-3 np-col order-3 order-md-3 right-layout">
      <h4 class="mb-1 mobile-display desktop-display">Product Name</h4>
      <div class="text mb-3 mobile-display desktop-display">Price: Vendor • ₹ 499.00</div>

      <form id="np-atc-form" method="post" action="#">
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" name="preview_data" id="np-preview-hidden">

        <div class="mb-3">
          <label class="form-label color-display desktop-display">Size</label>
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
          <label class="form-label color-display desktop-display">Quantity</label>
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled>Add to Cart</button>
      </form>

      <div class="small-delivery text mt-2 desktop-display">Button enables when both Name & Number are valid.</div>
    </div>
  </div>
</div>

<script>
/* ---------- CORE UI + fixes ---------- */
(function(){
  const $ = id => document.getElementById(id);

  /* expose applyFont globally so delegated listener can call it */
  window.applyFont = function(val){
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num');
    const classes = ['font-bebas','font-anton','font-oswald','font-impact'];
    if (pvName) pvName.classList.remove(...classes);
    if (pvNum) pvNum.classList.remove(...classes);
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const c = map[val] || 'font-bebas';
    if (pvName) pvName.classList.add(c);
    if (pvNum) pvNum.classList.add(c);
  };

  function init(){
    const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
    const btn = $('np-atc-btn');

    const layout = {}; // keep if needed
    const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

    function validate(){
      const okName = nameEl ? NAME_RE.test((nameEl.value||'').trim()) : true;
      const okNum = numEl ? NUM_RE.test((numEl.value||'').trim()) : true;
      if (btn) {
        const sizeOk = document.getElementById('np-size')?.value;
        btn.disabled = !(okName && okNum && sizeOk);
      }
      return okName && okNum;
    }

    function syncPreview(){
      if (pvName && nameEl) {
        const txt = (nameEl.value||'').toUpperCase();
        pvName.textContent = txt || 'NAME';
      }
      if (pvNum && numEl) {
        const numTxt = (numEl.value||'').replace(/\D/g,'');
        pvNum.textContent = numTxt || 'NUMBER';
      }
    }

    function syncHidden(){
      document.getElementById('np-name-hidden').value  = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
      document.getElementById('np-num-hidden').value   = (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim();
      document.getElementById('np-font-hidden').value  = fontEl ? fontEl.value : '';
      document.getElementById('np-color-hidden').value = colorEl ? colorEl.value : '';
    }

    /* existing bindings */
    if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
    if (numEl)  numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
    if (fontEl) fontEl.addEventListener('change', ()=>{ window.applyFont(fontEl.value); syncPreview(); syncHidden(); });
    if (colorEl) colorEl.addEventListener('input', ()=>{ if (pvName) pvName.style.color = colorEl.value; if (pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

    // swatches initial binding (in case of static DOM)
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

    // initial state
    window.applyFont(fontEl ? fontEl.value : 'bebas');
    if (pvName && colorEl) pvName.style.color = colorEl.value;
    if (pvNum && colorEl) pvNum.style.color = colorEl.value;
    syncPreview(); syncHidden();

    // image/layout adjustments if needed
    window.addEventListener('resize', ()=>{ /* could re-calc overlays if required */ });
    baseImg && baseImg.addEventListener('load', ()=>{ /* calc layout if required */ });

    // wire up size change to revalidate
    document.getElementById('np-size')?.addEventListener('change', validate);
    document.getElementById('np-qty')?.addEventListener('input', validate);
  } // init

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

  /* ---------- MOBILE FIX: keep stage visible but not blocking touches when keyboard opens ---------- */
  (function mobileKeyboardHandler(){
    const stage = $('np-stage');
    const btn = $('np-atc-btn');
    if (!stage) return;

    function onViewportResize(){
      if (!window.visualViewport) return;
      const vH = window.visualViewport.height;
      const vhRatio = vH / window.innerHeight;
      if (vhRatio < 0.75) {
        // keyboard opened
        stage.classList.add('covering');    // visually intact but pointer-events:none
        // keep stage positioned for better UX
        stage.style.position = 'fixed';
        stage.style.top = '60px';
        stage.style.left = '50%';
        stage.style.transform = 'translateX(-50%)';
        stage.style.maxWidth = '420px';

        // ensure Add to cart remains clickable
        if (btn) {
          // move Add to cart into stage if you prefer floating over stage
          if (btn.parentNode !== stage) {
            // create placeholder to restore later
            if (!document.getElementById('np-atc-placeholder')) {
              const placeholder = document.createElement('div');
              placeholder.id = 'np-atc-placeholder';
              btn.parentNode.insertBefore(placeholder, btn);
            }
            stage.appendChild(btn);
            btn.classList.add('mobile-fixed');
          }
          btn.style.pointerEvents = 'auto';
        }
      } else {
        // keyboard closed
        stage.classList.remove('covering');
        stage.style.position = '';
        stage.style.top = '';
        stage.style.left = '';
        stage.style.transform = '';
        stage.style.maxWidth = '';

        // restore button to original place if placeholder exists
        const placeholder = document.getElementById('np-atc-placeholder');
        if (placeholder && btn) {
          placeholder.parentNode.insertBefore(btn, placeholder.nextSibling);
          btn.classList.remove('mobile-fixed');
        }
        if (btn) btn.style.pointerEvents = '';
      }
    }

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', onViewportResize);
      setTimeout(onViewportResize, 120);
    } else {
      window.addEventListener('resize', onViewportResize);
      setTimeout(onViewportResize, 120);
    }
  })();

  /* ---------- Robust delegated handlers (works even if DOM nodes are moved) ---------- */
  (function delegatedHandlers(){
    document.addEventListener('click', function(e){
      // swatch clicked
      const sw = e.target.closest('.np-swatch');
      if (sw) {
        const color = sw.dataset.color;
        const colorInput = document.getElementById('np-color');
        if (colorInput) colorInput.value = color;
        const pvName = document.getElementById('np-prev-name');
        const pvNum  = document.getElementById('np-prev-num');
        if (pvName) pvName.style.color = color;
        if (pvNum) pvNum.style.color = color;
        document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
        sw.classList.add('active');
        // update hidden
        document.getElementById('np-color-hidden').value = color;
      }
    }, true);

    // color input change
    document.addEventListener('input', function(e){
      if (e.target && e.target.id === 'np-color') {
        const c = e.target.value;
        const pvName = document.getElementById('np-prev-name');
        const pvNum  = document.getElementById('np-prev-num');
        if (pvName) pvName.style.color = c;
        if (pvNum) pvNum.style.color = c;
        document.getElementById('np-color-hidden').value = c;
      }
    }, true);

    // font select change (delegated)
    document.addEventListener('change', function(e){
      if (e.target && e.target.id === 'np-font') {
        const v = e.target.value;
        if (typeof window.applyFont === 'function') {
          window.applyFont(v);
        }
        // persist hidden
        document.getElementById('np-font-hidden').value = v;
      }
    }, true);
  })();

})(); /* end main IIFE */
</script>

<!-- html2canvas included if you need to generate preview image on submit -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
/* Submit handler (unchanged logic - ensure hidden fields are synced before capture) */
(function(){
  const form = document.getElementById('np-atc-form');
  if (!form) return;
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    // validation basic
    const name = document.getElementById('np-name')?.value || '';
    const num  = document.getElementById('np-num')?.value || '';
    const size = document.getElementById('np-size')?.value || '';
    if (!size) { alert('Please select a size.'); return; }
    if (!/^[A-Za-z ]{1,12}$/.test(name.trim()) || !/^\d{1,3}$/.test(num.trim())) {
      alert('Please enter valid Name (A–Z, 1–12) and Number (1–3 digits).');
      return;
    }

    // sync hidden before capture
    document.getElementById('np-name-hidden').value = name.toUpperCase().trim();
    document.getElementById('np-num-hidden').value = num.replace(/\D/g,'').trim();
    document.getElementById('np-font-hidden').value = document.getElementById('np-font')?.value || '';
    document.getElementById('np-color-hidden').value = document.getElementById('np-color')?.value || '';

    // capture preview
    try {
      const stage = document.getElementById('np-stage');
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
      document.getElementById('np-preview-hidden').value = canvas.toDataURL('image/png');
    } catch (err) {
      console.warn('Preview capture failed', err);
    }

    // here you would submit normally (disabled in this demo)
    // form.submit();
    alert('Form ready to submit (demo) — check hidden fields.');
  });
})();
</script>

</body>
</html>
