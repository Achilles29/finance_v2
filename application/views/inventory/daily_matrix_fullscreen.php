<?php
$divisions = is_array($divisions ?? null) ? $divisions : [];
$canWarehouse = !empty($can_warehouse);
$canMaterial = !empty($can_material);
$canComponent = !empty($can_component);
$initialMonth = (string)($initial_month ?? date('Y-m'));
$requestedDivisionId = (int)$this->input->get('division_id', true);
$requestedScope = strtolower(trim((string)$this->input->get('scope', true)));
$requestedSource = strtolower(trim((string)$this->input->get('source', true)));
$selectedDivisionId = 0;
foreach ($divisions as $division) if ((int)($division['id'] ?? 0) === $requestedDivisionId) $selectedDivisionId = $requestedDivisionId;
if ($selectedDivisionId <= 0 && !empty($divisions)) $selectedDivisionId = (int)($divisions[0]['id'] ?? 0);
$selectedScope = !$canMaterial && !$canComponent && $canWarehouse
    ? 'warehouse'
    : ($requestedScope === 'warehouse' && $canWarehouse
        ? 'warehouse'
        : ($selectedDivisionId > 0 || !empty($divisions) ? 'division' : ($canWarehouse ? 'warehouse' : 'division')));
$selectedSource = !$canMaterial && !$canComponent
    ? 'warehouse'
    : (in_array($requestedSource, ['material', 'component'], true) ? $requestedSource : ($canMaterial ? 'material' : 'component'));
