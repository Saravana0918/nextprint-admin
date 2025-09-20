<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo e($product->title); ?> – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">

  <style>

  /* =========================
   NextPrint — Page styling
   Drop this into <head> or theme CSS
   ========================= */

/* ---- basic helpers ---- */
.np-hidden { display: none !important; }
.font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
.font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
.font-oswald{font-family:'Oswald', Arial, sans-serif;}
.font-impact{font-family:Impact, 'Arial Black', sans-serif;}

/* page card look */
.border.rounded.p-3 {
  background: #fff;
  border: 1px solid #e6e9ee;
  border-radius: 8px;
  padding: 1rem !important;
  box-shadow: 0 6px 18px rgba(15,23,42,0.04);
}

/* heading */
h6.mb-3 {
  margin-bottom: .75rem !important;
  font-weight: 700;
  color: #111827;
  font-size: 2rem;
}

/* status & note */
#np-status, #np-note {
  font-size: .85rem;
  color: #6b7280;
}

/* form-label polish */
.form-label { font-weight: 600; color: #374151; font-size: 1.3rem; }

/* help & errors */
.form-text { color: #6b7280; font-size: 1rem; margin-top: .25rem; }
.text-danger.small { font-size: .8rem; }

/* inputs - make consistent */
#np-num, #np-name, #np-font, #np-color,
#np-mobile-name, #np-mobile-num, #np-mobile-font {
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  padding: .55rem .65rem;
  font-size: .95rem;
  transition: border-color .12s ease, box-shadow .12s ease;
}
#np-num:focus, #np-name:focus, #np-font:focus, #np-color:focus,
#np-mobile-name:focus, #np-mobile-num:focus, #np-mobile-font:focus {
  outline: none;
  border-color: #60a5fa;
  box-shadow: 0 0 0 6px rgba(96,165,250,0.06);
}

/* font select custom arrow (desktop) */
#np-font {
  -webkit-appearance: none; appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, #6b7280 50%),
                    linear-gradient(135deg, #6b7280 50%, transparent 50%),
                    linear-gradient(to right, #fff, #fff);
  background-position: calc(100% - 18px) calc(1em + 2px), calc(100% - 13px) calc(1em + 2px), 100% 0;
  background-size: 6px 6px, 6px 6px, 1.5em 100%;
  background-repeat: no-repeat;
  padding-right: 2.4rem;
}

/* color swatches (desktop left panel) */
.np-swatch {
  width: 28px; height: 28px; border-radius: 6px;
  border: 1px solid rgba(0,0,0,0.06); cursor: pointer;
  box-shadow: 0 4px 10px rgba(16,24,40,0.03);
  transition: transform .08s ease, box-shadow .08s ease;
  display: inline-block;
}
.np-swatch:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(16,24,40,0.06); }
.np-swatch.active {
  box-shadow: 0 0 0 4px rgba(59,130,246,0.14);
  border-color: rgba(59,130,246,0.9);
}

/* native color input */
.form-control-color {
  padding: .25rem 0;
  height: 40px;
  width: 100%;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  box-sizing: border-box;
  margin-top: .5rem;
}

/* stage / preview area */
.np-stage {
  position: relative;
  width: 100%;
  max-width: 562px;
  margin: 0 auto;
  min-height: 220px;
  overflow: visible;
  background: #fff;
}
.np-stage img {
  width: 100%;
  height: auto;
  display: block;
  border-radius: 6px;
}

/* overlay text */
.np-overlay {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  color: #D4AF37; /* default gold */
  text-shadow: 0 2px 6px rgba(0,0,0,0.35);
  white-space: nowrap;
  pointer-events: none;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
  line-height: 1;
}

/* name smaller and number bigger by default */
.np-name { font-size: 26px; top: 18%; }
.np-num  { font-size: 64px; top: 42%; }

/* shrink / responsiveness handled by inline js in your page */

/* mobile-only theme */
@media (max-width: 767.98px) {
  body {
  background-image: url('<?php echo e(asset("images/stadium-bg.jpg")); ?>');
  background-size: cover;
  background-position: center top;
  background-repeat: no-repeat;
}

.container {
  background: transparent;
}

/* If you want overlay to improve readability */
body::before {
  content: "";
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.45);
  pointer-events: none;
  z-index: 1;
}

