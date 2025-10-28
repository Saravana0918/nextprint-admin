@extends('layouts.admin')
@section('title','Decoration Area')

@push('styles')
<style>
  #canvasWrap { max-width: 980px; border: 1px dashed #bbb; background:#f8f9fb; }
  #canvasWrap canvas { display:block; }
  .hint { font-size:.9rem; color:#666; }

  /* Modal */
  #tplModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);
            align-items:center;justify-content:center;z-index:100000}
  #tplModal .box{background:#fff;max-width:720px;width:92%;
                 max-height:85vh;overflow:auto;padding:16px;border-radius:10px}
</style>
@endpush

@section('content')

<h4 class="mb-3">{{ $product->name }} — {{ $view->name }} (Decoration Area)</h4>

<div class="mb-2 d-flex gap-2">
  <button id="btnSelectTemplate" onclick="openTplModal()" class="btn btn-outline-secondary">
    Select decoration area
  </button>

  <button id="btnDeleteArea" class="btn btn-outline-danger">Delete selected</button>
  <button id="btnClearAreas" class="btn btn-outline-secondary">Clear all</button>
  <button type="button" id="btnSaveAll" class="btn btn-primary ms-1">Save all</button>
</div>

<!-- Template modal -->
<div id="tplModal">
  <div class="box">
    <div class="d-flex gap-2 mb-2">
      <input id="tplSearch" class="form-control" placeholder="Search">
      <a class="btn btn-sm btn-light" target="_blank" href="{{ route('admin.decoration.index') }}">+ Manage</a>
      <button type="button" class="btn btn-sm btn-dark" id="tplClose">×</button>
    </div>
    <div id="tplList" class="list-group"></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div id="canvasWrap">
      <canvas id="design-canvas"></canvas>
    </div>
    <div class="hint mt-2">
      Drag/resize the red box. Use toolbar buttons above.
      @php
        $bgLink = $bgUrl ?? null;
      @endphp
      @if($bgLink)
        • BG: <a href="{{ $bgLink }}" target="_blank">open image</a>
      @else
        • No view/product image yet.
      @endif
    </div>

  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-body">
        <form method="post" action="{{ route('admin.views.uploadImage', [$product->id,$view->id]) }}" enctype="multipart/form-data">
          @csrf
          <label class="form-label">Upload view image (PNG/JPG)</label>
          <input type="file" name="view_image" class="form-control" accept="image/*" required>
          <button class="btn btn-secondary mt-2 w-100">Upload</button>
          @error('view_image')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
          @if(session('ok'))<div class="alert alert-success mt-3 py-2 mb-0">{{ session('ok') }}</div>@endif
        </form>
      </div>
    </div>

    {{-- Legacy single-area form (still useful to see numbers for the selected rect) --}}
    <div class="card">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">X (mm)</label>
            <input class="form-control" id="x_mm" value="{{ $area?->x_mm ?? '' }}">
          </div>
          <div class="col-6">
            <label class="form-label">Y (mm)</label>
            <input class="form-control" id="y_mm" value="{{ $area?->y_mm ?? '' }}">
          </div>
          <div class="col-6">
            <label class="form-label">Width (mm)</label>
            <input class="form-control" id="width_mm" value="{{ $area?->width_mm ?? '' }}">
          </div>
          <div class="col-6">
            <label class="form-label">Height (mm)</label>
            <input class="form-control" id="height_mm" value="{{ $area?->height_mm ?? '' }}">
          </div>
          <div class="col-6">
            <label class="form-label">DPI</label>
            <input class="form-control" id="dpi" value="{{ $area?->dpi ?? '' }}">
          </div>
          <div class="col-6">
            <label class="form-label">Rotation</label>
            <input class="form-control" id="rotation" value="{{ $area?->rotation ?? 0 }}">
          </div>

          <input type="hidden" id="template_id" value="{{ $area?->template_id ?? '' }}">
          <input type="hidden" id="mask_svg_path" value="{{ $area?->mask_svg_path ?? '' }}">
        </div>

        <div class="small text-muted mt-2">Numbers reflect the current selection on canvas.</div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/fabric@5.3.0/dist/fabric.min.js"></script>