if (($selectedSource === 'material' && !$canMaterial) || ($selectedSource === 'component' && !$canComponent)) $selectedSource = $canMaterial ? 'material' : 'component';
?>
<style>
  .daily-matrix-workspace{--dm-maroon:#681629;display:flex;flex-direction:column;height:100%;min-height:0;background:#fbf3e9}
  .dm-toolbar{display:flex;align-items:center;gap:.7rem;padding:.42rem .85rem;color:#fff;background:radial-gradient(circle at 92% -80%,#ffffff42,transparent 25rem),linear-gradient(110deg,#491323,#681629 55%,#8d2535);overflow:hidden}
  .dm-brand{display:flex;align-items:center;gap:.55rem;flex:0 0 auto;min-width:235px;order:1}.dm-brand-mark{display:grid;place-items:center;width:34px;height:34px;border:1px solid #ffffff55;border-radius:10px;background:#ffffff1a;font-size:1rem}
  .dm-brand h1{margin:0;color:#fff !important;font-size:.92rem;font-weight:900;white-space:nowrap}.dm-brand p{margin:.04rem 0 0;color:#f3d9d5;font-size:.66rem;white-space:nowrap}
  .dm-tabs-area{display:flex;align-items:center;gap:.45rem;flex:1 1 auto;min-width:0;padding:0;background:transparent;border:0;overflow-x:auto;scrollbar-width:thin;order:2}.dm-location-tabs,.dm-source-tabs{display:flex;align-items:center;gap:.35rem;overflow:visible;scrollbar-width:thin;flex:0 0 auto}
  .dm-location-tabs{min-width:0}.dm-source-tabs{padding-left:.45rem;border-left:1px solid #ffffff55}
  .dm-toolbar-actions{display:flex;align-items:center;justify-content:flex-end;gap:.35rem;flex:0 0 auto;flex-wrap:nowrap;order:3}.dm-toolbar .btn{min-height:32px;border-radius:9px;font-weight:800;padding:.3rem .55rem}.dm-month{width:145px;border:1px solid #ffffff55}
  .dm-tab{display:inline-flex;align-items:center;gap:.32rem;flex:0 0 auto;border:1px solid #e8d5c9;border-radius:999px;padding:.3rem .58rem;background:#fff;color:#68424a;font-size:.7rem;font-weight:850;cursor:pointer;transition:.15s;white-space:nowrap}
  .dm-tab:hover{transform:translateY(-1px);border-color:#f5c4b4}.dm-tab.is-active{background:#fff;border-color:#fff;color:#681629;box-shadow:0 4px 10px #21091455}.dm-tab .dm-tab-code{opacity:.7;font-size:.6rem;letter-spacing:.07em}.dm-source-tab.is-active{background:#c3253b;border-color:#c3253b;color:#fff}
  .dm-frame-wrap{position:relative;flex:1;min-height:0;background:#fff}.dm-frame-loading{position:absolute;inset:0;display:grid;place-items:center;z-index:3;color:#79545a;background:#fffaf5;font-size:.86rem;font-weight:800;pointer-events:none}.dm-frame-loading[hidden]{display:none}.dm-frame{position:relative;z-index:2;display:block;width:100%;height:100%;border:0;background:#fff}
  @media(max-width:1100px){.dm-brand{min-width:190px}.dm-brand p{display:none}.dm-toolbar-actions .btn:not(#dmRefresh){font-size:0}.dm-toolbar-actions .btn:not(#dmRefresh) .ri{margin:0!important}.dm-month{width:125px}}
  @media(max-width:640px){.dm-toolbar{padding:.4rem .55rem;gap:.45rem}.dm-brand{min-width:38px}.dm-brand h1{display:none}.dm-toolbar-actions{gap:.25rem}.dm-toolbar .btn{padding:.3rem .42rem;font-size:.7rem}.dm-month{width:112px}}
</style>
<section class="daily-matrix-workspace" id="dailyMatrixWorkspace"
 data-warehouse-url="<?php echo html_escape((string)$warehouse_url); ?>" data-material-url="<?php echo html_escape((string)$material_url); ?>" data-component-url="<?php echo html_escape((string)$component_url); ?>"
 data-can-warehouse="<?php echo $canWarehouse ? '1' : '0'; ?>" data-can-material="<?php echo $canMaterial ? '1' : '0'; ?>" data-can-component="<?php echo $canComponent ? '1' : '0'; ?>"
 data-selected-scope="<?php echo html_escape($selectedScope); ?>" data-selected-division="<?php echo $selectedDivisionId; ?>" data-selected-source="<?php echo html_escape($selectedSource); ?>">
  <header class="dm-toolbar">
    <div class="dm-brand"><span class="dm-brand-mark"><i class="ri ri-table-2"></i></span><div><h1>Daily Inventory Matrix</h1><p>Semua divisi, satu layar. Telusuri pergerakan stok per hari.</p></div></div>
    <div class="dm-toolbar-actions">
      <a class="btn btn-sm btn-outline-light" href="<?php echo html_escape((string)$back_url); ?>" id="dmBack"><i class="ri ri-arrow-left-line me-1"></i>Kembali</a>
      <a class="btn btn-sm btn-light" href="<?php echo html_escape((string)$home_url); ?>"><i class="ri ri-home-4-line me-1"></i>Beranda</a>
      <label for="dmMonth" class="visually-hidden">Bulan matrix</label><input type="month" class="form-control form-control-sm dm-month" id="dmMonth" value="<?php echo html_escape($initialMonth); ?>">
      <button type="button" class="btn btn-sm btn-outline-light" id="dmRefresh" title="Muat ulang matrix"><i class="ri ri-refresh-line"></i></button>
    </div>
    <div class="dm-tabs-area">
    <nav class="dm-location-tabs" aria-label="Pilih divisi atau lokasi">
      <?php if ($canWarehouse): ?><button type="button" class="dm-tab<?php echo $selectedScope === 'warehouse' ? ' is-active' : ''; ?>" data-scope="warehouse" aria-pressed="<?php echo $selectedScope === 'warehouse' ? 'true' : 'false'; ?>"><i class="ri ri-archive-drawer-line"></i><span>STOREROOM</span><span class="dm-tab-code">GUDANG</span></button><?php endif; ?>
      <?php foreach ($divisions as $division): ?><?php $divisionId = (int)($division['id'] ?? 0); $divisionCode = trim((string)($division['code'] ?? '')); $divisionName = trim((string)($division['name'] ?? '')); ?>
        <button type="button" class="dm-tab<?php echo $selectedScope === 'division' && $selectedDivisionId === $divisionId ? ' is-active' : ''; ?>" data-scope="division" data-division-id="<?php echo $divisionId; ?>" data-division-name="<?php echo html_escape($divisionName); ?>" aria-pressed="<?php echo $selectedScope === 'division' && $selectedDivisionId === $divisionId ? 'true' : 'false'; ?>"><i class="ri ri-building-4-line"></i><span><?php echo html_escape($divisionCode !== '' ? $divisionCode : $divisionName); ?></span></button>
      <?php endforeach; ?>
    </nav>
    <nav class="dm-source-tabs" aria-label="Pilih jenis matrix" id="dmSourceTabs">
      <?php if ($canMaterial): ?><button type="button" class="dm-tab dm-source-tab<?php echo $selectedSource === 'material' ? ' is-active' : ''; ?>" data-source="material" aria-pressed="<?php echo $selectedSource === 'material' ? 'true' : 'false'; ?>"><i class="ri ri-seedling-line"></i>Bahan Baku</button><?php endif; ?>
      <?php if ($canComponent): ?><button type="button" class="dm-tab dm-source-tab<?php echo $selectedSource === 'component' ? ' is-active' : ''; ?>" data-source="component" aria-pressed="<?php echo $selectedSource === 'component' ? 'true' : 'false'; ?>"><i class="ri ri-flask-line"></i>Component / Base / Prepare</button><?php endif; ?>
    </nav>
    </div>
  </header>
  <div class="dm-frame-wrap"><div class="dm-frame-loading" id="dmFrameLoading"><span><i class="ri ri-loader-4-line ri-spin me-2"></i>Menyiapkan matrix...</span></div><iframe class="dm-frame" id="dmMatrixFrame" title="Daily Inventory Matrix"></iframe></div>
</section>
<script>
(() => {
  const root=document.getElementById('dailyMatrixWorkspace'),frame=document.getElementById('dmMatrixFrame'),month=document.getElementById('dmMonth'),loading=document.getElementById('dmFrameLoading');
  if(!root||!frame||!month)return;
  const state={scope:root.dataset.selectedScope||'division',divisionId:Number(root.dataset.selectedDivision||0),source:root.dataset.selectedSource||'material'};
  const allowed={warehouse:root.dataset.canWarehouse==='1',material:root.dataset.canMaterial==='1',component:root.dataset.canComponent==='1'};
  const tabs=Array.from(root.querySelectorAll('.dm-tab'));
  function paintTabs(){tabs.forEach(tab=>{const active=tab.dataset.scope?tab.dataset.scope===state.scope&&(state.scope!=='division'||Number(tab.dataset.divisionId||0)===state.divisionId):tab.dataset.source===state.source;tab.classList.toggle('is-active',active);tab.setAttribute('aria-pressed',active?'true':'false')});document.getElementById('dmSourceTabs').style.display=state.scope==='warehouse'?'none':''}
  function loadMatrix(){let base;if(state.scope==='warehouse'){base=root.dataset.warehouseUrl;state.source='warehouse'}else{if(!allowed[state.source])state.source=allowed.material?'material':'component';base=state.source==='component'?root.dataset.componentUrl:root.dataset.materialUrl}
    const url=new URL(base,window.location.href);url.searchParams.set('matrix_fullscreen','1');url.searchParams.set('month',month.value||new Date().toISOString().slice(0,7));
    if(state.scope==='division'){url.searchParams.set('division_id',String(state.divisionId));url.searchParams.set('limit','1000');url.searchParams.set('per_page','100')}
    loading.hidden=false;frame.src=url.toString();paintTabs();const current=new URL(window.location.href);current.searchParams.set('month',month.value);current.searchParams.set('scope',state.scope);current.searchParams.set('source',state.source);if(state.scope==='division')current.searchParams.set('division_id',String(state.divisionId));else current.searchParams.delete('division_id');window.history.replaceState({},'',current.toString())}
  tabs.forEach(tab=>tab.addEventListener('click',()=>{if(tab.dataset.scope==='warehouse'){state.scope='warehouse';state.source='warehouse'}else if(tab.dataset.scope==='division'){state.scope='division';state.divisionId=Number(tab.dataset.divisionId||0);if(state.source==='warehouse'||!allowed[state.source])state.source=allowed.material?'material':'component'}else if(tab.dataset.source){state.source=tab.dataset.source;state.scope='division'}loadMatrix()}));
  month.addEventListener('change',loadMatrix);document.getElementById('dmRefresh').addEventListener('click',loadMatrix);document.getElementById('dmBack').addEventListener('click',event=>{if(window.history.length>1){event.preventDefault();window.history.back()}});frame.addEventListener('load',()=>{loading.hidden=true});frame.addEventListener('error',()=>{loading.innerHTML='<span>Matrix gagal dimuat. Periksa koneksi lalu tekan muat ulang.</span>'});loadMatrix();
})();
</script>