/* Ensure your content sits above */
.container, .np-stage, .np-mobile-inputs {
  position: relative;
  z-index: 2;
}


  .container, .np-stage, .np-mobile-inputs { position: relative; z-index: 2; }

  .col-md-3.np-col { display: none !important; }
  .col-md-6.np-col { order: 1 !important; flex: 0 0 100% !important; max-width: 100% !important; }

  .np-mobile-inputs { margin-top: 16px; text-align: center; font-family: 'Bebas Neue', sans-serif; background: transparent; z-index:3; }
  .np-mobile-inputs input {
    background: transparent; border: none;
    border-bottom: 2px solid rgba(255,255,255,0.95);
    text-align: center; color: #fff !important; font-size: 20px;
    letter-spacing: 2px; text-transform: uppercase; outline: none;
    width: 80%; margin: 10px auto;
  }
  .np-mobile-inputs input::placeholder { color: rgba(255,255,255,0.9); opacity: 1; }
  .np-mobile-inputs small { display:block; font-size:12px; color: #FFD700; width:80%; margin:0 auto 8px; text-align:right; }

  .np-mobile-controls { margin: 16px auto; width: 86%; z-index:3; }
  .np-mobile-controls label { color: #fff; font-weight:600; display:block; margin-bottom:6px; }
  .np-mobile-controls select.form-select { width: 100%; padding: 8px; border-radius: 8px; }
  .np-mobile-controls .swatches button {
    width: 28px; height: 28px; border-radius: 50%;
    border: 1px solid #ddd; margin-right:4px; cursor:pointer; background-clip:padding-box;
  }
  .np-mobile-controls .swatches button.active { outline: 2px solid #fff; outline-offset: 3px; }
}

/* right column polish */
.col-md-3.np-col.order-3 { padding-left: 0.5rem; }
#np-atc-form .btn {
  border-radius: 8px;
  padding: .55rem .75rem;
  font-weight: 600;
}

/* responsive spacing */
@media (min-width: 768px) {
  .row.g-4 { align-items: flex-start; }
  .col-md-6.np-col { display: block; }
}

/* Accessibility focus */
.np-swatch:focus,
.np-mobile-controls .swatches button:focus {
  outline: 3px solid rgba(59,130,246,0.16);
  outline-offset: 3px;
}

</style>

</head>
<body class="py-4">
<?php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
?>
<div class="container">
<div class="row g-4">
  
<div class="col-md-6 np-col order-1 order-md-2">
  <div class="border rounded p-3">
    <div class="np-stage">
      <img id="np-base" src="<?php echo e($img); ?>" alt="Preview"
           onerror="this.onerror=null;this.src='<?php echo e(asset('images/placeholder.png')); ?>'">
      <div id="np-prev-name" class="np-overlay np-name font-bebas"></div>
      <div id="np-prev-num"  class="np-overlay np-num font-bebas" style="font-size:64px;"></div>
    </div>
  </div>

  <!-- MOBILE INPUTS -->
  <div class="np-mobile-inputs d-md-none">
    <div>
      <input type="text" id="np-mobile-name" maxlength="11" placeholder="YOUR NAME">
      <small>MAX. 11</small>
    </div>
    <div>
      <input type="text" id="np-mobile-num" maxlength="2" placeholder="YOUR NUMBER">
      <small>MAX. 2</small>
    </div>
  </div>

  <!-- MOBILE CONTROLS -->
  <div class="np-mobile-controls d-md-none">
    <div class="mt-3">
      <label for="np-mobile-font" style="color:#fff;">Font</label>
      <select id="np-mobile-font" class="form-select">
        <option value="bebas">Bebas Neue (Bold)</option>
        <option value="anton">Anton</option>
        <option value="oswald">Oswald</option>
        <option value="impact">Impact</option>
      </select>
    </div>

    <div class="mt-3">
      <label style="color:#fff;">Text Color</label>
      <div class="swatches d-flex justify-content-center gap-2 flex-wrap mt-2">
        <button type="button" data-color="#FFFFFF" style="background:#FFFFFF;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
        <button type="button" data-color="#000000" style="background:#000000;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
        <button type="button" data-color="#FFD700" style="background:#FFD700;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
        <button type="button" data-color="#FF0000" style="background:#FF0000;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
        <button type="button" data-color="#1E90FF" style="background:#1E90FF;width:28px;height:28px;border-radius:50%;border:1px solid #ddd;"></button>
      </div>
    </div>
  </div>
</div> <!-- col-md-6 ends here -->



  
  <div class="col-md-3 np-col order-2 order-md-1">
    <div class="border rounded p-3">
      <h6 class="mb-3">Customize</h6>

      <div id="np-status" class="small text-muted mb-2">Checking methods…</div>
      <div id="np-note" class="small text-muted mb-3 d-none">Personalization not available for this product.</div>

      <div id="np-controls" class="np-hidden">
        <div class="mb-3">
          <label for="np-num" class="form-label">Number (1–3 digits)</label>
          <input id="np-num" type="text" inputmode="numeric" maxlength="3"
                 class="form-control" placeholder="e.g. 10" aria-invalid="false" autocomplete="off">
          <div id="np-num-help" class="form-text">Digits only. 1–3 digits.</div>
          <div id="np-num-err"  class="text-danger small d-none">Enter 1–3 digits only.</div>
        </div>

        <div class="mb-3">
          <label for="np-name" class="form-label">Name (max 12)</label>
          <input id="np-name" type="text" maxlength="12"
                 class="form-control" placeholder="e.g. SACHIN" aria-invalid="false" autocomplete="off">
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
            <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFFFFF" aria-label="White"></button>
            <button type="button" class="np-swatch" data-color="#000000" style="background:#000000" aria-label="Black"></button>
            <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700" aria-label="Gold"></button>
            <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000" aria-label="Red"></button>
            <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF" aria-label="Blue"></button>
          </div>
          <input id="np-color" type="color" class="form-control form-control-color mt-2" value="#D4AF37" title="Pick color">
        </div>
      </div>
    </div>
  </div>

  
  <div class="col-md-3 np-col order-3 order-md-3">
    <h4 class="mb-1"><?php echo e($product->title); ?></h4>
    <div class="text-muted mb-3">Vendor: <?php echo e($product->vendor); ?> • ₹ <?php echo e(number_format((float)$product->min_price, 2)); ?></div>

    <form id="np-atc-form" method="post" action="#">
      <?php echo csrf_field(); ?>
      <input type="hidden" id="np-product-id" value="<?php echo e($product->id); ?>">
      <input type="hidden" id="np-shopify-id" value="<?php echo e($product->shopify_product_id); ?>">
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


<script>
(function(){
  const $ = (id)=>document.getElementById(id);

  // UI refs
  const ctrls = $('np-controls');
  const note  = $('np-note');
  const status= $('np-status');
  const btn   = $('np-atc-btn');

  // inputs & preview
  const nameEl  = $('np-name');
  const numEl   = $('np-num');
  const fontEl  = $('np-font');
  const colorEl = $('np-color');
  const pvName  = $('np-prev-name');
  const pvNum   = $('np-prev-num');
  const baseImg = $('np-base');

  // MOBILE inputs
  const mobName = document.getElementById('np-mobile-name');
  const mobNum  = document.getElementById('np-mobile-num');

  // validators
  const NAME_RE = /^[A-Za-z ]{1,12}$/;
  const NUM_RE  = /^\d{1,3}$/;

  function validate(){
    const okName = NAME_RE.test((nameEl.value||'').trim());
    const okNum  = NUM_RE.test((numEl.value||'').trim());
    nameEl.setAttribute('aria-invalid', okName?'false':'true');
    numEl.setAttribute('aria-invalid',  okNum?'false':'true');
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
    pvNum.textContent  = (numEl.value||'').replace(/\D/g,'').toUpperCase();
  }
  function syncHidden(){
    $('np-name-hidden').value = (nameEl.value||'').toUpperCase().trim();
    $('np-num-hidden').value  = (numEl.value||'').replace(/\D/g,'').trim();
    $('np-font-hidden').value = fontEl.value;
    $('np-color-hidden').value= colorEl.value;
  }

  // listeners
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

  // MOBILE sync
  mobName?.addEventListener('input', ()=>{
    nameEl.value = mobName.value;
    nameEl.dispatchEvent(new Event('input'));
  });
  mobNum?.addEventListener('input', ()=>{
    numEl.value = mobNum.value;
    numEl.dispatchEvent(new Event('input'));
  });

  applyFont(fontEl.value);
  pvName.style.color=colorEl.value; pvNum.style.color=colorEl.value;
  syncPreview();
  syncHidden();

  // MOBILE font selector
const mobFont = document.getElementById('np-mobile-font');
mobFont?.addEventListener('change', ()=>{
  fontEl.value = mobFont.value;
  fontEl.dispatchEvent(new Event('change'));
});

// MOBILE color swatches
document.querySelectorAll('.np-mobile-controls .swatches button').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('.np-mobile-controls .swatches button').forEach(x=>x.classList.remove('active'));
    btn.classList.add('active');
    const color = btn.dataset.color;
    colorEl.value = color;
    pvName.style.color = color;
    pvNum.style.color = color;
    syncHidden();
  });
});


  // ---- CORE: show controls only if ADD TEXT, then place by layout ----
  const handle = "<?php echo e($product->handle); ?>";
  const norm = (c)=>String(c||'').toUpperCase().replace(/\s+/g,'').trim();

  async function initPersonalization(){
    try {
      const mRes = await fetch(`/api/public/products/${encodeURIComponent(handle)}/methods`, {headers:{'Accept':'application/json'}});
      const mData = mRes.ok ? await mRes.json() : {};
      const arr = Array.isArray(mData) ? mData : (mData.method_codes || []);
      const supports = arr.map(norm).some(c=>['ATC','ADDTEXT'].includes(c));

      if (!supports) {
        status.textContent = 'Personalization not available.';
        note.classList.remove('d-none');
        ctrls.classList.add('np-hidden');
        btn.disabled = false;
        return;
      }

      status.textContent = 'Personalization supported.';
      note.classList.add('d-none');
      ctrls.classList.remove('np-hidden');
      btn.disabled = true;

      try {
        const lRes = await fetch(`/api/public/products/${encodeURIComponent(handle)}/layout`, {headers:{'Accept':'application/json'}});
        if(!lRes.ok) throw 0;
        const data = await lRes.json();

        const isPlaceholder = (u) => {
          try { return new URL(u, location.origin).pathname.endsWith('/images/placeholder.png'); }
          catch { return false; }
        };
        if (data.image && !isPlaceholder(data.image)) {
          baseImg.src = data.image;
        }

        function place(el, slot){
          if(!slot) return;
          const stage = document.querySelector('.np-stage');
          const W = stage.clientWidth;
          const H = baseImg.clientHeight;

          const left = (slot.left_pct/100) * W;
          const top  = (slot.top_pct/100) * H;
          const w    = (slot.width_pct/100) * W;
          const h    = (slot.height_pct/100) * H;

          el.style.position = 'absolute';
          el.style.left     = left + 'px';
          el.style.top      = top + 'px';
          el.style.width    = w + 'px';
          el.style.height   = h + 'px';

          el.style.display = 'flex';
          el.style.alignItems = 'center';
          el.style.justifyContent = 'center';

          let fontSize = Math.max(10, h * 0.8);
          el.style.fontSize = fontSize + 'px';

          el.style.transform = `rotate(${slot.rotation||0}deg)`;
          el.style.whiteSpace = 'nowrap';
          el.style.overflow   = 'hidden';

          const shrinkToFit = () => {
            while (el.scrollWidth > el.clientWidth && fontSize > 8) {
              fontSize -= 1;
              el.style.fontSize = fontSize + 'px';
            }
          };
          shrinkToFit();
        }

        const apply = ()=>{ place(pvName, data.slots?.name); place(pvNum,  data.slots?.number); };
        apply();
        baseImg.addEventListener('load', apply);
        window.addEventListener('resize', apply);

      } catch(e){
        console.warn('layout fallback', e);
      }

    } catch (e) {
      status.textContent = 'Could not verify personalization. Proceeding without it.';
      note.classList.remove('d-none');
      ctrls.classList.add('np-hidden');
      btn.disabled = false;
    }
  }
  initPersonalization();

  // Submit guard
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
</div>
</body>
</html>
<?php /**PATH E:\Nextprint-designing-tool\backend-app\resources\views/store/product.blade.php ENDPATH**/ ?>