<script>
const DPI_DEFAULT = 150;
const MM_PER_INCH = 25.4;
const pxPerMm = (dpi)=> (dpi || DPI_DEFAULT) / MM_PER_INCH;
function mmToPx(mm, dpi){ return mm * pxPerMm(dpi); }

/** Create the editable area group (rect with dashed red stroke) */
function createAreaGroup(pxW, pxH, opts) {
  const rect = new fabric.Rect({
    width: pxW,
    height: pxH,
    fill: 'rgba(255,0,0,0.08)',
    stroke: 'red',
    strokeWidth: 1,
    strokeDashArray: [4,2],
    originX: 'center',
    originY: 'center'
  });

  const group = new fabric.Group([rect], Object.assign({
    left: 100, top: 100,
    hasRotatingPoint: false,
    lockRotation: true
  }, opts || {}));

  return group;
}

/** Load an SVG and fit it inside the group's rect (visual guide only; not saved) */
function attachSvgIntoAreaGroup(canvas, group, svgUrl) {
  if (!svgUrl) return;
  fabric.loadSVGFromURL(svgUrl, function(objects, options) {
    const svg = fabric.util.groupSVGElements(objects, options);
    const rect = group._objects.find(o => o.type === 'rect');
    if (!rect) return;

    function fit(){
      const sx = rect.width / svg.width;
      const sy = rect.height / svg.height;
      svg.set({
        left: -rect.width/2,
        top:  -rect.height/2,
        scaleX: sx, scaleY: sy,
        opacity: 0.85,
        evented: false,
        selectable: false,
        excludeFromExport: true  // do NOT serialize to DB
      });
    }

    fit();
    group.addWithUpdate(svg);
    canvas.requestRenderAll();

    group.on('scaled',  ()=>{ fit(); canvas.requestRenderAll(); });
    group.on('modified',()=>{ fit(); canvas.requestRenderAll(); });
  });
}
</script>
<script>
let templatesById = {};
fetch('/admin/api/decoration-areas')
  .then(r => r.json())
  .then(list => {
    list.forEach(t => templatesById[t.id] = t);
    // After templates load, attach overlays for already-loaded areas on canvas
    setTimeout(attachOverlaysForExisting, 200);
  });
</script>
<script>
function attachOverlaysForExisting(){
  if (typeof canvas === 'undefined') return;
  canvas.getObjects().forEach(obj => {
    if (obj.type !== 'group') return;
    // Your code that initially creates groups should set one of these:
    const tid = obj.template_id || (obj.data && obj.data.template_id);
    const t   = tid && templatesById[tid];
    if (t && t.svg_url) attachSvgIntoAreaGroup(canvas, obj, t.svg_url);
  });
}
</script>
<script>
// Listen for clicks on any element with data-template-id (your items)
document.addEventListener('click', function(e){
  const item = e.target.closest('[data-template-id]');
  if (!item) return;

  const tid = +item.dataset.templateId;
  const t = templatesById[tid];
  if (!t) return;

  const dpi = window.currentDpi || DPI_DEFAULT;
  const pxW = Math.max(10, mmToPx(t.width_mm, dpi));
  const pxH = Math.max(10, mmToPx(t.height_mm, dpi));

  const group = createAreaGroup(pxW, pxH);
  canvas.add(group).setActiveObject(group);
  canvas.requestRenderAll();

  if (t.svg_url) attachSvgIntoAreaGroup(canvas, group, t.svg_url);

  // store for save()
  group.template_id = t.id;
  group.slot_key    = t.slot_key || null;
});
</script>

{{-- Pass saved areas from PHP → JS --}}
<script>
  window.existingAreas = @json(($existing ?? collect())->toArray());
</script>

<script>
/* ================= CANVAS / CORE ================= */
(function () {
  const dpiInput = document.getElementById('dpi');
  const mm2px = (mm)=> (mm/25.4) * parseFloat(dpiInput.value || 300);
  const px2mm = (px)=> (px * 25.4) / parseFloat(dpiInput.value || 300);
  window.mm2px = mm2px;
  window.px2mm = px2mm;

  const canvas = new fabric.Canvas('design-canvas', { selection:false });
  window.canvas = canvas;

  // State
  window.areas = [];         // [{ rect, outline, base:{w,h}, template_id }]
  window.activeArea = null;

  // Attach events to a rect so form ↔ canvas sync happens
  function attachRectEvents(rect){
    function sync(){
      if (window.activeArea !== rect) return;
      const rw = rect.getScaledWidth();
      const rh = rect.getScaledHeight();
      x_mm.value = Math.round(px2mm(rect.left));
      y_mm.value = Math.round(px2mm(rect.top));
      width_mm.value  = Math.round(px2mm(rw));
      height_mm.value = Math.round(px2mm(rh));
      rect.setCoords();
      if (window.updateOutline) window.updateOutline(rect);
    }
    rect.on('selected', ()=> { window.activeArea = rect; sync(); });
    rect.on('moving',  sync);
    rect.on('scaling', sync);
    rect.on('modified',sync);

    // number edits → rect
    ['x_mm','y_mm','width_mm','height_mm'].forEach(id=>{
      document.getElementById(id).addEventListener('change',()=>{
        if (window.activeArea !== rect) return;
        const vW = mm2px(parseFloat(width_mm.value||0));
        const vH = mm2px(parseFloat(height_mm.value||0));
        rect.set({
          left:mm2px(parseFloat(x_mm.value||0)),
          top:mm2px(parseFloat(y_mm.value||0)),
          scaleX:1, scaleY:1, width:vW, height:vH
        });
        rect.setCoords();
        if (window.updateOutline) window.updateOutline(rect);
        canvas.requestRenderAll();
      });
    });
  }

  // Public creator (used by template apply & by loader)
  window.createRect = function(leftPx, topPx, wPx, hPx){
    const rect = new fabric.Rect({
      left:leftPx, top:topPx, width:wPx, height:hPx,
      fill:'transparent', stroke:'red', strokeWidth:2,
      lockRotation:true, cornerSize:10, lockUniScaling:true
    });
    canvas.add(rect);
    attachRectEvents(rect);
    window.activeArea = rect;
    canvas.setActiveObject(rect);
    rect.setCoords();
    canvas.requestRenderAll();
    return rect;
  };

  // Background image
  const bgUrl = @json($bgUrl);
  const wrapW = document.getElementById('canvasWrap').clientWidth || 960;
  const MAX_BG_W = 700;

  function ready(){
  // Render existing areas from DB
  (window.existingAreas || []).forEach(a=>{
    let left, top, wpx, hpx;

    if (a.left_pct !== null && a.width_pct !== null) {
      // use % values
      const W = canvas.getWidth();
      const H = canvas.getHeight();
      left = (a.left_pct/100) * W;
      top  = (a.top_pct/100) * H;
      wpx  = (a.width_pct/100) * W;
      hpx  = (a.height_pct/100) * H;
    } else {
      // fallback to mm
      left = mm2px(a.x_mm);
      top  = mm2px(a.y_mm);
      wpx  = mm2px(a.width_mm);
      hpx  = mm2px(a.height_mm);
    }

    const rect = window.createRect(left, top, wpx, hpx);
    rect._dbId = a.id;
    rect.__clientKey = 'loaded_' + a.id;
    rect._maskPath = a.mask_svg_path || null;

    if (a.mask_svg_path) {
      const svgUrl = `/files/${a.mask_svg_path}`;
      fabric.loadSVGFromURL(svgUrl, (objs, opts)=>{
        const svg = fabric.util.groupSVGElements(objs, opts);
        rect._shapeBase = { w: svg.width, h: svg.height };

        forceOutlineOnly(svg, '#444');
        svg.set({
          originX:'left', originY:'top',
          left: rect.left, top: rect.top,
          selectable:false, evented:false
        });
        rect._outline = svg;
        canvas.add(svg);
        if (window.updateOutline) window.updateOutline(rect);
      });
    }

    window.areas.push({
      rect,
      outline: rect._outline || null,
      base: rect._shapeBase || null,
      template_id: a.template_id
    });
  });

  canvas.requestRenderAll();
}

  // ===== Robust background loader: try multiple candidates and pick first that loads =====
  (function(){
    // candidates from server (bgUrl) + fallback to product->thumbnail or view->bg_image_url if present
    const serverBg = @json($bgUrl);
    const productThumb = @json($product->thumbnail ?? null);
    const viewBg = @json($view->bg_image_url ?? null);

    // normalize a path -> try to build /files/ and /storage/ variants too
    function buildCandidates(p){
      if (!p) return [];
      p = String(p);
      // if it's an absolute URL, return it as is
      if (/^https?:\/\//i.test(p)) return [p];
      // remove leading slashes
      p = p.replace(/^\/+/, '');
      return [
        '/files/' + p,          // Laravel route that serves storage directly (recommended)
        '/storage/' + p,        // public symlink
        p                       // raw relative (best-effort)
      ];
    }

    // merge candidates: server provided first, then view/product fallbacks
    let candidates = [];
    if (serverBg) {
      if (/^https?:\/\//i.test(serverBg)) candidates.push(serverBg);
      else {
        // if server gave a path like "/files/..." or "/storage/...", keep it first
        candidates.push(serverBg);
        // also add normalized variants just in case
        const norm = serverBg.replace(/^\/+/, '');
        candidates = candidates.concat(buildCandidates(norm));
      }
    }
    candidates = candidates.concat(buildCandidates(viewBg)).concat(buildCandidates(productThumb));

    // dedupe while preserving order
    candidates = candidates.filter((v,i)=> v && candidates.indexOf(v) === i);

    // debug: show resolved candidates in console and small debug panel (visible in page)
    console.log('bg candidates:', candidates);
    const dbgWrap = document.querySelector('.hint');
    if (dbgWrap) {
      const dbgBox = document.createElement('div');
      dbgBox.style.fontSize = '12px';
      dbgBox.style.marginTop = '6px';
      dbgBox.innerHTML = '<strong>BG candidates:</strong> ' + candidates.map(c=>`<a href="${c}" target="_blank" style="margin-right:6px">${c}</a>`).join('');
      dbgWrap.appendChild(dbgBox);
    }

    // try to load each candidate sequentially
    function tryLoadList(list, idx){
      if (!list || idx >= list.length) {
        // all failed -> fallback color
        canvas.setWidth(wrapW);
        canvas.setHeight(Math.round(wrapW*0.7));
        canvas.setBackgroundColor('#f6f7fb', canvas.renderAll.bind(canvas));
        ready();
        return;
      }

      const url = list[idx] + '?v=' + ({{ isset($view) && $view->updated_at ? strtotime($view->updated_at) : (isset($product) && $product->updated_at ? strtotime($product->updated_at) : time()) }});
      const img = new Image();
      // remove crossOrigin for same-origin /files or /storage to avoid taint issues
      try { img.crossOrigin = undefined; } catch(e){}

      img.onload = function(){
        // good — set canvas size and background
        let scale = 1;
        const maxW = Math.min(wrapW, MAX_BG_W);
        if (img.width > maxW) scale = maxW / img.width;

        const w = Math.round(img.width * scale);
        const h = Math.round(img.height * scale);
        canvas.setWidth(w);
        canvas.setHeight(h);

        const bg = new fabric.Image(img, { selectable:false, evented:false });
        bg.scale(scale);
        canvas.setBackgroundImage(bg, canvas.renderAll.bind(canvas));
        ready();
      };

      img.onerror = function(){
        console.warn('bg load failed for', url);
        // try next candidate
        tryLoadList(list, idx + 1);
      };

      // start loading
      img.src = url;
    }

    // start attempts
    tryLoadList(candidates, 0);
  })();

  // Keep outline in sync with rect
  window.updateOutline = function(rect){
    if (!rect || !rect._outline || !rect._shapeBase) return;
    const w = rect.getScaledWidth();
    const h = rect.getScaledHeight();
    const scale = Math.min(w / rect._shapeBase.w, h / rect._shapeBase.h);
    rect._outline.set({ left: rect.left, top: rect.top, scaleX: scale, scaleY: scale });
    canvas.requestRenderAll();
  };

  // Toolbar: delete / clear
  document.getElementById('btnDeleteArea')?.addEventListener('click', ()=>{
    const rect = window.activeArea;
    if (!rect) return;
    if (rect._outline) canvas.remove(rect._outline);
    canvas.remove(rect);
    window.areas = window.areas.filter(a => a.rect !== rect);
    window.activeArea = null;
    canvas.discardActiveObject();
    canvas.requestRenderAll();
  });

  document.getElementById('btnClearAreas')?.addEventListener('click', ()=>{
    window.areas.forEach(a=>{
      if (a.outline) canvas.remove(a.outline);
      canvas.remove(a.rect);
    });
    window.areas = [];
    window.activeArea = null;
    canvas.discardActiveObject();
    canvas.requestRenderAll();
  });

})();
</script>

<script>
/* ============ TEMPLATE MODAL + APPLY (outline only) ============ */
const tplModal  = document.getElementById('tplModal');
const tplList   = document.getElementById('tplList');
const tplSearch = document.getElementById('tplSearch');
const tplClose  = document.getElementById('tplClose');
const apiUrl    = `{{ route('admin.decoration.search') }}`;

async function openTplModal(){
  try { await loadTpl(''); tplModal.style.display = 'flex'; }
  catch(e){ console.error(e); alert('Failed to load templates.'); }
}
window.openTplModal = openTplModal;

tplClose.addEventListener('click', ()=> tplModal.style.display='none');
tplModal.addEventListener('click', (e)=>{ if(e.target===tplModal) tplModal.style.display='none'; });
tplSearch.addEventListener('input', ()=> { loadTpl(tplSearch.value).catch(console.error); });

async function loadTpl(q=''){
  const url = q ? `${apiUrl}?q=${encodeURIComponent(q)}` : apiUrl;
  const res = await fetch(url, { headers:{'Accept':'application/json'} });
  if(!res.ok) throw new Error('API error');
  const items = await res.json();

  if (!Array.isArray(items) || !items.length){
    tplList.innerHTML = `<div class="list-group-item">No templates. Click “+ Manage”.</div>`;
    return true;
  }

  const groups = {regular:[], custom:[], without_bleed:[]};
  items.forEach(i => (groups[i.category]||(groups[i.category]=[])).push(i));

  const block = (t,arr)=> arr.length ? `<div class="mb-2">
    <div class="fw-bold mb-1">${t}</div>
    ${arr.map(i=>{
  const hasName = i.name && i.name.trim().length;
  const label   = hasName ? i.name.trim() : `${i.width_mm}×${i.height_mm} mm`;
  const sizeStr = `(${i.width_mm}×${i.height_mm} mm)`;
  const badge   = i.slot_key ? `<span class="badge bg-secondary ms-2">${i.slot_key}</span>` : '';
  const svgUrl  = i.svg_path ? `/files/${i.svg_path}` : '';

  return `<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
      data-id="${i.id}" data-name="${hasName ? i.name.trim() : ''}" data-w="${i.width_mm}" data-h="${i.height_mm}"
      data-svg="${svgUrl}">
      <span>${label} <small class="text-muted">${sizeStr}</small> ${badge}</span>
      ${svgUrl ? '<small class="text-muted">SVG</small>' : ''}
    </a>`;
}).join('')}

  </div>` : '';

  tplList.innerHTML = block('REGULAR',groups.regular) + block('CUSTOM',groups.custom) + block('WITHOUT BLEED',groups.without_bleed);

  tplList.querySelectorAll('a').forEach(a=>{
    a.onclick = (e)=>{
      e.preventDefault();
      const tpl = {
        id:a.dataset.id, name:a.dataset.name,
        width_mm:+a.dataset.w, height_mm:+a.dataset.h,
        svg_url:a.dataset.svg || null
      };
      applyTemplate(tpl);
      tplModal.style.display='none';
    };
  });
  return true;
}

// Make any SVG outline-only (no fill)
function forceOutlineOnly(target, stroke='#444') {
  if (!target) return;
  const setOutline = (o)=>{
    if ('fill' in o) o.fill = 'transparent';
    o.stroke = stroke;
    o.strokeWidth = 2;
    o.strokeUniform = true;
  };
  if (Array.isArray(target)) {
    target.forEach(o=>{ setOutline(o); if (o._objects) forceOutlineOnly(o._objects, stroke); });
  } else {
    setOutline(target);
    if (target._objects) forceOutlineOnly(target._objects, stroke);
  }
}

function applyTemplate(tpl){
  const canvas = window.canvas;
  if (!canvas) return;

  // size & center
  let wpx = window.mm2px(tpl.width_mm);
  let hpx = window.mm2px(tpl.height_mm);
  const MIN_PX = 20; if (wpx < MIN_PX) wpx = MIN_PX; if (hpx < MIN_PX) hpx = MIN_PX;

  let left = Math.max(0, (canvas.getWidth()  - wpx)/2);
  let top  = Math.max(0, (canvas.getHeight() - hpx)/2);

  // 1) rect
  const rect = window.createRect(left, top, wpx, hpx);
  rect.__clientKey = (Date.now() + '_' + Math.random()).replace('.','');
  rect._maskPath = tpl.svg_url ? tpl.svg_url.replace('{{ asset('storage') }}/','') : null;

  // 2) outline from SVG (optional)
  if (tpl.svg_url){
    fabric.loadSVGFromURL(tpl.svg_url, (objs, opts) => {
      const svg = fabric.util.groupSVGElements(objs, opts);
      rect._shapeBase = { w: svg.width, h: svg.height };
      forceOutlineOnly(svg, '#444');
      svg.set({ originX:'left', originY:'top', left: rect.left, top: rect.top, selectable:false, evented:false });
      rect._outline = svg;
      canvas.add(svg);
      if (window.updateOutline) window.updateOutline(rect);

      window.areas.push({ rect, outline: svg, base: rect._shapeBase, template_id: tpl.id || null });
    });
  } else {
    window.areas.push({ rect, outline:null, base:null, template_id: tpl.id || null });
  }

  // reflect in form (optional)
  document.getElementById('width_mm').value  = tpl.width_mm;
  document.getElementById('height_mm').value = tpl.height_mm;
  document.getElementById('template_id').value = tpl.id || '';
  document.getElementById('mask_svg_path').value = rect._maskPath || '';
}
</script>

<script>
/* ================= SAVE ALL ================= */
document.getElementById('btnSaveAll')?.addEventListener('click', async (e) => {
  e.preventDefault();    // <-- GET navigation தடுக்கிறது
  e.stopPropagation();

  const areas = (window.areas || []).map(a => {
    const r = a.rect;
    const rw = r.getScaledWidth();
    const rh = r.getScaledHeight();

    // RECT -> % (canvas width/height க்கு normalize செய்யலாம்; அல்லது நீங்கள் நேரே % வைத்திருந்தா அதையே போடலாம்)
    const W = window.canvas.getWidth();
    const H = window.canvas.getHeight();
    const left_pct   = (r.left / W) * 100;
    const top_pct    = (r.top  / H) * 100;
    const width_pct  = (rw / W) * 100;
    const height_pct = (rh / H) * 100;

    return {
      id: r._dbId || null,
      template_id: a.template_id || null,
      mask_svg_path: r._maskPath || null,
      left_pct, top_pct, width_pct, height_pct,
      rotation: 0
    };
  });

  const url = `{{ route('admin.areas.bulk', [$product->id,$view->id]) }}`;

  try {
    const res = await fetch(url, {
      method: 'POST',                                            // <-- POST
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': `{{ csrf_token() }}`                     // <-- CSRF
      },
      body: JSON.stringify({
        stage_w: window.canvas?.getWidth?.() || null,
        stage_h: window.canvas?.getHeight?.() || null,
        areas
      })
    });

    if (!res.ok) {
      const txt = await res.text();
      console.error('Save failed response:', txt);
      alert('Save failed. Check console.');
      return;
    }
    alert('Saved!');
  } catch (err) {
    console.error(err);
    alert('Save failed. Check console.');
  }
});

</script>
@endpush