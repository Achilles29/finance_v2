<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$filters = $filters ?? ['q' => '', 'status' => 'ACTIVE'];
$labels = $labels ?? [];
$edit = $edit_row ?? null;
$tableReady = !empty($table_ready);
$formMode = !empty($form_mode);
$autoPrint = !empty($print_auto);
$isEditing = !empty($edit['id']);
$labelTemplates = is_array($label_templates ?? null) ? $label_templates : [];
$selectedTemplate = is_array($selected_template ?? null) ? $selected_template : [];
$selectedTemplateId = (int)($selectedTemplate['id'] ?? 0);
$selectedTemplateKey = (string)($selectedTemplate['template_key'] ?? 'classic-portrait');
$applyTemplate = !empty($apply_template);
$isUniversalTemplate = false; // Ke depan semua template memakai editor + mesin cetak yang sama.
$canSave = $isEditing ? !empty($can_edit) : !empty($can_create);
$imagePath = trim((string)($edit['image_path'] ?? ''));
$imageUrl = $imagePath !== '' ? base_url($imagePath) : '';
$logoPath = trim((string)($edit['logo_path'] ?? ''));
$profileLogoUrl = trim((string)($business_profile['logo_url'] ?? ''));
$defaultLogoUrl = is_file(FCPATH . 'assets/uploads/logo.png') ? base_url('assets/uploads/logo.png')
    : ($profileLogoUrl !== '' ? $profileLogoUrl : base_url('assets/img/business-placeholder.svg'));
$logoUrl = $logoPath !== '' ? base_url($logoPath) : $defaultLogoUrl;
$storedDesignJson = (string)($edit['design_json'] ?? '{}');
$designJson = (string)($editor_design_json ?? ($storedDesignJson !== '' ? $storedDesignJson : ($selectedTemplate['design_json'] ?? '{}')));
$designData = json_decode($storedDesignJson, true);
$designData = is_array($designData) ? $designData : [];
$editorDesignData = json_decode($designJson, true);
$editorDesignData = is_array($editorDesignData) ? $editorDesignData : [];
$editorCanvas = is_array($editorDesignData['canvas'] ?? null) ? $editorDesignData['canvas'] : [];
$canvasWidth = max(40, min(160, (int)($editorCanvas['width'] ?? ($edit['canvas_width_mm'] ?? 90))));
$canvasHeight = max(60, min(240, (int)($editorCanvas['height'] ?? ($edit['canvas_height_mm'] ?? 140))));
$designMeta = is_array($designData['meta'] ?? null) ? $designData['meta'] : [];
$roastLevelOptions = ['Light', 'Light - Medium', 'Medium', 'Medium - Dark', 'Dark', 'Omni Roast', 'Espresso Roast', 'Filter Roast'];
$bodyLevelOptions = ['Light', 'Light - Medium', 'Medium', 'Medium - Full', 'Full'];
$selectedRoastLevel = (string)($edit['roast_level'] ?? 'Medium');
$selectedBodyLevel = (string)($edit['body_level'] ?? ($designMeta['body_level'] ?? 'Light - Medium'));
if ($selectedRoastLevel !== '' && !in_array($selectedRoastLevel, $roastLevelOptions, true)) {
    $roastLevelOptions[] = $selectedRoastLevel;
}
if ($selectedBodyLevel !== '' && !in_array($selectedBodyLevel, $bodyLevelOptions, true)) {
    $bodyLevelOptions[] = $selectedBodyLevel;
}
$selectedElevation = (string)($edit['elevation_text'] ?? ($designMeta['elevation_text'] ?? ($designMeta['elevation'] ?? '')));
$selectedBeanType = (string)($edit['bean_type'] ?? ($designMeta['bean_type'] ?? 'Whole Bean'));
$selectedFooterNote = (string)($edit['footer_note'] ?? ($designMeta['footer_note'] ?? ''));
$selectedRibbonText = (string)($designMeta['ribbon_text'] ?? ($selectedFooterNote !== '' ? $selectedFooterNote : 'Single Origin'));
$selectedLabelName = trim((string)($edit['label_name'] ?? ($edit['coffee_name'] ?? '')));
$selectedProductId = (int)($edit['product_id'] ?? 0);
$badgeLogoPath = trim((string)($designMeta['badge_logo_path'] ?? ''));
$badgeLogoUrl = $badgeLogoPath !== '' ? base_url($badgeLogoPath) : $logoUrl;
$themePreset = (string)($editorCanvas['theme'] ?? ($edit['theme_preset'] ?? 'heritage-cream'));
$designCanvas = $editorCanvas;
$artworkMode = (string)($designCanvas['artworkMode'] ?? 'full');
if (!in_array($artworkMode, ['full', 'rounded', 'circle', 'arch'], true)) {
    $artworkMode = 'full';
}
$artworkFit = (string)($designCanvas['artworkFit'] ?? 'stretch');
if (!in_array($artworkFit, ['stretch', 'contain', 'cover'], true)) {
    $artworkFit = 'stretch';
}
$patternMode = (string)($designCanvas['patternMode'] ?? 'contour');
if (!in_array($patternMode, ['contour', 'speckles', 'diagonal', 'grid', 'waves', 'sunburst', 'none'], true)) {
    $patternMode = 'contour';
}
$productOptions = is_array($product_options ?? null) ? $product_options : [];
$artworkGallery = is_array($artwork_gallery ?? null) ? $artwork_gallery : [];
$logoGallery = is_array($logo_gallery ?? null) ? $logo_gallery : [];
$currentStatus = strtoupper((string)($filters['status'] ?? 'ACTIVE'));
$statusTabs = [
    'ACTIVE' => 'Aktif',
    'INACTIVE' => 'Nonaktif',
    'ALL' => 'Semua',
];
$editorQuery = $isEditing ? ['edit' => (int)$edit['id']] : ['new' => 1];
$namuaRoastersLogoUrl = (is_file(FCPATH . 'assets/roastery/logo 2.png') ? base_url('assets/roastery/logo%202.png') : $logoUrl);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cormorant+Garamond:wght@600;700&family=Fraunces:opsz,wght@9..144,600;9..144,800;9..144,900&family=Jost:wght@400;600;800&family=Libre+Baskerville:wght@400;700&family=Space+Grotesk:wght@500;700&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

<style>
.coffee-label-page{--red:#a70f25;--gold:#d6a84d;--brown:#432115}
.coffee-hero{border:0;border-radius:28px;overflow:hidden;color:#fff;background:radial-gradient(circle at 12% 14%,rgba(255,255,255,.22),transparent 24%),radial-gradient(circle at 90% 20%,rgba(214,168,77,.38),transparent 28%),linear-gradient(135deg,#2b140c,#8e1827 58%,#b66e2f);box-shadow:0 22px 45px rgba(60,28,16,.2)}
.coffee-hero h3{font-family:'Playfair Display',serif;font-weight:900}.hero-kicker{letter-spacing:.23em;text-transform:uppercase;color:#ffe3a1;font-size:.72rem;font-weight:800}
.coffee-chip{border-radius:999px;padding:.45rem .75rem;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);font-size:.8rem;font-weight:700}
.label-workbench{display:grid;grid-template-columns:minmax(370px,1fr) minmax(420px,540px);gap:1.1rem;align-items:start}
.label-panel{border:1px solid rgba(167,15,37,.12);border-radius:22px;background:rgba(255,255,255,.94);box-shadow:0 16px 40px rgba(70,40,25,.1);overflow:hidden}
.label-panel-head{padding:1rem 1.15rem;border-bottom:1px solid rgba(167,15,37,.12);background:linear-gradient(135deg,#fff,#fff7eb);display:flex;justify-content:space-between;align-items:center;gap:1rem}.label-panel-head h5{margin:0;color:#3b1c14;font-weight:900}
.label-panel-body{padding:.95rem}.label-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.68rem}.label-tools{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.68rem;align-items:start}.full{grid-column:1/-1}
.label-form-grid label,.label-tools label{font-size:.72rem;font-weight:900;color:#6b3a2a;letter-spacing:.04em;text-transform:uppercase}
.form-control,.form-select{border-radius:13px}.range-line{display:grid;grid-template-columns:72px 1fr 44px;gap:.5rem;align-items:center}.range-line small{font-weight:800;color:#795548;line-height:1.15}.range-line output{font-size:.72rem;color:var(--red);font-weight:900;text-align:right}.range-line input{accent-color:var(--red)}
.label-product-picker{display:grid;grid-template-columns:minmax(180px,.8fr) minmax(220px,1.2fr);gap:.65rem}.label-product-picker .form-select,.label-product-picker .form-control{min-height:46px}
.label-section-title{grid-column:1/-1;display:flex;align-items:center;gap:.55rem;margin:.35rem 0 -.15rem;color:#4a2116;font-weight:950}.label-section-title:after{content:"";height:1px;flex:1;background:linear-gradient(90deg,rgba(167,15,37,.22),transparent)}
.label-section-title i{width:28px;height:28px;border-radius:10px;display:grid;place-items:center;background:#fff0df;color:#a70f25}
.toggle-row{display:flex;flex-wrap:wrap;gap:.45rem}.toggle-row .btn{border-radius:999px;font-weight:800}
.label-preview-card{position:sticky;top:82px}.preview-shell{min-height:640px;display:grid;place-items:center;padding:1.15rem;background:radial-gradient(circle at center,#fff6e6,#efe2d3);border-radius:20px;overflow:auto}
.preview-shell.show-cut-line .label-canvas{outline:2px dashed rgba(40,24,18,.55);outline-offset:8px}
.label-canvas{width:var(--label-preview-w,360px);height:var(--label-preview-h,560px);max-width:100%;position:relative;overflow:hidden;border-radius:18px;background:#f7ecd9;color:#2c1711;box-shadow:0 24px 50px rgba(44,23,17,.28),inset 0 0 0 1px rgba(255,255,255,.45);isolation:isolate;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.label-canvas:before{content:"";position:absolute;inset:14px;border:1px solid rgba(214,168,77,.58);border-radius:13px;z-index:3;pointer-events:none}.label-canvas:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.12),transparent 32%,rgba(67,33,18,.2));z-index:2;pointer-events:none}
.label-bg{position:absolute;inset:0;z-index:1;background:radial-gradient(circle at 30% 20%,#fff8ea,#dfc195)}.label-bg img{width:100%;height:100%;object-fit:fill;object-position:center;filter:saturate(1.05) contrast(1.03)}.label-canvas.artwork-fit-contain .label-bg img{object-fit:contain}.label-canvas.artwork-fit-cover .label-bg img{object-fit:cover}.label-canvas.artwork-fit-stretch .label-bg img{object-fit:fill}.label-bg.no-image:before{content:"PNG LABEL ARTWORK";position:absolute;inset:18px;border:1px dashed rgba(95,48,25,.28);border-radius:14px;display:grid;place-items:center;color:rgba(95,48,25,.5);font-weight:900;letter-spacing:.14em}
.label-overlay{position:absolute;inset:0;z-index:4;background:radial-gradient(circle at 50% 34%,rgba(255,246,219,.78),rgba(255,246,219,.18) 23%,transparent 42%),linear-gradient(180deg,rgba(26,14,10,.12),rgba(44,20,14,.05) 30%,rgba(20,11,10,.84));pointer-events:none}.theme-midnight-roast .label-overlay{background:radial-gradient(circle at 50% 34%,rgba(255,232,183,.34),rgba(255,232,183,.08) 23%,transparent 42%),linear-gradient(180deg,rgba(13,9,10,.2),rgba(13,9,10,.22) 38%,rgba(13,9,10,.94))}.theme-clean-white .label-overlay{background:radial-gradient(circle at 50% 35%,rgba(255,255,255,.95),rgba(255,255,255,.42) 28%,transparent 48%),linear-gradient(180deg,rgba(255,255,255,.7),rgba(255,255,255,.14) 46%,rgba(255,255,255,.82))}
.label-watermark{position:absolute;z-index:5;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-12deg);font-family:'Bebas Neue',sans-serif;font-size:84px;color:rgba(255,255,255,.10);letter-spacing:.08em;white-space:nowrap}.label-mark{position:absolute;z-index:6;left:18px;top:18px;padding:6px 10px;border:1px solid rgba(255,226,168,.38);border-radius:999px;font-size:9px;letter-spacing:.18em;font-weight:900;color:#fff6d6;background:rgba(38,22,18,.34);backdrop-filter:blur(5px)}
.label-brand-panel{position:absolute;z-index:5;left:24px;right:24px;top:24px;height:44%;border-radius:22px;background:radial-gradient(circle at 50% 55%,rgba(255,244,219,.9),rgba(255,244,219,.38) 40%,rgba(255,255,255,.08) 72%);border:1px solid rgba(255,229,174,.22);box-shadow:0 20px 44px rgba(25,12,9,.2),inset 0 0 0 1px rgba(255,255,255,.18);pointer-events:none}.label-brand-panel:after{content:"";position:absolute;left:19px;right:19px;bottom:13px;height:1px;background:linear-gradient(90deg,transparent,rgba(255,223,157,.45),transparent)}
.label-sensory-panel{position:absolute;z-index:5;left:24px;right:24px;bottom:24px;min-height:30%;border-radius:22px;background:linear-gradient(145deg,rgba(23,16,20,.90),rgba(60,23,43,.82) 48%,rgba(14,31,61,.86));border:1px solid rgba(255,225,169,.28);box-shadow:0 18px 34px rgba(17,8,9,.3);pointer-events:none}.theme-clean-white .label-sensory-panel{background:linear-gradient(145deg,rgba(255,255,255,.92),rgba(247,231,202,.88));border-color:rgba(128,53,32,.18)}
.label-orbit{position:absolute;z-index:6;border:1px solid rgba(255,220,145,.28);border-radius:50%;pointer-events:none}.label-orbit.o1{width:54%;aspect-ratio:1;left:23%;top:13%;box-shadow:0 0 0 26px rgba(255,220,145,.035)}.label-orbit.o2{width:22%;aspect-ratio:1;right:10%;bottom:13%}.label-orbit.o3{width:12%;aspect-ratio:1;left:9%;bottom:25%}.label-speckles{position:absolute;inset:0;z-index:6;pointer-events:none;background-image:radial-gradient(circle,rgba(255,228,166,.55) 0 1.2px,transparent 1.8px),radial-gradient(circle,rgba(255,255,255,.32) 0 .8px,transparent 1.4px);background-size:38px 48px,64px 72px;background-position:7px 11px,22px 29px;mix-blend-mode:screen;opacity:.7}
.label-meta-grid{position:absolute;z-index:6;left:34px;right:34px;bottom:34px;display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-top:1px solid rgba(255,226,168,.22);border-bottom:1px solid rgba(255,226,168,.14);pointer-events:none}.label-meta-grid span{min-height:32px;border-right:1px solid rgba(255,226,168,.14)}.label-meta-grid span:last-child{border-right:0}
.label-logo{position:absolute;z-index:8;cursor:move;object-fit:contain;filter:drop-shadow(0 5px 10px rgba(49,21,13,.16));touch-action:none;user-select:none}.label-logo.active{outline:0;background:transparent}
.label-text{position:absolute;z-index:7;min-height:14px;cursor:move;padding:2px 5px;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.08;text-shadow:0 1px 0 rgba(255,255,255,.16);touch-action:none;user-select:none}.label-text.active{outline:2px solid rgba(255,193,7,.9);background:rgba(255,193,7,.12)}
.gallery-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:.55rem;max-height:154px;overflow:auto;padding:.25rem}.gallery-tile{border:1px solid rgba(167,15,37,.15);border-radius:14px;padding:.25rem;background:#fff7ec;cursor:pointer;text-align:left}.gallery-tile img{width:100%;height:70px;object-fit:cover;border-radius:10px}.gallery-tile span{display:block;margin-top:.25rem;font-size:.65rem;color:#6b3a2a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gallery-tile.active{border-color:#a70f25;box-shadow:0 0 0 2px rgba(167,15,37,.12)}
.label-index-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.summary-tile{border:1px solid rgba(167,15,37,.1);border-radius:18px;background:linear-gradient(135deg,#fff,#fff7ec);padding:.85rem 1rem;display:flex;align-items:center;gap:.75rem}.summary-tile i{width:40px;height:40px;border-radius:14px;display:grid;place-items:center;background:#a70f25;color:#fff;font-size:1.15rem}.summary-tile small{display:block;color:#8a6a55;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.summary-tile b{display:block;color:#321711;font-size:1.15rem}
.saved-card{border:1px solid rgba(167,15,37,.1);border-radius:18px;padding:.85rem;background:#fff;box-shadow:0 8px 22px rgba(70,40,25,.06);display:flex;align-items:flex-start;gap:.8rem;min-width:0;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}.saved-card:hover{transform:translateY(-2px);border-color:rgba(167,15,37,.24);box-shadow:0 16px 34px rgba(70,40,25,.1)}.saved-card .flex-grow-1{min-width:0}.saved-card .badge{flex:none;white-space:nowrap}.saved-thumb{width:68px;height:92px;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#f7e5c6,#d9b66d);flex:none}.saved-thumb img{width:100%;height:100%;object-fit:cover}
.label-status-tabs{display:flex;flex-wrap:wrap;gap:.45rem;padding:.35rem;border:1px solid rgba(167,15,37,.1);border-radius:999px;background:#fff8ed;box-shadow:inset 0 0 0 1px rgba(255,255,255,.65)}
.label-status-tab{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem .9rem;border-radius:999px;color:#6b3a2a;text-decoration:none;font-weight:900;font-size:.82rem;border:1px solid transparent;transition:.18s ease}
.label-status-tab:hover{color:#a70f25;background:#fff;border-color:rgba(167,15,37,.16)}
.label-status-tab.active{color:#fff;background:linear-gradient(135deg,#a70f25,#c74a33);box-shadow:0 8px 18px rgba(167,15,37,.18)}
.saved-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:.45rem;margin-top:.9rem;align-items:stretch}
.saved-actions .btn{width:100%;min-width:0;min-height:34px;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;border-radius:12px;font-weight:900;white-space:normal;line-height:1.12;text-align:center;overflow-wrap:anywhere;padding:.42rem .52rem}
.saved-actions .btn i{flex:none;font-size:1rem;line-height:1}
.saved-actions .btn-wide{grid-column:auto}
.label-index-head{gap:.9rem}.label-index-controls{min-width:0;max-width:100%}.label-index-filter{min-width:0}.label-index-filter .form-control{min-width:0!important;flex:1 1 220px}.label-index-controls>.btn{white-space:nowrap}
.coffee-label-page .label-canvas{border-radius:30px;background:linear-gradient(135deg,#a72d43 4%,#74233b 28%,#d05843 64%,#f3a35f 100%);box-shadow:0 30px 65px rgba(67,38,24,.26),inset 0 0 0 1px rgba(255,255,255,.62)}
.coffee-label-page .label-canvas:before{inset:0;border:1px solid rgba(255,247,236,.68);border-radius:30px;box-shadow:inset 0 0 0 1px rgba(119,78,45,.06)}
.coffee-label-page .label-canvas:after{background:
radial-gradient(circle at 18% 16%,rgba(255,255,255,.15),transparent 24%),
radial-gradient(circle at 84% 78%,rgba(255,203,140,.12),transparent 24%),
repeating-radial-gradient(circle at 20% 20%,rgba(255,255,255,.08) 0 1px,transparent 1px 8px),
linear-gradient(135deg,rgba(255,255,255,.08),transparent 36%,rgba(86,29,33,.12) 100%);
z-index:2}
.coffee-label-page .label-canvas.pattern-mode-contour:after{background:
radial-gradient(circle at 18% 16%,rgba(255,255,255,.15),transparent 24%),
radial-gradient(circle at 84% 78%,rgba(255,203,140,.12),transparent 24%),
repeating-radial-gradient(circle at 20% 20%,rgba(255,255,255,.085) 0 1px,transparent 1px 8px),
linear-gradient(135deg,rgba(255,255,255,.08),transparent 36%,rgba(86,29,33,.12) 100%)}
.coffee-label-page .label-canvas.pattern-mode-speckles:after{background:
radial-gradient(circle at 18% 16%,rgba(255,255,255,.12),transparent 24%),
radial-gradient(circle,rgba(255,236,188,.36) 0 1px,transparent 1.7px),
radial-gradient(circle,rgba(255,255,255,.22) 0 .7px,transparent 1.3px),
linear-gradient(135deg,rgba(255,255,255,.06),transparent 36%,rgba(86,29,33,.12) 100%);background-size:auto,28px 32px,46px 52px,auto;background-position:0 0,7px 11px,22px 29px,0 0}
.coffee-label-page .label-canvas.pattern-mode-diagonal:after{background:
radial-gradient(circle at 18% 16%,rgba(255,255,255,.12),transparent 24%),
repeating-linear-gradient(135deg,rgba(255,244,220,.12) 0 1px,transparent 1px 9px),
linear-gradient(135deg,rgba(255,255,255,.07),transparent 36%,rgba(86,29,33,.12) 100%)}
.coffee-label-page .label-canvas.pattern-mode-grid:after{background:
radial-gradient(circle at 18% 16%,rgba(255,255,255,.10),transparent 24%),
linear-gradient(rgba(255,244,220,.10) 1px,transparent 1px),
linear-gradient(90deg,rgba(255,244,220,.08) 1px,transparent 1px),
linear-gradient(135deg,rgba(255,255,255,.06),transparent 36%,rgba(86,29,33,.12) 100%);background-size:auto,22px 22px,22px 22px,auto}
.coffee-label-page .label-canvas.pattern-mode-waves:after{background:
radial-gradient(circle at 84% 78%,rgba(255,203,140,.12),transparent 24%),
repeating-radial-gradient(ellipse at 12% 78%,rgba(255,244,220,.12) 0 1px,transparent 1px 11px),
repeating-radial-gradient(ellipse at 90% 18%,rgba(255,255,255,.07) 0 1px,transparent 1px 14px),
linear-gradient(135deg,rgba(255,255,255,.06),transparent 36%,rgba(86,29,33,.12) 100%)}
.coffee-label-page .label-canvas.pattern-mode-sunburst:after{background:
radial-gradient(circle at 52% 34%,rgba(255,235,185,.18),transparent 26%),
repeating-conic-gradient(from -18deg at 52% 34%,rgba(255,244,220,.12) 0deg 2deg,transparent 2deg 9deg),
linear-gradient(135deg,rgba(255,255,255,.07),transparent 36%,rgba(86,29,33,.12) 100%)}
.coffee-label-page .label-canvas.pattern-mode-none:after{background:
linear-gradient(135deg,rgba(255,255,255,.05),transparent 38%,rgba(86,29,33,.10) 100%)}
.coffee-label-page .label-bg{background:linear-gradient(135deg,#a72d43 4%,#74233b 28%,#d05843 64%,#f3a35f 100%)}
.coffee-label-page .label-bg.no-image:before{content:"";inset:0;border:0;border-radius:0;background:linear-gradient(135deg,#a72d43 4%,#74233b 28%,#d05843 64%,#f3a35f 100%)}
.coffee-label-page .label-bg.no-image:after{content:"";position:absolute;inset:0;background:repeating-radial-gradient(circle at 20% 24%,rgba(255,255,255,.08) 0 1px,transparent 1px 8px);opacity:.9}
.coffee-label-page .label-bg img{filter:saturate(1.06) contrast(1.03)}
.coffee-label-page .label-bg{transition:all .28s ease}
.coffee-label-page .label-canvas.artwork-mode-full .label-bg{inset:0;border-radius:0;overflow:hidden;box-shadow:none}
.coffee-label-page .label-canvas.artwork-mode-rounded .label-bg{inset:4.5% 6%;border-radius:26px;overflow:hidden;box-shadow:0 22px 44px rgba(65,20,28,.26), inset 0 0 0 1px rgba(255,255,255,.38)}
.coffee-label-page .label-canvas.artwork-mode-circle .label-bg{left:13%;right:13%;top:6.5%;bottom:auto;width:74%;height:auto;aspect-ratio:1/1;border-radius:999px;overflow:hidden;box-shadow:0 22px 44px rgba(65,20,28,.26), inset 0 0 0 1px rgba(255,255,255,.38)}
.coffee-label-page .label-canvas.artwork-mode-arch .label-bg{left:10%;right:10%;top:5.5%;bottom:auto;width:80%;height:54%;border-radius:42% 42% 20px 20px / 26% 26% 20px 20px;overflow:hidden;box-shadow:0 22px 44px rgba(65,20,28,.26), inset 0 0 0 1px rgba(255,255,255,.38)}
.coffee-label-page .label-canvas.artwork-mode-rounded .label-bg.no-image:before,
.coffee-label-page .label-canvas.artwork-mode-circle .label-bg.no-image:before,
.coffee-label-page .label-canvas.artwork-mode-arch .label-bg.no-image:before{border-radius:inherit}
.coffee-label-page .label-canvas.artwork-mode-rounded .label-bg.no-image:after,
.coffee-label-page .label-canvas.artwork-mode-circle .label-bg.no-image:after,
.coffee-label-page .label-canvas.artwork-mode-arch .label-bg.no-image:after{border-radius:inherit}
.coffee-label-page .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.02) 26%,rgba(58,9,18,.12) 100%);}
.coffee-label-page .label-brand-panel,
.coffee-label-page .label-sensory-panel,
.coffee-label-page .label-watermark,
.coffee-label-page .label-mark,
.coffee-label-page .label-orbit,
.coffee-label-page .label-speckles{display:none}
.coffee-label-page .label-meta-grid{left:52px;right:52px;bottom:56px;border:1px solid rgba(68,38,24,.30);border-radius:15px;overflow:hidden;background:rgba(255,249,235,.13)}
.coffee-label-page .label-meta-grid span{min-height:44px;border-right:1px solid rgba(68,38,24,.20)}
.coffee-label-page .label-logo-main{filter:drop-shadow(0 12px 20px rgba(105,28,30,.18));background:transparent!important}
.coffee-label-page .label-badge-logo{filter:drop-shadow(0 6px 12px rgba(84,21,24,.16));background:transparent!important}
.coffee-label-page .label-text{text-shadow:none;line-height:1.05}
.coffee-label-page .label-text[data-block="origin"],.coffee-label-page .label-text[data-block="process_method"],.coffee-label-page .label-text[data-block="roast_level"],.coffee-label-page .label-text[data-block="weight_text"],.coffee-label-page .label-text[data-block="batch_no"],.coffee-label-page .label-text[data-block="roast_date"],.coffee-label-page .label-text[data-block="expiry_date"],.coffee-label-page .label-text[data-block="description"]{display:none}
.label-side-ribbon{position:absolute;z-index:8;left:2.1%;top:.8%;width:8.5%;height:21.5%;border-radius:0 0 16px 16px;border:1px solid rgba(255,244,236,.72);background:rgba(77,14,26,.52);backdrop-filter:blur(2px);display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:4px 0 10px;color:#fff6ea;cursor:move;touch-action:none;user-select:none;--ribbon-text-size:9px;--ribbon-icon-size:18px;--ribbon-letter-spacing:4.2}
.label-side-ribbon span{writing-mode:vertical-rl;transform:rotate(180deg);font-family:'Space Grotesk',sans-serif;font-size:var(--ribbon-text-size);letter-spacing:calc(var(--ribbon-letter-spacing) * 1px);text-transform:uppercase;font-weight:700}
.label-side-ribbon i,.label-side-ribbon svg{font-size:var(--ribbon-icon-size)}
.label-side-ribbon svg,.taste-icon-row svg{width:1em;height:1em;display:block;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round}
.label-side-ribbon.active{outline:2px dashed rgba(167,15,37,.72);outline-offset:4px}
.label-roastery-kicker{position:absolute;z-index:8;left:20%;right:20%;top:38.5%;text-align:center;font-family:'Space Grotesk',sans-serif;font-size:8px;letter-spacing:.4em;text-transform:uppercase;color:#fff3ea;font-weight:800;white-space:normal;line-height:1.35}
.coffee-label-page .label-text[data-block="coffee_name"]{color:#fff5ea;text-shadow:0 2px 8px rgba(87,14,27,.18)}
.coffee-label-page .label-text[data-block="brew_suggestion"]{color:#fff3ea;font-weight:800!important;letter-spacing:.35em!important;text-transform:uppercase}
.coffee-label-page .label-text[data-block="tasting_notes"]{display:none!important}
.label-info-panel{position:absolute;z-index:8;left:10.5%;right:10.5%;bottom:4.8%;border:1px solid rgba(128,59,52,.55);border-radius:18px;overflow:hidden;background:rgba(255,244,236,.84);color:#6e2b2d;font-family:'Space Grotesk',sans-serif;font-size:6.5px;letter-spacing:.03em;backdrop-filter:blur(4px)}
.label-info-panel.active{outline:2px dashed rgba(167,15,37,.72);outline-offset:4px}
.label-info-panel[data-block]{cursor:move;touch-action:none;user-select:none}
.label-info-panel .info-top{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid rgba(67,37,24,.28)}
.label-info-panel .info-cell{padding:4px 7px 5px;min-height:33px}.label-info-panel .info-cell:first-child{border-right:1px solid rgba(67,37,24,.28)}
.label-info-panel .info-title{display:block;font-weight:900;text-transform:uppercase;letter-spacing:.11em;margin-bottom:3px;line-height:1.15;font-size:.82em}
.label-info-panel .info-value{display:block;font-size:1.2em;letter-spacing:.03em;margin-bottom:3px;line-height:1.1}
.label-info-panel .info-dots{display:flex;gap:.45em}.label-info-panel .info-dot{width:.95em;height:.95em;border-radius:50%;border:1px solid currentColor}.label-info-panel .info-dot.filled{background:currentColor}
.label-info-panel .info-bottom{display:grid;gap:2px;padding:5px 7px;border-bottom:1px solid rgba(107,37,46,.26)}.label-info-panel .info-line{display:grid;grid-template-columns:auto 1fr auto 1fr;gap:4px 6px;align-items:baseline}.label-info-panel .info-line.info-line-origin{grid-template-columns:auto minmax(0,1fr)}.label-info-panel .info-line.info-line-origin span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.94em}.label-info-panel .info-line.info-line-dual{grid-template-columns:auto minmax(0,1.35fr) auto minmax(0,.85fr)}.label-info-panel .info-line b{font-weight:900;text-transform:uppercase;letter-spacing:.10em;min-width:0;line-height:1.15;font-size:.82em}.label-info-panel .info-line span{font-family:'Jost',sans-serif;font-size:1em;letter-spacing:.03em;line-height:1.16;min-width:0}
.label-info-panel .info-date-row{display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid rgba(67,37,24,.22)}.label-info-panel .info-date-cell{min-height:26px;padding:4px 5px 5px;border-right:1px solid rgba(67,37,24,.18)}.label-info-panel .info-date-cell:last-child{border-right:0}.label-info-panel .info-date-cell b{display:block;font-weight:900;text-transform:uppercase;letter-spacing:.10em;font-size:.8em;margin-bottom:2px;line-height:1.1}.label-info-panel .info-date-cell span{display:block;min-height:9px;font-family:'Space Grotesk',sans-serif;font-size:.93em;letter-spacing:.05em;line-height:1.12;word-break:break-word}
.label-pack-footer{position:absolute;z-index:7;left:17%;right:17%;bottom:6%;display:flex;justify-content:space-between;align-items:center;color:#6f2c30;font-family:'Space Grotesk',sans-serif;font-size:8px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.label-info-panel .label-pack-footer{position:static;left:auto;right:auto;bottom:auto;min-height:22px;padding:6px 8px 7px;font-size:1em;line-height:1.15}
.taste-icon-row{position:absolute;z-index:8;left:16%;right:16%;top:54.1%;display:flex;align-items:flex-start;justify-content:center;gap:calc(var(--taste-icon-size,18px) * 1.05);color:#fff6ea;font-size:13px;--taste-icon-size:18px}
.taste-icon-row span{min-width:58px;display:flex;flex-direction:column;align-items:center;gap:5px}
.taste-icon-row b{width:calc(var(--item-icon-size,var(--taste-icon-size,18px)) * 2);height:calc(var(--item-icon-size,var(--taste-icon-size,18px)) * 2);border-radius:999px;display:grid;place-items:center;border:1px solid rgba(255,241,232,.72);background:rgba(255,255,255,.05);font-size:var(--item-icon-size,var(--taste-icon-size,18px))}
.taste-icon-row b svg{width:1.08em;height:1.08em;stroke-width:1.8}
.taste-icon-row small{display:block;font-family:'Space Grotesk',sans-serif;font-size:var(--item-text-size,var(--taste-text-size,6.8px))!important;font-weight:800;letter-spacing:.16em;line-height:1.25;text-transform:uppercase;text-align:center;max-width:68px}
.label-watermark[data-block],.label-mark[data-block],.label-roastery-kicker[data-block],.taste-icon-row[data-block],.label-side-ribbon[data-block]{cursor:move;pointer-events:auto;touch-action:none;user-select:none}
.label-watermark.active,.label-mark.active,.label-roastery-kicker.active,.taste-icon-row.active{outline:2px dashed rgba(167,15,37,.72);outline-offset:4px;border-radius:12px}
.drag-guides{position:absolute;inset:0;z-index:30;pointer-events:none;opacity:0;transition:opacity .12s ease}
.drag-guides.is-active{opacity:1}
.drag-guide-line{position:absolute;background:rgba(255,247,237,.92);box-shadow:0 0 0 1px rgba(121,39,50,.18)}
.drag-guide-line.guide-v{top:0;bottom:0;width:1px}
.drag-guide-line.guide-h{left:0;right:0;height:1px}
.drag-guide-line.guide-center{background:rgba(255,214,130,.88);box-shadow:0 0 0 1px rgba(121,39,50,.12)}
.drag-guide-line.is-snap{background:#ffd36d;box-shadow:0 0 0 1px rgba(121,39,50,.22),0 0 12px rgba(255,211,109,.55)}
.drag-guide-badge{position:absolute;left:10px;top:10px;padding:4px 8px;border-radius:999px;background:rgba(74,17,27,.82);color:#fff8ee;font:700 10px/1 'Space Grotesk',sans-serif;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 10px 18px rgba(48,16,22,.18)}
.taste-builder{border:1px solid rgba(167,15,37,.12);border-radius:16px;background:linear-gradient(135deg,#fffaf3,#fff3e5);padding:.75rem;display:grid;gap:.55rem}
.taste-row{display:grid;grid-template-columns:minmax(0,1fr) 190px 76px 76px 38px;gap:.5rem;align-items:center;position:relative}
.taste-row .form-control,.taste-row .form-select{border-radius:11px}
.taste-size-input{display:grid;gap:.18rem}
.taste-size-input span{font-size:.58rem;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:#8a6a55;line-height:1}
.taste-size-input input{padding:.38rem .45rem;text-align:center}
.taste-icon-picker{position:relative}
.taste-icon-toggle{width:100%;min-height:38px;border:1px solid rgba(167,15,37,.16);border-radius:11px;background:#fff;color:#4d2418;display:flex;align-items:center;justify-content:space-between;gap:.45rem;padding:.38rem .55rem;font-weight:850}
.taste-icon-toggle span{display:inline-flex;align-items:center;gap:.45rem;min-width:0}.taste-icon-toggle i{font-size:1.06rem;color:#a70f25}.taste-icon-toggle em{font-style:normal;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.taste-icon-menu{display:none;position:absolute;z-index:45;top:calc(100% + 6px);left:0;right:auto;width:min(300px,calc(100vw - 2rem));max-height:320px;overflow:auto;padding:.35rem;border:1px solid rgba(167,15,37,.14);border-radius:14px;background:#fff;box-shadow:0 16px 32px rgba(70,32,20,.18)}
.taste-icon-picker.open .taste-icon-menu{display:grid;gap:.25rem}
.taste-icon-option{border:0;background:transparent;border-radius:10px;padding:.45rem .5rem;text-align:left;display:flex;align-items:center;gap:.55rem;color:#4d2418;font-weight:780}
.taste-icon-option:hover,.taste-icon-option.active{background:#fff0df;color:#a70f25}.taste-icon-option i{width:22px;text-align:center;font-size:1.05rem}.taste-icon-option span{line-height:1.2}
.taste-icon-preview{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#fff;border:1px solid rgba(167,15,37,.16);color:#6b3a2a}
.label-print-settings{border:1px solid rgba(167,15,37,.12);border-radius:16px;background:linear-gradient(135deg,#fffaf3,#fff4e6);padding:.85rem;display:grid;gap:.7rem}
.print-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;align-items:end}
.print-check{min-height:46px;border:1px solid rgba(167,15,37,.13);border-radius:13px;background:#fff;display:flex;align-items:center;gap:.55rem;padding:.55rem .75rem;font-weight:850;color:#4d2418}
.print-check input{accent-color:#a70f25}
.print-sheet{display:none}
.print-sheet-portal{display:none}
.taste-text-control{transition:opacity .16s ease}
.description-card{border:1px solid rgba(167,15,37,.12);border-radius:16px;background:#fffaf3;padding:.75rem}
.description-card textarea{min-height:86px}
.description-hint{font-size:.72rem;color:#8a6a55;margin-top:.35rem}
.label-meta-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-bottom:.75rem}.label-meta-form .full{grid-column:1/-1}
@media(max-width:768px){.label-product-picker,.print-grid{grid-template-columns:1fr 1fr}}
@media(max-width:576px){.label-meta-form{grid-template-columns:1fr}.taste-row{grid-template-columns:minmax(0,1fr) 1fr 1fr 38px}.taste-icon-picker{grid-column:1/-1}.print-grid,.label-product-picker{grid-template-columns:1fr}}
.coffee-label-page .theme-midnight-roast{background:linear-gradient(135deg,#120d0c,#3a1e26 42%,#d36b47 72%,#182946)}
.coffee-label-page .theme-midnight-roast .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.04),transparent 38%,rgba(44,24,15,.05))}
.coffee-label-page .theme-clean-white{background:linear-gradient(135deg,#fffaf0,#f7e5c2 48%,#d8c7a6)}
.coffee-label-page .theme-clean-white .label-bg.no-image:before{background:linear-gradient(135deg,#fffaf0,#f7e5c2 48%,#d8c7a6)}
.coffee-label-page .theme-clean-white .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.04),transparent 38%,rgba(44,24,15,.05))}
.coffee-label-page .theme-porcelain-mist{background:linear-gradient(135deg,#fffdf7,#f3efe3 42%,#dfe8d8 74%,#c9dacb)}
.coffee-label-page .theme-porcelain-mist .label-bg.no-image:before{background:radial-gradient(circle at 25% 18%,rgba(255,255,255,.78),transparent 26%),linear-gradient(135deg,#fffdf7,#f3efe3 42%,#dfe8d8 74%,#c9dacb)}
.coffee-label-page .theme-porcelain-mist .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.08),transparent 42%,rgba(255,255,255,.16))}
.coffee-label-page .theme-sakura-cream{background:linear-gradient(135deg,#fff8ed,#ffe4d3 45%,#f7b9a1 72%,#ecd1bd)}
.coffee-label-page .theme-sakura-cream .label-bg.no-image:before{background:radial-gradient(circle at 25% 22%,rgba(255,255,255,.62),transparent 27%),linear-gradient(135deg,#fff8ed,#ffe4d3 45%,#f7b9a1 72%,#ecd1bd)}
.coffee-label-page .theme-sakura-cream .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.06),transparent 42%,rgba(70,36,24,.04))}
.coffee-label-page .theme-oat-paper{background:linear-gradient(135deg,#fbf3df,#efe0bf 48%,#d8c19a)}
.coffee-label-page .theme-oat-paper .label-bg.no-image:before{background:linear-gradient(135deg,#fbf3df,#efe0bf 48%,#d8c19a)}
.coffee-label-page .theme-oat-paper .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.08),transparent 42%,rgba(90,58,31,.06))}
.coffee-label-page .theme-matcha-sunrise{background:linear-gradient(135deg,#fff4d9,#f7cf9c 42%,#bdc69b 73%,#7f9678)}
.coffee-label-page .theme-matcha-sunrise .label-bg.no-image:before{background:linear-gradient(135deg,#fff4d9,#f7cf9c 42%,#bdc69b 73%,#7f9678)}
.coffee-label-page .theme-matcha-sunrise .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.05),transparent 40%,rgba(47,70,46,.06))}
.coffee-label-page .label-canvas.artwork-mode-full .label-bg img{filter:saturate(1.06) contrast(1.03)}
.coffee-label-page .label-canvas.artwork-mode-full .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.02) 26%,rgba(58,9,18,.12) 100%)}
.coffee-label-page .label-canvas.artwork-mode-full:before{border-color:rgba(255,247,236,.68);box-shadow:inset 0 0 0 1px rgba(119,78,45,.06)}
.coffee-label-page .label-canvas.artwork-mode-full.pattern-mode-contour:after{background:
radial-gradient(circle at 18% 16%,rgba(255,255,255,.15),transparent 24%),
radial-gradient(circle at 84% 78%,rgba(255,203,140,.12),transparent 24%),
repeating-radial-gradient(circle at 20% 20%,rgba(255,255,255,.08) 0 1px,transparent 1px 8px),
linear-gradient(135deg,rgba(255,255,255,.08),transparent 36%,rgba(86,29,33,.12) 100%)}
@media(max-width:768px){.label-index-summary{grid-template-columns:1fr}.summary-tile{padding:.75rem}.saved-thumb{width:58px;height:82px}}
@media(max-width:768px){.label-index-controls,.label-index-filter,.label-index-controls>.btn{width:100%}.label-index-filter .btn{flex:1 1 auto}.label-index-filter .form-control{flex-basis:100%}.label-status-tabs{width:100%;border-radius:18px}.label-status-tab{flex:1;justify-content:center}}
@media(max-width:576px){.saved-card{gap:.65rem;padding:.7rem}.saved-actions{grid-template-columns:1fr}.label-status-tabs{border-radius:18px}.label-status-tab{flex:1;justify-content:center}}.saved-title{font-weight:900;color:#511c18}
.label-template-bar{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:.7rem;padding:.82rem;border:1px solid rgba(167,15,37,.18);border-radius:15px;background:linear-gradient(135deg,#fffaf2,#fff)}
.label-template-select-label{margin-bottom:.32rem;color:#5d211b;font-size:.78rem;font-weight:900}.label-template-select{min-height:42px;border-color:rgba(167,15,37,.24);font-weight:800;color:#4d2418;background-color:#fff}.label-template-select:focus{border-color:#a70f25;box-shadow:0 0 0 .2rem rgba(167,15,37,.12)}.label-template-help{display:block;margin-top:.34rem;color:#8c7068;font-size:.7rem;line-height:1.35}.label-template-save{min-height:42px;white-space:nowrap;border-radius:11px;font-size:.76rem;font-weight:850}
.label-template-modal{position:fixed;z-index:1090;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(36,20,16,.48);backdrop-filter:blur(3px)}.label-template-modal[hidden]{display:none}.label-template-dialog{width:min(100%,470px);padding:1.25rem;border:1px solid rgba(167,15,37,.2);border-radius:20px;background:#fffaf5;box-shadow:0 24px 60px rgba(54,22,17,.35)}.label-template-dialog h5{margin:0;color:#5d211b;font-weight:900}.label-template-dialog p{margin:.35rem 0 1rem;color:#80665c;font-size:.8rem;line-height:1.45}.label-template-dialog .form-label{color:#5d211b;font-size:.78rem;font-weight:850}.label-template-dialog .required-mark{color:#a70f25}.label-template-dialog textarea{resize:vertical}.label-template-dialog-actions{display:flex;justify-content:flex-end;gap:.55rem;margin-top:1rem}
.element-toggle-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:.45rem}.element-toggle{display:flex;align-items:center;justify-content:space-between;gap:.4rem;border:1px solid rgba(103,56,35,.17);border-radius:11px;padding:.46rem .55rem;background:#fff;color:#5a281b;font-size:.72rem;font-weight:850;text-align:left}.element-toggle.is-hidden{opacity:.55;background:#fbf5ee;color:#876f65}.element-toggle i{font-size:1rem;color:#a70f25}.element-toggle.is-hidden i{color:#8c7068}.editor-drag-hint{font-size:.72rem;color:#87654f;background:#fff6e9;border:1px dashed rgba(167,15,37,.25);padding:.55rem .65rem;border-radius:10px}
.studio-only{display:contents}.coffee-label-page.is-universal-editor .studio-only{display:none!important}.universal-label-preview{display:none}.coffee-label-page.is-universal-editor .universal-label-preview{display:block}.coffee-label-page.is-universal-editor #labelCanvas{display:none!important}.coffee-label-page.is-universal-editor .preview-shell{min-height:500px}
.namua-label{position:relative;isolation:isolate;width:100mm;min-height:68mm;max-width:100%;overflow:hidden;color:#40101c;background:#d9b37b;box-shadow:0 25px 45px rgba(56,25,27,.25);print-color-adjust:exact;-webkit-print-color-adjust:exact}.namua-label__artwork{position:absolute;inset:0;z-index:0;overflow:hidden;background:linear-gradient(135deg,#5b0d1d 0%,#a93443 48%,#edb26e 100%)}.namua-label__artwork img{display:block;width:100%;height:100%;object-fit:cover}.namua-label__artwork:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(255,249,231,.46) 0%,rgba(255,239,208,.12) 48%,rgba(255,244,220,.46) 100%)}.namua-label__artwork.no-image{background:linear-gradient(135deg,#701126 0%,#a42c3c 45%,#ebad68 100%)}.namua-label__rail{position:absolute;inset:0 auto 0 0;z-index:1;width:18mm;background:rgba(70,10,27,.94)}.namua-label__logo-shell{display:grid;width:11.5mm;aspect-ratio:1;margin:7.5mm auto 0;padding:1.2mm;place-items:center;border:1px solid rgba(255,228,174,.72);border-radius:50%;background:rgba(255,246,226,.96)}.namua-label__logo-shell img{display:block;width:100%;height:100%;object-fit:contain}.namua-label__side-rule{width:8mm;height:1px;margin:3.5mm auto 0;background:rgba(255,228,174,.72)}.namua-label__side-copy{position:absolute;inset:26mm 2.2mm 4.5mm;display:flex;flex-direction:column;color:#ffe6b4}.namua-label__side-series{padding:3px 0;border-top:1px solid rgba(255,228,174,.48);border-bottom:1px solid rgba(255,228,174,.48);font-size:4.7px;font-weight:700;letter-spacing:.1em;line-height:1.35;overflow-wrap:anywhere;text-align:center;text-transform:uppercase}.namua-label__side-motto{margin:4px 0 auto;color:#e7b86f;font-size:4.1px;font-weight:700;letter-spacing:.13em;line-height:1.55;text-align:center;text-transform:uppercase}.namua-label__side-meta{display:grid;gap:3px;margin-top:4px}.namua-label__side-meta-item{padding-top:3px;border-top:1px solid rgba(255,228,174,.32)}.namua-label__side-meta-label{display:block;color:#f0bc72;font-size:4.1px;font-weight:700;letter-spacing:.12em;line-height:1.15;text-transform:uppercase}.namua-label__side-meta-value{display:block;margin-top:1px;color:#fff0cd;font-size:5.2px;font-weight:700;line-height:1.25;overflow-wrap:anywhere;text-transform:uppercase}.namua-label__content{position:relative;z-index:2;isolation:isolate;display:flex;flex-direction:column;min-height:58mm;margin:5mm 5mm 5mm 22mm;padding:0;color:#4a1421;text-shadow:0 1px 8px rgba(255,249,229,.86)}.namua-label__content:before{content:"";position:absolute;inset:-9px -14px;z-index:-1;pointer-events:none;background:radial-gradient(ellipse at 32% 35%,rgba(255,250,234,.76) 0%,rgba(255,248,227,.42) 47%,rgba(255,248,227,0) 78%);filter:blur(6px)}.namua-label__title{max-width:100%;margin:0 0 5px;color:#48111f;font-family:'Playfair Display',serif;font-size:27px;font-weight:900;letter-spacing:-.055em;line-height:.9;overflow-wrap:anywhere;text-transform:uppercase}.namua-label__origin-stack{display:grid;gap:3px}.namua-label__origin,.namua-label__elevation{display:flex;align-items:flex-start;gap:4px;max-width:100%;color:#5e1e2b;font-size:7px;font-weight:700;letter-spacing:.045em;line-height:1.35;text-transform:uppercase}.namua-label__origin i{margin-top:1px;color:#8c1830;font-size:8px}.namua-label__elevation i{margin-top:1px;color:#8c1830;font-size:7px}.namua-label__notes{display:flex;flex-wrap:wrap;gap:4px;margin:9px 0 0}.namua-label__note{max-width:100%;padding:3px 5px;border:1px solid rgba(82,18,33,.48);border-radius:999px;background:rgba(255,249,232,.42);color:#571724;font-size:5.8px;font-weight:700;letter-spacing:.08em;line-height:1.2;overflow-wrap:anywhere;text-transform:uppercase}.namua-label__trace{display:flex;flex-wrap:wrap;gap:3px 8px;margin-top:auto;padding:5px 6px;border:1px solid rgba(255,229,180,.46);background:rgba(61,7,22,.78);color:#fff2d1;font-family:monospace;font-size:5px;line-height:1.25;text-shadow:none}.namua-label__trace strong{color:#f4ca85;font-weight:700}.namua-label__trace-item.is-empty,.namua-label__origin.is-empty,.namua-label__elevation.is-empty,.namua-label__side-meta-item.is-empty{display:none}
.namua-label__note{display:inline-flex;align-items:center;gap:2px}.namua-label__note-icon{display:inline-flex;width:7px;height:7px;flex:none;align-items:center;justify-content:center}.namua-label__note-icon .taste-svg-icon{display:block;width:100%;height:100%;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
#universalPrintPortal{display:none}
@media(max-width:1180px){.label-workbench{grid-template-columns:1fr}.label-preview-card{position:static}.label-form-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.label-tools{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:768px){.label-form-grid,.label-tools,.label-template-bar{grid-template-columns:1fr}.label-template-save{width:100%}}
@page{size:A4 portrait;margin:0}
@media print{body.coffee-label-printing{margin:0!important;background:#fff!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}body.coffee-label-printing *{print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}body.coffee-label-printing .layout-wrapper,body.coffee-label-printing .layout-menu,body.coffee-label-printing .layout-navbar,body.coffee-label-printing .content-footer{display:none!important}body.coffee-label-printing #printSheetPortal{display:grid!important;position:absolute!important;left:0!important;top:0!important;width:var(--print-paper-w,210mm)!important;height:var(--print-paper-h,297mm)!important;margin:0!important;padding:var(--print-margin,8mm)!important;gap:var(--print-gap,4mm)!important;grid-template-columns:repeat(var(--print-cols,2),var(--label-print-w,90mm));grid-auto-rows:var(--label-print-h,140mm);align-content:start;justify-content:center;box-sizing:border-box;background:#fff!important;page-break-after:auto!important;break-after:auto!important}body.coffee-label-printing #printSheetPortal .print-label-slot{position:relative;width:var(--label-print-w,90mm);height:var(--label-print-h,140mm);break-inside:avoid}body.coffee-label-printing #printSheetPortal .print-label-slot.cut-line:before{content:"";position:absolute;inset:-1.5mm;border:.25mm dashed #222;z-index:60;pointer-events:none}body.coffee-label-printing #printSheetPortal .label-canvas{display:block!important;width:var(--label-design-w,360px)!important;height:var(--label-design-h,560px)!important;max-width:none!important;box-shadow:none!important;outline:0!important;transform:scale(var(--label-print-scale,.944882))!important;transform-origin:top left!important}body.coffee-label-printing #printSheetPortal .label-logo{filter:none!important;image-rendering:auto!important}body.coffee-label-printing #printSheetPortal .label-bg img{image-rendering:auto!important}body.namua-label-printing{margin:0!important;background:#fff!important}body.namua-label-printing > *{display:none!important}body.namua-label-printing #universalPrintPortal{display:grid!important;width:210mm!important;min-height:297mm!important;grid-template-columns:repeat(2,100mm)!important;align-content:start!important;gap:2.5mm 0!important;padding:5mm!important;background:#fff!important;box-sizing:border-box!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}body.namua-label-printing #universalPrintPortal .universal-print-slot{position:relative;width:100mm!important;break-inside:avoid!important;page-break-inside:avoid!important}body.namua-label-printing #universalPrintPortal .universal-print-slot:before{content:"";position:absolute;inset:-.5mm;border:.2mm dashed rgba(32,22,22,.55);pointer-events:none}body.namua-label-printing #universalPrintPortal .namua-label{width:100mm!important;min-height:68mm!important;max-width:none!important;box-shadow:none!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}}
</style>

<div class="coffee-label-page <?php echo $isUniversalTemplate ? 'is-universal-editor' : ''; ?>">
  <div class="card coffee-hero mb-4"><div class="card-body p-4 p-lg-5 d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div><div class="hero-kicker mb-2">Roastery Label Studio</div><h3 class="mb-2">Label Packaging Kopi</h3><div class="text-white-50"><?php echo $formMode ? 'Atur detail label, preview, lalu simpan untuk kembali ke daftar.' : 'Kelola label packaging kopi yang sudah dibuat. Duplikat template lama bila ingin produksi batch cepat.'; ?></div></div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="coffee-chip"><i class="ri ri-file-list-3-line"></i> PNG artwork</span><span class="coffee-chip"><i class="ri ri-pencil-line"></i> Editable text</span><span class="coffee-chip"><i class="ri ri-printer-line"></i> Print preview</span>
      <?php if ($formMode): ?>
        <a class="btn btn-light fw-bold" href="<?php echo site_url('roastery/packaging-labels'); ?>"><i class="ri ri-arrow-left-line me-1"></i>Kembali ke Daftar</a>
      <?php elseif (!empty($can_create)): ?>
        <a class="btn btn-light fw-bold" href="<?php echo site_url('roastery/packaging-labels?new=1'); ?>"><i class="ri ri-add-line me-1"></i>Buat Label</a>
      <?php endif; ?>
    </div>
  </div></div>

  <?php if (!$tableReady): ?><div class="alert alert-warning">Jalankan SQL <code>sql/2026-07-26a_create_coffee_packaging_labels.sql</code> agar data bisa disimpan.</div><?php endif; ?>

  <?php if ($formMode): ?>
  <div class="label-workbench mb-4">
    <div class="label-panel"><div class="label-panel-head"><div><h5><?php echo $isEditing ? 'Edit Label' : 'Buat Label Baru'; ?></h5><small class="text-muted">Data kopi + setting desain.</small></div><a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('roastery/packaging-labels'); ?>">Daftar Label</a></div>
      <div class="label-panel-body">
        <form method="post" action="<?php echo site_url('roastery/packaging-labels/save'); ?>" enctype="multipart/form-data" id="coffeeLabelForm">
          <?php if ($this->config->item('csrf_protection')): ?><input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>"><?php endif; ?>
          <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
          <input type="hidden" name="template_id" id="templateIdInput" value="<?php echo $selectedTemplateId; ?>">
          <input type="hidden" name="design_json" id="designJsonInput" value="<?php echo html_escape($designJson); ?>">
          <div class="label-form-grid">
            <div class="full label-template-bar">
              <div>
                <label class="form-label label-template-select-label" for="templateSelect">Pilih template</label>
                <select class="form-select label-template-select" id="templateSelect">
                  <?php foreach ($labelTemplates as $template): ?>
                    <?php
                      $templateId = (int)($template['id'] ?? 0);
                      $templateKey = (string)($template['template_key'] ?? '');
                      $templateQuery = array_merge($editorQuery, $templateId > 0 ? ['template_id' => $templateId] : ['template' => $templateKey]);
                      $isSelectedTemplate = ($templateId > 0 && $selectedTemplateId === $templateId)
                        || ($templateId <= 0 && $selectedTemplateKey === $templateKey);
                      $templateName = trim((string)($template['template_name'] ?? '')) ?: 'Template tanpa nama';
                      $templateDisplayName = $templateKey === 'classic-portrait' ? 'Default — ' . $templateName : $templateName;
                      $templateDescription = trim((string)($template['description'] ?? ''));
                      if ($templateDescription === '') {
                          $templateDescription = !empty($template['is_system']) ? 'Template awal; semua elemen tetap dapat Anda ubah.' : 'Template buatan operator; semua elemen tetap dapat Anda ubah.';
                      }
                    ?>
                    <option value="<?php echo html_escape(site_url('roastery/packaging-labels?' . http_build_query($templateQuery))); ?>" data-description="<?php echo html_escape($templateDescription); ?>" <?php echo $isSelectedTemplate ? 'selected' : ''; ?>><?php echo html_escape($templateDisplayName); ?></option>
                  <?php endforeach; ?>
                </select>
                <small class="label-template-help" id="templateSelectHelp">Pilih template untuk menerapkan layout awal. Simpan label dahulu bila ingin menyimpan perubahan editor saat ini.</small>
              </div>
              <button class="btn btn-danger label-template-save" type="button" id="saveTemplateBtn" <?php echo (!$tableReady || !$canSave)?'disabled':''; ?>><i class="ri ri-bookmark-3-line me-1"></i>Simpan template baru</button>
            </div>
            <div class="label-section-title"><i class="ri ri-restaurant-line"></i><span>Identitas Label & Produk</span></div>
            <div class="full">
              <label class="form-label">Nama Label</label>
              <input class="form-control" name="label_name" value="<?php echo html_escape($selectedLabelName); ?>" placeholder="Contoh: Prau Red Wine - Kemasan 200 g" required>
              <small class="text-muted">Penanda administrasi untuk membedakan variasi desain, ukuran, atau batch dari produk yang sama. Tidak dicetak pada kemasan.</small>
            </div>
            <div class="full">
              <label class="form-label">Nama Produk</label>
              <div class="label-product-picker">
                <select class="form-select" id="coffeeProductPick" name="product_id">
                  <option value="">Ambil dari master produk roastery...</option>
                  <?php foreach ($productOptions as $product): ?>
                    <option value="<?php echo (int)($product['id'] ?? 0); ?>" data-name="<?php echo html_escape((string)($product['product_name'] ?? '')); ?>" data-code="<?php echo html_escape((string)($product['product_code'] ?? '')); ?>" <?php echo $selectedProductId === (int)($product['id'] ?? 0) ? 'selected' : ''; ?>>
                      <?php echo html_escape(trim((string)($product['product_name'] ?? '') . ' - ' . (string)($product['product_code'] ?? ''), ' -')); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input class="form-control" name="coffee_name" data-label-field="coffee_name" value="<?php echo html_escape((string)($edit['coffee_name'] ?? '')); ?>" placeholder="Nama produk yang tercetak" required>
              </div>
              <small class="text-muted">Pilih master produk agar terhubung. Jika belum ada di master, nama produk tetap dapat diisi manual.</small>
            </div>
            <div><label class="form-label">Origin</label><input class="form-control" name="origin" data-label-field="origin" value="<?php echo html_escape((string)($edit['origin'] ?? '')); ?>" placeholder="Kintamani / Gayo"></div>
            <div><label class="form-label">Berat</label><input class="form-control" name="weight_text" data-label-field="weight_text" value="<?php echo html_escape((string)($edit['weight_text'] ?? '200 g')); ?>"></div>
            <div><label class="form-label">Process</label><input class="form-control" name="process_method" data-label-field="process_method" value="<?php echo html_escape((string)($edit['process_method'] ?? '')); ?>" placeholder="Natural / Washed"></div>
            <div>
              <label class="form-label">Roast Level</label>
              <select class="form-select" name="roast_level" data-label-field="roast_level">
                <?php foreach ($roastLevelOptions as $option): ?>
                  <option value="<?php echo html_escape($option); ?>" <?php echo $selectedRoastLevel === $option ? 'selected' : ''; ?>><?php echo html_escape($option); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div><label class="form-label">Batch No</label><input class="form-control" name="batch_no" data-label-field="batch_no" value="<?php echo html_escape((string)($edit['batch_no'] ?? '')); ?>"></div>
            <div><label class="form-label">Tanggal Roast</label><input class="form-control" type="date" name="roast_date" data-label-field="roast_date" value="<?php echo html_escape((string)($edit['roast_date'] ?? '')); ?>"></div>
            <div><label class="form-label">Best Before</label><input class="form-control" type="date" name="expiry_date" data-label-field="expiry_date" value="<?php echo html_escape((string)($edit['expiry_date'] ?? '')); ?>"></div>
            <div class="label-section-title"><i class="ri ri-star-line"></i><span>Sensory & Cerita Label</span></div>
            <div class="full">
              <label class="form-label">Tasting Notes + Icon</label>
              <textarea class="d-none" name="tasting_notes" data-label-field="tasting_notes" id="tastingNotesValue"><?php echo html_escape((string)($edit['tasting_notes'] ?? '')); ?></textarea>
              <div class="taste-builder">
                <div id="tasteRows"></div>
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                  <button class="btn btn-sm btn-outline-danger" type="button" id="addTasteNote"><i class="ri ri-add-line me-1"></i>Tambah Note</button>
                  <small class="text-muted">Ikon opsional. Sistem akan menyarankan ikon dari kata seperti citrus, floral, tea, chocolate, nutty.</small>
                </div>
              </div>
            </div>
            <div class="full"><label class="form-label">Brew Suggestion</label><input class="form-control" name="brew_suggestion" data-label-field="brew_suggestion" value="<?php echo html_escape((string)($edit['brew_suggestion'] ?? 'Filter / Espresso / Milk Based')); ?>"></div>
            <div class="full">
              <label class="form-label">Keterangan Label</label>
              <div class="description-card">
                <div class="label-meta-form">
                  <div>
                    <label class="form-label mb-1">Body</label>
                    <select class="form-select" name="body_level" data-meta-field="body_level">
                      <?php foreach ($bodyLevelOptions as $option): ?>
                        <option value="<?php echo html_escape($option); ?>" <?php echo $selectedBodyLevel === $option ? 'selected' : ''; ?>><?php echo html_escape($option); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div><label class="form-label mb-1">Elevation</label><input class="form-control" name="elevation_text" data-meta-field="elevation_text" value="<?php echo html_escape($selectedElevation); ?>" placeholder=">1200 mdpl"></div>
                  <div><label class="form-label mb-1">Bean / Grind</label><input class="form-control" name="bean_type" data-meta-field="bean_type" value="<?php echo html_escape($selectedBeanType); ?>" placeholder="Whole Bean"></div>
                  <div><label class="form-label mb-1">Footer Mini</label><input class="form-control" name="footer_note" data-meta-field="footer_note" value="<?php echo html_escape($selectedFooterNote); ?>" placeholder="Roasted in small batch"></div>
                  <div><label class="form-label mb-1">Ribbon Kiri</label><input class="form-control" name="ribbon_text" data-meta-field="ribbon_text" value="<?php echo html_escape($selectedRibbonText); ?>" placeholder="Single Origin / House Blend"></div>
                </div>
                <textarea class="form-control" name="description" data-label-field="description" placeholder="Catatan internal, misal batch khusus / instruksi cetak. Tidak tampil di label."><?php echo html_escape((string)($edit['description'] ?? '')); ?></textarea>
                <div class="description-hint">Yang tampil di label adalah Footer Mini, Ribbon Kiri, Bean/Grind, berat, dan panel data. Catatan internal hanya tersimpan untuk administrasi.</div>
              </div>
            </div>
            <div class="studio-only">
            <div class="full">
              <div class="label-section-title"><i class="ri ri-layout-grid-line"></i><span>Ukuran, Cetak & Visual</span></div>
              <label class="form-label">Ukuran Label</label>
              <div class="input-group mb-2"><input class="form-control" type="number" min="40" max="160" name="canvas_width_mm" id="canvasWidth" value="<?php echo $canvasWidth; ?>"><span class="input-group-text">x</span><input class="form-control" type="number" min="60" max="240" name="canvas_height_mm" id="canvasHeight" value="<?php echo $canvasHeight; ?>"><span class="input-group-text">mm</span></div>
              <div class="range-line"><small>Lebar Label</small><input type="range" id="labelWidthRange" min="40" max="160" value="<?php echo $canvasWidth; ?>"><output id="labelWidthOut"><?php echo $canvasWidth; ?>mm</output></div>
              <div class="range-line mt-2"><small>Tinggi Label</small><input type="range" id="labelHeightRange" min="60" max="240" value="<?php echo $canvasHeight; ?>"><output id="labelHeightOut"><?php echo $canvasHeight; ?>mm</output></div>
            </div>
            <div class="full label-print-settings">
              <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                <div>
                  <label class="form-label mb-1">Pengaturan Cetak</label>
                  <div class="small text-muted">Jumlah label dalam satu kertas mengikuti ukuran label, margin, dan gap.</div>
                </div>
                <span class="badge bg-label-danger" id="printFitBadge">Siap cetak</span>
              </div>
              <div class="print-grid">
                <div><label class="form-label">Kertas</label><select class="form-select" id="printPaper"><option value="A4">A4</option><option value="A3">A3</option><option value="CUSTOM">Custom</option></select></div>
                <div><label class="form-label">Orientasi</label><select class="form-select" id="printOrientation"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></div>
                <div><label class="form-label">Lebar mm</label><input class="form-control" type="number" min="80" max="500" id="printPaperW"></div>
                <div><label class="form-label">Tinggi mm</label><input class="form-control" type="number" min="80" max="500" id="printPaperH"></div>
                <div><label class="form-label">Jumlah label</label><input class="form-control" type="number" min="1" max="40" id="printPerSheet"></div>
                <div><label class="form-label">Margin mm</label><input class="form-control" type="number" min="0" max="40" step=".5" id="printMargin"></div>
                <div><label class="form-label">Gap mm</label><input class="form-control" type="number" min="0" max="30" step=".5" id="printGap"></div>
                <label class="print-check"><input type="checkbox" id="printCutLine"> Garis potong</label>
              </div>
            </div>
            <div><label class="form-label">Tema</label><select class="form-select" name="theme_preset" id="themePreset"><option value="heritage-cream" <?php echo $themePreset==='heritage-cream'?'selected':''; ?>>Heritage Cream</option><option value="porcelain-mist" <?php echo $themePreset==='porcelain-mist'?'selected':''; ?>>Porcelain Mist</option><option value="sakura-cream" <?php echo $themePreset==='sakura-cream'?'selected':''; ?>>Sakura Cream</option><option value="oat-paper" <?php echo $themePreset==='oat-paper'?'selected':''; ?>>Oat Paper</option><option value="matcha-sunrise" <?php echo $themePreset==='matcha-sunrise'?'selected':''; ?>>Matcha Sunrise</option><option value="clean-white" <?php echo $themePreset==='clean-white'?'selected':''; ?>>Clean White</option><option value="midnight-roast" <?php echo $themePreset==='midnight-roast'?'selected':''; ?>>Midnight Roast</option></select></div>
            <div><label class="form-label">Model Artwork</label><select class="form-select" id="artworkMode"><option value="full" <?php echo $artworkMode==='full'?'selected':''; ?>>Full Background</option><option value="rounded" <?php echo $artworkMode==='rounded'?'selected':''; ?>>Rounded Card</option><option value="circle" <?php echo $artworkMode==='circle'?'selected':''; ?>>Circle Medallion</option><option value="arch" <?php echo $artworkMode==='arch'?'selected':''; ?>>Arch Window</option></select></div>
            <div><label class="form-label">Fit Background</label><select class="form-select" id="artworkFit"><option value="stretch" <?php echo $artworkFit==='stretch'?'selected':''; ?>>Stretch / Sesuaikan</option><option value="contain" <?php echo $artworkFit==='contain'?'selected':''; ?>>Contain / Utuh</option><option value="cover" <?php echo $artworkFit==='cover'?'selected':''; ?>>Cover / Crop</option></select></div>
            <div><label class="form-label">Corak Label</label><select class="form-select" id="patternMode"><option value="contour" <?php echo $patternMode==='contour'?'selected':''; ?>>Contour Circle</option><option value="speckles" <?php echo $patternMode==='speckles'?'selected':''; ?>>Speckles</option><option value="diagonal" <?php echo $patternMode==='diagonal'?'selected':''; ?>>Diagonal Lines</option><option value="grid" <?php echo $patternMode==='grid'?'selected':''; ?>>Fine Grid</option><option value="waves" <?php echo $patternMode==='waves'?'selected':''; ?>>Organic Waves</option><option value="sunburst" <?php echo $patternMode==='sunburst'?'selected':''; ?>>Sunburst</option><option value="none" <?php echo $patternMode==='none'?'selected':''; ?>>Tanpa Corak</option></select></div>
            </div>
            <div><label class="form-label">Artwork PNG</label><input class="form-control" type="file" name="label_image" id="labelImageInput" accept="image/png"><small class="text-muted">Upload PNG baru akan mengganti artwork lama.</small></div>
            <div class="full">
              <label class="form-label">Galeri Artwork Tersimpan</label>
              <input type="hidden" name="gallery_image_path" id="galleryImagePath" value="">
              <?php if (empty($artworkGallery)): ?>
                <div class="alert alert-light border mb-0">Belum ada PNG di galeri. Upload artwork pertama dulu, nanti otomatis muncul di sini.</div>
              <?php else: ?>
                <div class="gallery-strip" id="artworkGallery">
                  <?php foreach ($artworkGallery as $art): ?>
                    <?php $isCurrentArt = !empty($art['path']) && $art['path'] === $imagePath; ?>
                    <button class="gallery-tile <?php echo $isCurrentArt ? 'active' : ''; ?>" type="button" data-path="<?php echo html_escape((string)$art['path']); ?>" data-url="<?php echo html_escape((string)$art['url']); ?>">
                      <img src="<?php echo html_escape((string)$art['url']); ?>" alt="">
                      <span><?php echo html_escape((string)$art['name']); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="studio-only">
            <div><label class="form-label">Logo Utama</label><input class="form-control" type="file" name="logo_image" id="logoImageInput" accept="image/png,image/svg+xml,.svg"><small class="text-muted">Logo utama besar di tengah label. SVG resmi disarankan untuk cetak tajam.</small></div>
            <div>
              <label class="form-label">Galeri Logo Utama</label>
              <input type="hidden" name="gallery_logo_path" id="galleryLogoPath" value="">
              <?php if (empty($logoGallery)): ?>
                <div class="alert alert-light border mb-0 py-2">Belum ada logo tersimpan.</div>
              <?php else: ?>
                <div class="gallery-strip" id="logoGallery">
                  <?php foreach ($logoGallery as $logo): ?>
                    <?php $isCurrentLogo = !empty($logo['path']) && $logo['path'] === $logoPath; ?>
                    <button class="gallery-tile <?php echo $isCurrentLogo ? 'active' : ''; ?>" type="button" data-path="<?php echo html_escape((string)$logo['path']); ?>" data-url="<?php echo html_escape((string)$logo['url']); ?>">
                      <img src="<?php echo html_escape((string)$logo['url']); ?>" alt="">
                      <span><?php echo html_escape((string)$logo['name']); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div><label class="form-label">Logo Badge Kanan Atas</label><input class="form-control" type="file" name="badge_logo_image" id="badgeLogoInput" accept="image/png,image/svg+xml,.svg"><small class="text-muted">Badge/logo kecil di kanan atas. Jika kosong, ikut logo utama.</small></div>
            <div>
              <label class="form-label">Galeri Logo Badge</label>
              <input type="hidden" name="gallery_badge_logo_path" id="galleryBadgeLogoPath" value="">
              <?php if (empty($logoGallery)): ?>
                <div class="alert alert-light border mb-0 py-2">Belum ada logo tersimpan.</div>
              <?php else: ?>
                <div class="gallery-strip" id="badgeLogoGallery">
                  <?php foreach ($logoGallery as $logo): ?>
                    <?php $isCurrentBadgeLogo = !empty($logo['path']) && $logo['path'] === $badgeLogoPath; ?>
                    <button class="gallery-tile <?php echo $isCurrentBadgeLogo ? 'active' : ''; ?>" type="button" data-path="<?php echo html_escape((string)$logo['path']); ?>" data-url="<?php echo html_escape((string)$logo['url']); ?>">
                      <img src="<?php echo html_escape((string)$logo['url']); ?>" alt="">
                      <span><?php echo html_escape((string)$logo['name']); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            </div>
            <div><label class="form-label">Status</label><select class="form-select" name="is_active"><option value="1" <?php echo (int)($edit['is_active'] ?? 1)===1?'selected':''; ?>>Aktif</option><option value="0" <?php echo (int)($edit['is_active'] ?? 1)===0?'selected':''; ?>>Nonaktif</option></select></div>
          </div>
          <hr class="my-4">
          <div class="label-tools">
            <div class="full">
              <label class="form-label">Elemen di label</label>
              <div class="editor-drag-hint mb-2"><i class="ri ri-drag-move-2-line me-1"></i>Tekan lalu geser elemen langsung di preview. Tombol mata menampilkan atau menyembunyikan elemen, tanpa menghapus datanya.</div>
              <div class="element-toggle-grid" id="elementToggleGrid">
                <button type="button" class="element-toggle" data-element-toggle="logo"><span>Logo utama</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="badge_logo"><span>Logo badge</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="side_ribbon"><span>Ribbon kiri</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="coffee_name"><span>Nama kopi</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="roastery_kicker"><span>Footer mini</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="taste_icons"><span>Note + ikon</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="brew_suggestion"><span>Saran seduh</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="info_panel"><span>Panel informasi</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="origin"><span>Origin mini</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="process_method"><span>Process mini</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="roast_level"><span>Roast level</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="weight_text"><span>Berat</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="batch_no"><span>Nomor batch</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="roast_date"><span>Tanggal roast</span><i class="ri ri-eye-line"></i></button>
                <button type="button" class="element-toggle" data-element-toggle="expiry_date"><span>Best before</span><i class="ri ri-eye-line"></i></button>
              </div>
            </div>
            <div class="full">
              <label class="form-label">Elemen yang diatur</label>
              <select class="form-select" id="blockSelect">
                <option value="logo">Logo Utama</option>
                <option value="badge_logo">Logo Badge Kanan Atas</option>
                <option value="side_ribbon">Ribbon Kiri</option>
                <option value="coffee_name">Nama Produk di Label</option>
                <option value="roastery_kicker">Footer Mini Atas</option>
                <option value="taste_icons">Ikon + Teks Tasting</option>
                <option value="brew_suggestion">Brew Suggestion</option>
                <option value="info_panel">Panel Info Bawah</option>
                <option value="origin">Origin Mini</option>
                <option value="process_method">Process Mini</option>
                <option value="roast_level">Roast Level Mini</option>
                <option value="weight_text">Berat Mini</option>
                <option value="tasting_notes">Tasting Notes Teks</option>
                <option value="batch_no">Batch Mini</option>
                <option value="roast_date">Tanggal Roast Mini</option>
                <option value="expiry_date">Best Before Mini</option>
                <option value="description">Deskripsi/Footer Bawah</option>
              </select>
              <small class="text-muted d-block mt-1" id="blockSourceHint">Sumber: upload logo utama / galeri logo utama.</small>
            </div>
            <div><label class="form-label">Font</label><select class="form-select" id="fontFamily"><option>Fraunces</option><option>Playfair Display</option><option>Cormorant Garamond</option><option>Libre Baskerville</option><option>Bebas Neue</option><option>Space Grotesk</option><option>Jost</option></select></div>
            <div><label class="form-label">Warna</label><input class="form-control form-control-color w-100" type="color" id="fontColor" value="#7d1720"></div>
            <div><label class="form-label">Background</label><input class="form-control form-control-color w-100" type="color" id="bgColor" value="#fff4ec"></div>
            <div class="full range-line"><small>Ukuran</small><input type="range" id="fontSize" min="7" max="52"><output id="fontSizeOut"></output></div>
            <div class="full range-line taste-text-control" id="tasteTextSizeWrap"><small>Teks Note</small><input type="range" id="tasteTextSize" min="5" max="20" step=".2"><output id="tasteTextSizeOut"></output></div>
            <div class="full range-line"><small>Posisi X</small><input type="range" id="posX" min="-20" max="100"><output id="posXOut"></output></div>
            <div class="full range-line"><small>Posisi Y</small><input type="range" id="posY" min="-20" max="100"><output id="posYOut"></output></div>
            <div class="full range-line"><small>Lebar</small><input type="range" id="blockWidth" min="10" max="100"><output id="blockWidthOut"></output></div>
            <div class="full range-line"><small>Tinggi Panel</small><input type="range" id="panelHeight" min="18" max="48"><output id="panelHeightOut"></output></div>
            <div class="full range-line"><small>Spasi</small><input type="range" id="letterSpacing" min="0" max="8" step=".1"><output id="letterSpacingOut"></output></div>
            <div class="full toggle-row"><button class="btn btn-sm btn-danger" type="button" id="resetPremiumLayout"><i class="ri ri-magic-line me-1"></i>Reset Layout Premium</button><button class="btn btn-sm btn-outline-dark" type="button" id="toggleBlockVisible"><i class="ri ri-eye-line me-1"></i><span>Tampil</span></button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="bold"><strong>B</strong> Bold</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="italic"><em>I</em> Italic</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="uppercase">Uppercase</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="shadow">Kontras</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="logoTint">Tint Logo</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="left">Left</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="center">Center</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="right">Right</button></div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-danger" type="submit" <?php echo (!$tableReady || !$canSave)?'disabled':''; ?>><i class="ri ri-save-line me-1"></i>Simpan Label</button><button class="btn btn-outline-dark" type="button" id="printLabelBtn"><i class="ri ri-printer-line me-1"></i>Print / Simpan PDF</button></div>
        </form>
        <form method="post" action="<?php echo site_url('roastery/packaging-labels/templates/save'); ?>" id="saveTemplateForm" class="d-none">
          <?php if ($this->config->item('csrf_protection')): ?><input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>"><?php endif; ?>
          <input type="hidden" name="return_label_id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
          <input type="hidden" name="template_name" id="templateNameInput">
          <input type="hidden" name="template_description" id="templateDescriptionInput">
          <input type="hidden" name="template_design_json" id="templateDesignJsonInput">
        </form>
        <div class="label-template-modal" id="saveTemplateModal" hidden aria-hidden="true">
          <div class="label-template-dialog" role="dialog" aria-modal="true" aria-labelledby="saveTemplateModalTitle">
            <h5 id="saveTemplateModalTitle">Simpan template baru</h5>
            <p>Beri nama agar template mudah ditemukan saat membuat label berikutnya. Nama template wajib diisi.</p>
            <div>
              <label class="form-label" for="templateNameField">Nama template <span class="required-mark">*</span></label>
              <input class="form-control" id="templateNameField" maxlength="160" autocomplete="off" placeholder="Contoh: Espresso 250 g — Panen 2026" required>
              <div class="invalid-feedback">Nama template wajib diisi.</div>
            </div>
            <div class="mt-3">
              <label class="form-label" for="templateDescriptionField">Keterangan singkat <span class="text-muted fw-normal">(opsional)</span></label>
              <textarea class="form-control" id="templateDescriptionField" maxlength="255" rows="3" placeholder="Contoh: Layout depan untuk kopi espresso 250 gram."></textarea>
            </div>
            <div class="label-template-dialog-actions"><button class="btn btn-light" type="button" id="cancelTemplateSave">Batal</button><button class="btn btn-danger" type="button" id="confirmTemplateSave"><i class="ri ri-save-line me-1"></i>Simpan template</button></div>
          </div>
        </div>
      </div>
    </div>

    <div class="label-panel label-preview-card print-target"><div class="label-panel-head"><div><h5>Live Preview</h5><small class="text-muted">Inilah kanvas yang akan disalin ke halaman cetak. Klik atau drag elemen untuk mengaturnya.</small></div><span class="badge bg-label-danger">Roastery</span></div>
      <div class="label-panel-body"><div class="preview-shell"><div class="universal-label-preview"><article id="universalLabel" class="namua-label"><div class="namua-label__artwork <?php echo $imageUrl === '' ? 'no-image' : ''; ?>" id="universalArtworkPreview"><img id="universalArtworkImage" src="<?php echo html_escape($imageUrl); ?>" alt="" <?php echo $imageUrl === '' ? 'style="display:none"' : ''; ?>></div><aside class="namua-label__rail"><div class="namua-label__logo-shell"><img src="<?php echo html_escape($namuaRoastersLogoUrl); ?>" alt="Logo usaha"></div><div class="namua-label__side-rule"></div><div class="namua-label__side-copy"><span class="namua-label__side-series" data-universal-output="footer_note"><?php echo html_escape($selectedFooterNote !== '' ? $selectedFooterNote : 'Single Origin'); ?></span><span class="namua-label__side-motto">From origin<br>to character</span><div class="namua-label__side-meta"><div class="namua-label__side-meta-item" data-universal-row="brew_suggestion"><span class="namua-label__side-meta-label">Brew</span><span class="namua-label__side-meta-value" data-universal-output="brew_suggestion"></span></div><div class="namua-label__side-meta-item" data-universal-row="roast_level"><span class="namua-label__side-meta-label">Roast</span><span class="namua-label__side-meta-value" data-universal-output="roast_level"></span></div><div class="namua-label__side-meta-item" data-universal-row="body_level"><span class="namua-label__side-meta-label">Body</span><span class="namua-label__side-meta-value" data-universal-output="body_level"></span></div><div class="namua-label__side-meta-item" data-universal-row="process_method"><span class="namua-label__side-meta-label">Process</span><span class="namua-label__side-meta-value" data-universal-output="process_method"></span></div></div></div></aside><div class="namua-label__content"><h1 class="namua-label__title" data-universal-output="coffee_name"></h1><div class="namua-label__origin-stack"><div class="namua-label__origin" data-universal-row="origin"><i class="ri ri-map-pin-2-line"></i><span data-universal-output="origin"></span></div><div class="namua-label__elevation" data-universal-row="elevation_text"><i class="ri ri-landscape-line"></i><span data-universal-output="elevation_text"></span></div></div><div class="namua-label__notes" id="universalNotesPreview"></div><div class="namua-label__trace" id="universalTracePreview"><span class="namua-label__trace-item" data-universal-trace="batch_no"><strong>BATCH</strong> <span></span></span><span class="namua-label__trace-item" data-universal-trace="roast_date"><strong>ROASTED</strong> <span></span></span><span class="namua-label__trace-item" data-universal-trace="expiry_date"><strong>BEST BEFORE</strong> <span></span></span></div></div></article></div><div id="labelCanvas" class="label-canvas theme-<?php echo html_escape($themePreset); ?> artwork-mode-<?php echo html_escape($artworkMode); ?> artwork-fit-<?php echo html_escape($artworkFit); ?> pattern-mode-<?php echo html_escape($patternMode); ?>" style="--label-preview-w:<?php echo $canvasWidth * 4; ?>px;--label-preview-h:<?php echo $canvasHeight * 4; ?>px;--label-print-w:<?php echo $canvasWidth; ?>mm;--label-print-h:<?php echo $canvasHeight; ?>mm;">
        <div class="label-bg <?php echo $imageUrl===''?'no-image':''; ?>" id="labelBg"><?php if ($imageUrl !== ''): ?><img id="labelImagePreview" src="<?php echo html_escape($imageUrl); ?>" alt="Label artwork"><?php else: ?><img id="labelImagePreview" src="" alt="" style="display:none"><?php endif; ?></div>
        <div class="label-overlay"></div><div class="label-brand-panel"></div><div class="label-sensory-panel"></div><div class="label-orbit o1"></div><div class="label-orbit o2"></div><div class="label-orbit o3"></div><div class="label-speckles"></div><div class="label-side-ribbon" data-block="side_ribbon"><span><?php echo html_escape(strtoupper($selectedRibbonText)); ?></span><i class="ri ri-star-line"></i></div><div class="taste-icon-row" data-block="taste_icons"><span><b><i class="ri ri-flower-line"></i></b><small>HIBISCUS</small></span><span><b><i class="ri ri-apple-line"></i></b><small>RIPE PEACH</small></span><span><b><i class="ri ri-goblet-line"></i></b><small>RED WINE</small></span></div>
        <div class="label-roastery-kicker" data-block="roastery_kicker" data-info-value="footer_note"></div>
        <div class="label-info-panel" data-block="info_panel">
          <div class="info-top">
            <div class="info-cell"><span class="info-title">Roast Level</span><span class="info-value" data-info-value="roast_level">Medium</span><span class="info-dots" data-info-dots="roast_level"></span></div>
            <div class="info-cell"><span class="info-title">Body</span><span class="info-value" data-info-value="body_level">Light - Medium</span><span class="info-dots" data-info-dots="body_level"></span></div>
          </div>
          <div class="info-bottom">
            <div class="info-line info-line-origin"><b>Origin</b><span data-info-value="origin">Nusantara</span></div>
            <div class="info-line info-line-dual"><b>Elevation</b><span data-info-value="elevation">&gt;1200 mdpl</span><b>Process</b><span data-info-value="process_method">Natural</span></div>
          </div>
          <div class="info-date-row">
            <div class="info-date-cell"><b>Batch</b><span data-info-value="batch_no">&nbsp;</span></div>
            <div class="info-date-cell"><b>Roasted</b><span data-info-value="roast_date">&nbsp;</span></div>
            <div class="info-date-cell"><b>Best Before</b><span data-info-value="expiry_date">&nbsp;</span></div>
          </div>
          <div class="label-pack-footer"><span data-info-value="bean_type">Whole Bean</span><span data-info-value="weight_text">200 g</span></div>
        </div>
        <img class="label-logo label-logo-main" data-block="logo" src="<?php echo html_escape($logoUrl); ?>" alt="Logo usaha">
        <img class="label-logo label-badge-logo" data-block="badge_logo" src="<?php echo html_escape($badgeLogoUrl); ?>" alt="Badge">
        <?php foreach (['coffee_name','origin','process_method','roast_level','weight_text','tasting_notes','brew_suggestion','batch_no','roast_date','expiry_date','description'] as $block): ?><div class="label-text" data-block="<?php echo $block; ?>"></div><?php endforeach; ?>
        <div class="drag-guides" id="dragGuides" aria-hidden="true">
          <div class="drag-guide-line guide-v guide-center" id="guideCenterV"></div>
          <div class="drag-guide-line guide-h guide-center" id="guideCenterH"></div>
          <div class="drag-guide-line guide-v" id="guideCurrentV"></div>
          <div class="drag-guide-line guide-h" id="guideCurrentH"></div>
          <div class="drag-guide-badge" id="guideBadge">X 0.0% · Y 0.0%</div>
        </div>
      </div><div id="printSheet" class="print-sheet" aria-hidden="true"></div></div><small class="text-muted d-block mt-3"><i class="ri ri-information-line me-1"></i>Gunakan artwork PNG sebagai dasar, lalu teks batch tetap bisa diubah cepat.</small></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$formMode): ?>
  <div class="label-panel">
    <div class="label-panel-head label-index-head flex-wrap align-items-start">
      <div>
        <h5>Daftar Label Packaging</h5>
        <small class="text-muted">Terbaru paling atas. Edit, duplikat, atau nonaktifkan label lama dari sini.</small>
      </div>
      <div class="label-index-controls d-flex flex-column flex-lg-row align-items-lg-center gap-2 ms-lg-auto">
        <div class="label-status-tabs">
          <?php foreach ($statusTabs as $statusKey => $statusLabel): ?>
            <?php
              $tabQuery = ['status' => $statusKey];
              if (trim((string)($filters['q'] ?? '')) !== '') {
                  $tabQuery['q'] = trim((string)$filters['q']);
              }
              $tabActive = $currentStatus === $statusKey;
            ?>
            <a class="label-status-tab <?php echo $tabActive ? 'active' : ''; ?>" href="<?php echo site_url('roastery/packaging-labels?'.http_build_query($tabQuery)); ?>">
              <?php if ($statusKey === 'ACTIVE'): ?><i class="ri ri-checkbox-circle-line"></i><?php elseif ($statusKey === 'INACTIVE'): ?><i class="ri ri-close-circle-line"></i><?php else: ?><i class="ri ri-stack-line"></i><?php endif; ?>
              <?php echo html_escape($statusLabel); ?>
            </a>
          <?php endforeach; ?>
        </div>
        <form class="label-index-filter d-flex flex-wrap gap-2" method="get" action="<?php echo site_url('roastery/packaging-labels'); ?>">
          <input type="hidden" name="status" value="<?php echo html_escape($currentStatus); ?>">
          <input class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Cari label, produk, atau origin" style="min-width:220px">
          <button class="btn btn-outline-danger" type="submit"><i class="ri ri-search-line me-1"></i>Filter</button>
          <?php if (trim((string)($filters['q'] ?? '')) !== ''): ?><a class="btn btn-outline-secondary" href="<?php echo site_url('roastery/packaging-labels?status='.rawurlencode($currentStatus)); ?>">Clear</a><?php endif; ?>
        </form>
        <?php if (!empty($can_create)): ?>
          <a class="btn btn-danger" href="<?php echo site_url('roastery/packaging-labels?new=1'); ?>"><i class="ri ri-add-line me-1"></i>Buat Label</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="label-panel-body">
      <?php
        $visibleLabelCount = count($labels);
        $artworkLabelCount = 0;
        $activeLabelCount = 0;
        foreach ($labels as $summaryRow) {
            if (!empty($summaryRow['image_path'])) {
                $artworkLabelCount++;
            }
            if ((int)($summaryRow['is_active'] ?? 0) === 1) {
                $activeLabelCount++;
            }
        }
      ?>
      <div class="label-index-summary">
        <div class="summary-tile"><i class="ri ri-stack-line"></i><div><small>Label di filter</small><b><?php echo number_format($visibleLabelCount, 0, ',', '.'); ?></b></div></div>
        <div class="summary-tile"><i class="ri ri-file-list-3-line"></i><div><small>Punya artwork</small><b><?php echo number_format($artworkLabelCount, 0, ',', '.'); ?></b></div></div>
        <div class="summary-tile"><i class="ri ri-printer-line"></i><div><small>Aktif siap cetak</small><b><?php echo number_format($activeLabelCount, 0, ',', '.'); ?></b></div></div>
      </div>
      <?php if (empty($labels)): ?>
        <div class="text-center py-5">
          <div class="mb-2 fw-bold text-muted">Belum ada label tersimpan.</div>
          <?php if (!empty($can_create)): ?>
            <a class="btn btn-danger" href="<?php echo site_url('roastery/packaging-labels?new=1'); ?>"><i class="ri ri-add-line me-1"></i>Buat Label</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($labels as $row): ?>
            <?php
              $rowDesign = json_decode((string)($row['design_json'] ?? ''), true);
              $rowMeta = is_array($rowDesign) && is_array($rowDesign['meta'] ?? null) ? $rowDesign['meta'] : [];
              $isLegacyWide = trim((string)($row['theme_preset'] ?? '')) === 'namua-universal'
                || (is_array($rowDesign) && (string)($rowDesign['layout'] ?? '') === 'namua-universal-10cm-v1');
              $templateBadge = trim((string)($rowMeta['template_name'] ?? ''));
              if ($templateBadge === '') {
                $templateBadge = $isLegacyWide ? 'Retail Wide' : 'Classic Portrait';
              }
              $displayLabelName = trim((string)($row['label_name'] ?? $row['coffee_name'] ?? ''));
              $displayProductName = trim((string)($row['coffee_name'] ?? ''));
            ?>
            <div class="col-md-6 col-xl-4"><div class="saved-card h-100">
              <div class="saved-thumb"><?php if (!empty($row['image_path'])): ?><img src="<?php echo html_escape(base_url($row['image_path'])); ?>" alt=""><?php endif; ?></div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between gap-2">
                  <div class="saved-title text-truncate"><?php echo html_escape($displayLabelName !== '' ? $displayLabelName : 'Tanpa nama label'); ?></div>
                  <div class="d-flex flex-wrap justify-content-end gap-1"><span class="badge bg-label-secondary"><?php echo html_escape($templateBadge); ?></span><span class="badge <?php echo (int)($row['is_active'] ?? 1) === 1 ? 'bg-label-success' : 'bg-label-secondary'; ?>"><?php echo (int)($row['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif'; ?></span></div>
                </div>
                <div class="small text-muted text-truncate"><i class="ri ri-cup-line me-1"></i>Produk: <?php echo html_escape($displayProductName !== '' ? $displayProductName : '-'); ?></div>
                <div class="small text-muted text-truncate"><?php echo html_escape(trim((string)$row['origin'].' '.(string)$row['weight_text'])); ?></div>
                <div class="small text-muted text-truncate"><?php echo html_escape((string)($row['label_code'] ?? '')); ?></div>
                <div class="saved-actions">
                  <a class="btn btn-sm btn-outline-dark" href="<?php echo site_url('roastery/packaging-labels/print/'.(int)$row['id']); ?>"><i class="ri ri-printer-line"></i>Cetak</a>
                  <?php if (!empty($can_edit)): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('roastery/packaging-labels?edit='.(int)$row['id']); ?>"><i class="ri ri-edit-line"></i>Edit</a><?php endif; ?>
                  <?php if (!empty($can_create)): ?><a class="btn btn-sm btn-outline-warning" href="<?php echo site_url('roastery/packaging-labels/duplicate/'.(int)$row['id']); ?>" onclick="return confirm('Duplikat label ini sebagai template baru?')"><i class="ri ri-file-copy-line"></i>Duplikat</a><?php endif; ?>
                  <?php if ((int)($row['is_active'] ?? 1) === 0): ?>
                    <?php if (!empty($can_edit)): ?><a class="btn btn-sm btn-success btn-wide" href="<?php echo site_url('roastery/packaging-labels/activate/'.(int)$row['id']); ?>" onclick="return confirm('Aktifkan kembali label ini?')"><i class="ri ri-checkbox-circle-line"></i>Aktifkan</a><?php endif; ?>
                  <?php elseif (!empty($can_delete)): ?>
                    <a class="btn btn-sm btn-outline-danger" href="<?php echo site_url('roastery/packaging-labels/delete/'.(int)$row['id']); ?>" onclick="return confirm('Nonaktifkan label ini?')"><i class="ri ri-delete-bin-line"></i>Nonaktif</a>
                  <?php endif; ?>
                </div>
              </div>
            </div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($formMode): ?>
<script>
(function(){
const initialRaw=<?php echo json_encode($designJson, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const el=id=>document.getElementById(id), fields=[...document.querySelectorAll('[data-label-field]')], metaFields=[...document.querySelectorAll('[data-meta-field]')], by=n=>document.querySelector('[data-label-field="'+n+'"]'), metaBy=n=>document.querySelector('[data-meta-field="'+n+'"]');
const canvas=el('labelCanvas'), bg=el('labelBg'), img=el('labelImagePreview'), designInput=el('designJsonInput'), blockSelect=el('blockSelect'), blockSourceHint=el('blockSourceHint');
const canvasWidthEl=el('canvasWidth'), canvasHeightEl=el('canvasHeight'), labelWidthRange=el('labelWidthRange'), labelHeightRange=el('labelHeightRange'), labelWidthOut=el('labelWidthOut'), labelHeightOut=el('labelHeightOut'), themePresetEl=el('themePreset'), artworkModeEl=el('artworkMode'), artworkFitEl=el('artworkFit'), patternModeEl=el('patternMode'), imageInput=el('labelImageInput'), logoInput=el('logoImageInput'), badgeLogoInput=el('badgeLogoInput'), galleryPath=el('galleryImagePath'), galleryLogoPath=el('galleryLogoPath'), galleryBadgeLogoPath=el('galleryBadgeLogoPath'), formEl=el('coffeeLabelForm'), printBtn=el('printLabelBtn'), resetPremiumLayout=el('resetPremiumLayout'), toggleBlockVisible=el('toggleBlockVisible'), templateSelect=el('templateSelect'), templateSelectHelp=el('templateSelectHelp'), saveTemplateBtn=el('saveTemplateBtn'), saveTemplateForm=el('saveTemplateForm'), saveTemplateModal=el('saveTemplateModal'), templateNameField=el('templateNameField'), templateDescriptionField=el('templateDescriptionField'), confirmTemplateSave=el('confirmTemplateSave'), cancelTemplateSave=el('cancelTemplateSave'), templateNameInput=el('templateNameInput'), templateDescriptionInput=el('templateDescriptionInput'), templateDesignJsonInput=el('templateDesignJsonInput'), logoEl=document.querySelector('.label-logo-main[data-block="logo"]'), badgeLogoEl=document.querySelector('.label-badge-logo[data-block="badge_logo"]'), tasteRows=el('tasteRows'), addTasteNote=el('addTasteNote'), tastingNotesValue=el('tastingNotesValue'), tasteIconRow=document.querySelector('.taste-icon-row[data-block="taste_icons"]'), printSheet=el('printSheet'), previewShell=document.querySelector('.preview-shell'), coffeeProductPick=el('coffeeProductPick'), printFitBadge=el('printFitBadge');
const isUniversalTemplate=<?php echo $isUniversalTemplate ? 'true' : 'false'; ?>, universalLabel=el('universalLabel'), universalArtwork=el('universalArtworkPreview'), universalArtworkImage=el('universalArtworkImage'), universalNotes=el('universalNotesPreview'), universalTrace=el('universalTracePreview');
const printControls={paper:el('printPaper'),orientation:el('printOrientation'),paperW:el('printPaperW'),paperH:el('printPaperH'),perSheet:el('printPerSheet'),margin:el('printMargin'),gap:el('printGap'),cutLine:el('printCutLine')};
const guides={wrap:el('dragGuides'),centerV:el('guideCenterV'),centerH:el('guideCenterH'),currentV:el('guideCurrentV'),currentH:el('guideCurrentH'),badge:el('guideBadge')};
const c={fontFamily:el('fontFamily'),fontColor:el('fontColor'),bgColor:el('bgColor'),fontSize:el('fontSize'),posX:el('posX'),posY:el('posY'),blockWidth:el('blockWidth'),panelHeight:el('panelHeight'),letterSpacing:el('letterSpacing')};
const o={fontSize:el('fontSizeOut'),posX:el('posXOut'),posY:el('posYOut'),blockWidth:el('blockWidthOut'),panelHeight:el('panelHeightOut'),letterSpacing:el('letterSpacingOut')};
const tasteTextControl={wrap:el('tasteTextSizeWrap'),input:el('tasteTextSize'),out:el('tasteTextSizeOut')};
const defaults={
  canvas:{theme:'heritage-cream',artworkMode:'full',artworkFit:'stretch',patternMode:'contour'},
  meta:{
    body_level:'Medium',
    elevation_text:'1.400 - 1.800 mdpl',
    bean_type:'Whole Bean',
    footer_note:'Single Origin',
    ribbon_text:'Single Origin'
  },
  print:{paper:'A4',orientation:'portrait',paperW:210,paperH:297,perSheet:4,margin:8,gap:4,cutLine:true},
  tasteIcons:['ri-flower-line','ri-apple-line','ri-goblet-line'],
  tasteIconSizes:[18,18,18],
  tasteTextSizes:[6.8,6.8,6.8],
  blocks:{
    logo:{x:28,y:11,w:44,size:24,font:'Jost',color:'#7a0f2b',logoTint:false,bold:true,align:'center',letter:0},
    badge_logo:{x:78,y:7,w:14,size:18,font:'Jost',color:'#7a0f2b',logoTint:false,bold:true,align:'center',letter:0},
    side_ribbon:{x:2.1,y:.8,w:8.5,h:21.5,size:9,font:'Space Grotesk',color:'#fff6ea',bgColor:'#7a3d46',bold:true,uppercase:true,shadow:true,align:'center',letter:4.2},
    roastery_kicker:{x:25,y:39,w:50,size:8,font:'Space Grotesk',color:'#fff3ea',bold:true,uppercase:true,shadow:true,align:'center',letter:4.4},
    coffee_name:{x:16,y:44,w:68,size:34,font:'Cormorant Garamond',color:'#fff5ea',bold:true,italic:false,uppercase:true,shadow:true,align:'center',letter:3.8},
    origin:{x:18,y:50,w:64,size:9,font:'Space Grotesk',color:'#fff5ea',bold:true,uppercase:true,align:'center',letter:4},
    process_method:{x:16,y:73,w:22,size:8,font:'Space Grotesk',color:'#6f2c30',bold:true,uppercase:true,align:'center',letter:1.2},
    roast_level:{x:39,y:73,w:22,size:8,font:'Space Grotesk',color:'#6f2c30',bold:true,uppercase:true,align:'center',letter:1.2},
    weight_text:{x:66,y:87,w:22,size:8,font:'Space Grotesk',color:'#6f2c30',bold:true,uppercase:true,align:'center',letter:1.1},
    tasting_notes:{x:14,y:56,w:72,size:8,font:'Space Grotesk',color:'#fff5ea',bold:true,italic:false,shadow:true,align:'center',letter:2.6},
    taste_icons:{x:17,y:63,w:66,size:18,textSize:6.8,font:'Jost',color:'#fff6ea',bold:false,shadow:true,align:'center',letter:0},
    brew_suggestion:{x:29,y:51.5,w:42,size:9,font:'Space Grotesk',color:'#fff3ea',bold:true,uppercase:true,shadow:true,align:'center',letter:4.4},
    info_panel:{x:10.5,y:69.2,w:79,h:24.5,size:6.5,font:'Space Grotesk',color:'#6e2b2d',bgColor:'#fff4ec',align:'left',letter:.03},
    batch_no:{x:16,y:82,w:22,size:7,font:'Space Grotesk',color:'#6f2c30',align:'center',letter:.8},
    roast_date:{x:39,y:82,w:22,size:7,font:'Space Grotesk',color:'#6f2c30',align:'center',letter:.8},
    expiry_date:{x:62,y:82,w:22,size:7,font:'Space Grotesk',color:'#6f2c30',align:'center',letter:.8},
    description:{x:14,y:94,w:72,size:6,font:'Jost',color:'#6a4937',align:'center',letter:.25}
  }
};
const hiddenByDefaultBlocks=['origin','process_method','roast_level','weight_text','tasting_notes','batch_no','roast_date','expiry_date','description'];
Object.keys(defaults.blocks).forEach(k=>{
  if(defaults.blocks[k].visible===undefined){
    defaults.blocks[k].visible=!hiddenByDefaultBlocks.includes(k);
  }
});
function clone(x){return JSON.parse(JSON.stringify(x))}function parsed(){try{return JSON.parse(initialRaw||'{}')||{}}catch(e){return{}}}
function merge(a,b){const r=clone(a);if(b.canvas)Object.assign(r.canvas,b.canvas);if(b.meta)Object.assign(r.meta,b.meta);if(b.print)Object.assign(r.print,b.print);if(Array.isArray(b.tasteIcons))r.tasteIcons=b.tasteIcons;if(Array.isArray(b.tasteIconSizes))r.tasteIconSizes=b.tasteIconSizes;if(Array.isArray(b.tasteTextSizes))r.tasteTextSizes=b.tasteTextSizes;if(b.blocks)Object.keys(b.blocks).forEach(k=>r.blocks[k]=Object.assign(r.blocks[k]||{},b.blocks[k]));return r}
const initialDesign=parsed();
let state=merge(defaults,initialDesign), active=blockSelect.value;
['logo','badge_logo'].forEach(k=>{
  state.blocks[k]=state.blocks[k]||clone(defaults.blocks[k]);
  if(state.blocks[k].logoTint===undefined){
    state.blocks[k].logoTint=false;
    if((state.blocks[k].color||'').toLowerCase()==='#fff4ea')state.blocks[k].color=defaults.blocks[k].color;
  }
});
const blockHints={
  logo:'Sumber: upload logo utama / galeri logo utama. Ubah Warna lalu aktifkan Tint Logo untuk mengganti warna PNG transparan.',
  badge_logo:'Sumber: upload logo badge / galeri logo badge. Ubah Warna lalu aktifkan Tint Logo untuk mengganti warna PNG transparan.',
  side_ribbon:'Sumber: field Ribbon Kiri. Posisi, ukuran, warna teks, background, dan tinggi ribbon bisa diatur di sini.',
  coffee_name:'Sumber: field Nama Produk.',
  roastery_kicker:'Sumber: field Footer Mini.',
  tasting_notes:'Sumber: builder Tasting Notes + Icon.',
  taste_icons:'Sumber: builder Tasting Notes + Icon. Ukuran utama mengatur ikon, kontrol Teks Note mengatur tulisan note.',
  brew_suggestion:'Sumber: field Brew Suggestion.',
  info_panel:'Sumber: gabungan field Roast Level, Body, Origin, Elevation, Process, Batch, Tanggal Roast, Best Before, Bean/Grind, dan Berat. Warna background panel bisa diatur di sini.'
};
function near(a,b){return Math.abs((parseFloat(a)||0)-b)<0.01}
if((parseFloat(state.blocks?.brew_suggestion?.y)||0)>=49){state.blocks.tasting_notes.y=40;state.blocks.tasting_notes.size=7.8;state.blocks.brew_suggestion.y=46;state.blocks.brew_suggestion.size=6.2}
if(!Array.isArray(initialDesign.tasteIcons)){state.tasteIcons=noteList().map(suggestIcon)}
if(!Array.isArray(initialDesign.tasteIconSizes)){state.tasteIconSizes=noteList().map(()=>18)}
if(!Array.isArray(initialDesign.tasteTextSizes)){state.tasteTextSizes=noteList().map(()=>6.8)}
let forceBlankTasteRow=false;
let dragState=null;
const iconChoices=[
  ['','Tanpa ikon'],
  ['ri-apple-line','Fruity / buah'],
  ['ri-sun-line','Citrus / orange'],
  ['ri-rainbow-line','Tropical / berry'],
  ['ri-drop-line','Juicy / syrupy'],
  ['ri-flower-line','Floral / jasmine'],
  ['ri-leaf-line','Tea / herbal'],
  ['ri-seedling-line','Nutty / almond'],
  ['ri-plant-line','Green / grassy'],
  ['ri-goblet-line','Winey / grape'],
  ['ri-flask-line','Fermented / anaerobic'],
  ['ri-drinks-line','Liqueur / rum'],
  ['ri-bowl-line','Cocoa / chocolate'],
  ['ri-cake-line','Sweet / caramel'],
  ['ri-bread-line','Pastry / biscuit'],
  ['ri-fire-line','Roasty / smoky'],
  ['ri-beer-line','Malty / yeasty'],
  ['ri-sparkling-line','Sparkling / bright'],
  ['ri-mist-line','Clean / delicate'],
  ['ri-contrast-line','Balanced'],
  ['ri-heart-pulse-line','Fresh acidity'],
  ['ri-bar-chart-horizontal-line','Full body'],
  ['ri-time-line','Long finish'],
  ['ri-map-pin-line','Terroir / origin'],
  ['ri-shuffle-line','Layered'],
  ['ri-git-branch-line','Complex'],
  ['ri-fingerprint-line','Signature'],
  ['ri-shield-check-line','Clean cup'],
  ['ri-cup-line','Creamy / milk'],
  ['ri-restaurant-line','Dessert'],
  ['ri-book-open-line','Tea-like'],
  ['ri-gift-line','Candy / sweet']
];
const legacyIconMap={
  'ri-star-line':'ri-sparkling-line',
  'ri-magic-line':'ri-flower-line',
  'ri-price-tag-3-line':'ri-bowl-line',
  'ri-price-tag-2-line':'ri-seedling-line',
  'ri-timer-flash-line':'ri-fire-line',
  'ri-store-2-line':'ri-leaf-line',
  'ri-archive-drawer-line':'ri-fire-line',
  'ri-shopping-bag-3-line':'ri-cake-line',
  'ri-lifebuoy-line':'ri-mist-line',
  'ri-flow-chart':'ri-shuffle-line',
  'ri-box-3-line':'ri-bar-chart-horizontal-line',
  'ri-bank-card-2-line':'ri-cup-line',
  'ri-ticket-2-line':'ri-gift-line',
  'ri-hand-coin-line':'ri-cake-line',
  'ri-vip-crown-line':'ri-fingerprint-line'
};
function suggestIcon(note){
  const t=(note||'').toLowerCase();
  if(/floral|flower|jasmine|melati|rose|mawar|lavender|hibiscus|elderflower|honeysuckle|bouquet/.test(t))return'ri-flower-line';
  if(/citrus|orange|lemon|lime|jeruk|grapefruit|pomelo|yuzu|tangerine|mandarin|bergamot/.test(t))return'ri-sun-line';
  if(/tropical|mango|pineapple|nanas|banana|pisang|papaya|passion|markisa|guava|jambu/.test(t))return'ri-rainbow-line';
  if(/berry|berries|strawberry|raspberry|blueberry|blackberry|cranberry|currant|cherry|ceri|plum|delima|pomegranate/.test(t))return'ri-rainbow-line';
  if(/apple|apel|pear|pir|melon|fruit|fruity|buah/.test(t))return'ri-apple-line';
  if(/peach|persik|apricot|aprikot|nectarine|lychee|leci/.test(t))return'ri-apple-line';
  if(/juicy|syrup|syrupy|sirup|nectar|jam|selai|ripe/.test(t))return'ri-drop-line';
  if(/wine|winey|grape|anggur|red wine|white wine|raisin|kismis/.test(t))return'ri-goblet-line';
  if(/ferment|fermented|anaerobic|carbonic|funky|funk|boozy/.test(t))return'ri-flask-line';
  if(/rum|liqueur|brandy|whisky|whiskey|bourbon|cocktail/.test(t))return'ri-drinks-line';
  if(/choco|chocolate|cocoa|cacao|kakao|coklat|dark chocolate|mocha/.test(t))return'ri-bowl-line';
  if(/nut|nutty|almond|hazelnut|peanut|cashew|walnut|kacang|pecan/.test(t))return'ri-seedling-line';
  if(/tea|teh|black tea|earl|oolong|jasmine tea|white tea/.test(t))return'ri-book-open-line';
  if(/herbal|herb|mint|minty|leaf|lemongrass|serai|sage|basil/.test(t))return'ri-leaf-line';
  if(/green|grassy|grass|vegetal|fresh herb|matcha/.test(t))return'ri-plant-line';
  if(/spice|spicy|cinnamon|kayu manis|clove|cengkeh|ginger|jahe|pepper|lada|rempah|cardamom/.test(t))return'ri-fire-line';
  if(/toffee|caramel|brown sugar|gula aren|palm sugar|honey|madu|molasses|vanilla|maple|sweet|sweetness/.test(t))return'ri-cake-line';
  if(/cake|pastry|biscuit|cookie|wafer|bread|toast|butter|buttery|brioche/.test(t))return'ri-bread-line';
  if(/roast|roasty|smoke|smoky|smoked|burnt|char|tobacco/.test(t))return'ri-fire-line';
  if(/malt|malty|yeast|yeasty|beer/.test(t))return'ri-beer-line';
  if(/sparkling|sparkly|fizzy|bright|vibrant|zesty/.test(t))return'ri-sparkling-line';
  if(/clean|crisp|clarity|transparent|delicate|soft/.test(t))return'ri-mist-line';
  if(/acid|acidity|asam|fresh/.test(t))return'ri-heart-pulse-line';
  if(/creamy|cream|milk|milky|smooth|velvety/.test(t))return'ri-cup-line';
  if(/body|bold|heavy|full|dense|round|mouthfeel/.test(t))return'ri-bar-chart-horizontal-line';
  if(/balanced|balance|harmoni|harmony/.test(t))return'ri-contrast-line';
  if(/finish|aftertaste|long|linger|lingering/.test(t))return'ri-time-line';
  if(/origin|terroir|estate|farm|process lot|micro lot|microlot/.test(t))return'ri-map-pin-line';
  if(/signature|house/.test(t))return'ri-fingerprint-line';
  if(/complex|layer|layered|multi/.test(t))return'ri-git-branch-line';
  return'ri-apple-line';
}
function normalizeTasteIcon(icon,note=''){
  const candidate=legacyIconMap[icon]||icon||'';
  if(iconChoices.some(([val])=>val===candidate))return candidate;
  return suggestIcon(note);
}
function tasteIconSvg(icon){
  const shapes={
    'ri-apple-line':'<path d="M15.8 8.1c2.1 0 4.2 1.8 4.2 5.2 0 4.7-3 7.7-5.1 7.7-1 0-1.7-.5-2.9-.5s-1.9.5-2.9.5C7 21 4 18 4 13.3c0-3.4 2.1-5.2 4.2-5.2 1.3 0 2.4.7 3.8.7s2.5-.7 3.8-.7Z"/><path d="M13.3 6.6c.2-2 1.4-3.3 3.2-4"/>',
    'ri-sun-line':'<circle cx="12" cy="12" r="3.4"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.5 1.5M17.9 17.9l1.5 1.5M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.5-1.5M17.9 6.1l1.5-1.5"/>',
    'ri-rainbow-line':'<path d="M3.5 18a8.5 8.5 0 0 1 17 0"/><path d="M6.5 18a5.5 5.5 0 0 1 11 0"/><path d="M9.5 18a2.5 2.5 0 0 1 5 0"/>',
    'ri-drop-line':'<path d="M12 3.2s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/>',
    'ri-flower-line':'<circle cx="12" cy="12" r="2"/><path d="M12 4.2c2.5 2.1 2.5 3.8 0 5.8-2.5-2-2.5-3.7 0-5.8ZM12 14c2.5 2 2.5 3.7 0 5.8-2.5-2.1-2.5-3.8 0-5.8ZM4.2 12c2.1-2.5 3.8-2.5 5.8 0-2 2.5-3.7 2.5-5.8 0ZM14 12c2-2.5 3.7-2.5 5.8 0-2.1 2.5-3.8 2.5-5.8 0Z"/>',
    'ri-leaf-line':'<path d="M20.5 4.5C12 4.5 5.5 8.7 4 17.8c7.5.5 13.3-3.4 16.5-13.3Z"/><path d="M5 18c4-5.1 8.2-7.9 13-10"/>',
    'ri-seedling-line':'<path d="M12 21V9"/><path d="M12 11C9.5 7 6.5 6 3.8 6.5 4.3 10.2 7.1 12.1 12 11Z"/><path d="M12 14c2.6-4 5.6-5 8.3-4.5-.5 3.7-3.3 5.6-8.3 4.5Z"/>',
    'ri-plant-line':'<path d="M12 21V8"/><path d="M7 21h10"/><path d="M12 10c-3.4-3.6-6.2-3.9-8.8-2.8 1 3.6 4 5 8.8 2.8Z"/><path d="M12 13c3.7-4 6.6-4.3 9.2-3.2-1 3.8-4.2 5.2-9.2 3.2Z"/>',
    'ri-goblet-line':'<path d="M7 3h10l-1 8a4 4 0 0 1-8 0L7 3Z"/><path d="M12 15v5M8.5 21h7"/>',
    'ri-flask-line':'<path d="M9 3h6"/><path d="M10 3v6.5L5.8 18a2 2 0 0 0 1.8 3h8.8a2 2 0 0 0 1.8-3L14 9.5V3"/><path d="M8 16h8"/>',
    'ri-drinks-line':'<path d="M6 3h12l-5 6v10"/><path d="M9 21h6"/><path d="M8 8h8"/><path d="M17 3l3-1"/>',
    'ri-bowl-line':'<path d="M4 11h16a8 8 0 0 1-16 0Z"/><path d="M7 20h10"/><path d="M8 6c0 1-.7 1.4-.7 2.3M12 5c0 1-.7 1.5-.7 2.5M16 6c0 1-.7 1.4-.7 2.3"/>',
    'ri-cake-line':'<path d="M5 11h14v9H5z"/><path d="M5 15c2 1.2 3.5 1.2 5 0s3-1.2 5 0 3 1.2 4 0"/><path d="M12 4v4M9 8h6"/><path d="M12 4c1 1 1 2 0 3-1-1-1-2 0-3Z"/>',
    'ri-bread-line':'<path d="M5 20V9a6 6 0 0 1 12 0 4 4 0 0 1 2 3.5V20H5Z"/><path d="M8 13h8M8 17h8"/>',
    'ri-fire-line':'<path d="M12 21c-3.6 0-6.2-2.5-6.2-6 0-3 1.8-5.1 4.1-7.1.2 2.1 1.1 3.2 2.4 4.2.1-3.6 1.5-6.2 4.2-8.1.3 4 2.7 6.4 2.7 10.8 0 3.6-2.6 6.2-7.2 6.2Z"/><path d="M12 21c-1.5-.8-2.3-2-2.3-3.4 0-1.6.9-2.7 2.3-3.9 1.4 1.2 2.3 2.3 2.3 3.9 0 1.4-.8 2.6-2.3 3.4Z"/>',
    'ri-beer-line':'<path d="M5 7h10v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7Z"/><path d="M15 10h2a3 3 0 0 1 0 6h-2"/><path d="M7 4h6M8 10v7M12 10v7"/>',
    'ri-sparkling-line':'<path d="M12 3l1.8 5 5.2 1.8-5.2 1.8L12 17l-1.8-5.4L5 9.8 10.2 8 12 3Z"/><path d="M5 15l.8 2.2L8 18l-2.2.8L5 21l-.8-2.2L2 18l2.2-.8L5 15ZM19 3l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2Z"/>',
    'ri-mist-line':'<path d="M4 8h10M7 12h13M4 16h11M16 8h4"/>',
    'ri-contrast-line':'<circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 0 0 16Z"/>',
    'ri-heart-pulse-line':'<path d="M20.3 5.8a5 5 0 0 0-7.1 0L12 7l-1.2-1.2a5 5 0 0 0-7.1 7.1L12 21l8.3-8.1a5 5 0 0 0 0-7.1Z"/><path d="M5 13h3l1.5-3 2.2 6 1.8-3H19"/>',
    'ri-bar-chart-horizontal-line':'<path d="M4 18h16M4 13h10M4 8h16"/>',
    'ri-time-line':'<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
    'ri-map-pin-line':'<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.4"/>',
    'ri-shuffle-line':'<path d="M4 7h3c4 0 4.5 10 9 10h4"/><path d="M17 14l3 3-3 3"/><path d="M4 17h3c1.4 0 2.3-1.1 3.1-2.6M14.5 8.4c.5-.8 1-1.4 1.5-1.4h4"/><path d="M17 4l3 3-3 3"/>',
    'ri-git-branch-line':'<circle cx="7" cy="6" r="2.3"/><circle cx="17" cy="18" r="2.3"/><circle cx="7" cy="18" r="2.3"/><path d="M7 8.3V18M9.3 6c4 0 7.7 2.5 7.7 9.7"/>',
    'ri-fingerprint-line':'<path d="M7 11a5 5 0 0 1 10 0c0 3-1 5.5-2.8 7.6M10 20c1.2-2 2-4.8 2-8M7.8 16.5c.5-1.6.8-3.3.8-5.1A3.4 3.4 0 0 1 12 8a3.4 3.4 0 0 1 3.4 3.4M5 15c.3-1.3.5-2.6.5-3.8a6.5 6.5 0 0 1 13 0c0 1.1-.1 2.2-.4 3.2"/>',
    'ri-shield-check-line':'<path d="M12 3l7 3v5.5c0 4.3-2.8 7.5-7 9.5-4.2-2-7-5.2-7-9.5V6l7-3Z"/><path d="M8.8 12.2l2.1 2.1 4.4-4.8"/>',
    'ri-cup-line':'<path d="M5 7h11v6a5 5 0 0 1-10 0V7Z"/><path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M6 21h10"/>',
    'ri-restaurant-line':'<path d="M7 3v8M4 3v8M10 3v8M4 11h6M7 11v10M16 3v18M16 3c3 2 4 5 2 8h-2"/>',
    'ri-book-open-line':'<path d="M4 5.5c3-.8 5.5-.2 8 1.5v13c-2.5-1.7-5-2.3-8-1.5v-13Z"/><path d="M20 5.5c-3-.8-5.5-.2-8 1.5v13c2.5-1.7 5-2.3 8-1.5v-13Z"/>',
    'ri-gift-line':'<path d="M4 10h16v11H4z"/><path d="M4 10h16M12 10v11M6 6.5C6 5.1 7.1 4 8.5 4 11 4 12 10 12 10S7.5 10 6.5 8.8c-.3-.4-.5-.8-.5-1.3ZM18 6.5C18 5.1 16.9 4 15.5 4 13 4 12 10 12 10s4.5 0 5.5-1.2c.3-.4.5-.8.5-1.3Z"/>',
    'ri-star-line':'<path d="M12 3.4l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.7l-5.2 2.7 1-5.8-4.2-4.1 5.8-.8L12 3.4Z"/>',
    'ri-close-line':'<path d="M6 6l12 12M18 6L6 18"/>'
  };
  return '<svg class="taste-svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g vector-effect="non-scaling-stroke">'+(shapes[icon]||shapes['ri-apple-line'])+'</g></svg>';
}
function noteList(){return (tastingNotesValue?.value||'').split(',').map(s=>s.trim()).filter(Boolean)}
const universalDefaults={coffee_name:'Prau Red Wine',footer_note:'Single Origin',origin:'Mt. Prau, Dieng',process_method:'Wine Process',roast_level:'Medium',body_level:'Medium',brew_suggestion:'Filter',elevation_text:'1.200 - 1.500 mdpl',tasting_notes:'Hibiscus, Ripe Peach, Red Wine'};
function universalInputValue(name){const input=formEl?.querySelector('[name="'+name+'"]');return input?(input.value||'').trim():''}
function universalDisplayValue(name){const value=universalInputValue(name);return value!==''?value:(!<?php echo $isEditing ? 'true' : 'false'; ?>?(universalDefaults[name]||''):'')}
function universalSetText(name,value){if(!universalLabel)return;universalLabel.querySelectorAll('[data-universal-output="'+name+'"]').forEach(node=>node.textContent=value)}
function universalSetRow(name,value){if(!universalLabel)return;universalLabel.querySelectorAll('[data-universal-row="'+name+'"]').forEach(node=>node.classList.toggle('is-empty',value===''))}
function universalDate(value){if(!value||!/^\d{4}-\d{2}-\d{2}$/.test(value))return'';const [year,month,day]=value.split('-').map(Number),date=new Date(year,month-1,day);return Number.isNaN(date.getTime())?value:date.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}).toUpperCase()}
function refreshUniversalPreview(){
  if(!universalLabel)return;
  const values={};
  ['coffee_name','footer_note','origin','process_method','roast_level','body_level','brew_suggestion','elevation_text','tasting_notes'].forEach(name=>{values[name]=universalDisplayValue(name);universalSetText(name,values[name])});
  ['origin','elevation_text','process_method','roast_level','body_level','brew_suggestion'].forEach(name=>universalSetRow(name,values[name]));
  if(universalNotes){const notes=values.tasting_notes.split(/[,;|\n]+/).map(note=>note.trim()).filter(Boolean).slice(0,4);universalNotes.innerHTML='';notes.forEach((note,index)=>{const chip=document.createElement('span'),iconWrap=document.createElement('span'),text=document.createElement('span');const icon=normalizeTasteIcon((state.tasteIcons&&state.tasteIcons[index])||suggestIcon(note),note);chip.className='namua-label__note';iconWrap.className='namua-label__note-icon';iconWrap.innerHTML=tasteIconSvg(icon);text.className='namua-label__note-text';text.textContent=note;chip.append(iconWrap,text);universalNotes.appendChild(chip)});universalNotes.style.display=notes.length?'flex':'none'}
  let hasTrace=false;
  ['batch_no','roast_date','expiry_date'].forEach(name=>{const value=name==='roast_date'||name==='expiry_date'?universalDate(universalInputValue(name)):universalInputValue(name);const node=universalLabel.querySelector('[data-universal-trace="'+name+'"]');if(!node)return;const out=node.querySelector('span');if(out)out.textContent=value;node.classList.toggle('is-empty',value==='');hasTrace=hasTrace||value!==''});
  if(universalTrace)universalTrace.style.display=hasTrace?'flex':'none';
  if(universalArtwork&&universalArtworkImage){const hasArtwork=!!(img&&!bg?.classList.contains('no-image')&&(img.currentSrc||img.src));if(hasArtwork){universalArtworkImage.src=img.currentSrc||img.src;universalArtworkImage.style.display='';universalArtwork.classList.remove('no-image')}else{universalArtworkImage.removeAttribute('src');universalArtworkImage.style.display='none';universalArtwork.classList.add('no-image')}}
}
state.tasteIcons=(state.tasteIcons||[]).map((icon,idx)=>normalizeTasteIcon(icon,noteList()[idx]||''));
function syncTasteValueFromRows(){const noteInputs=[...tasteRows.querySelectorAll('[data-taste-note]')];const notes=noteInputs.map(i=>i.value.trim()).filter(Boolean);tastingNotesValue.value=notes.join(', ');state.tasteIcons=noteInputs.map((input,idx)=>{const raw=(input.value||'').trim();const select=input.closest('.taste-row')?.querySelector('[data-taste-icon]');return select?normalizeTasteIcon(select.value||suggestIcon(raw),raw):suggestIcon(raw)}).filter((_,idx)=>notes[idx]!==undefined);state.tasteIconSizes=noteInputs.map(input=>{const raw=(input.value||'').trim();const sizeField=input.closest('.taste-row')?.querySelector('[data-taste-icon-size]');return raw!==''?Math.max(10,Math.min(40,parseFloat(sizeField?.value||18)||18)):null}).filter(v=>v!==null);state.tasteTextSizes=noteInputs.map(input=>{const raw=(input.value||'').trim();const sizeField=input.closest('.taste-row')?.querySelector('[data-taste-text-size]');return raw!==''?Math.max(5,Math.min(20,parseFloat(sizeField?.value||6.8)||6.8)):null}).filter(v=>v!==null);if(state.tasteTextSizes.length){state.blocks.taste_icons=state.blocks.taste_icons||{};state.blocks.taste_icons.textSize=state.tasteTextSizes[0];if(tasteTextControl.input){tasteTextControl.input.value=state.tasteTextSizes[0];tasteTextControl.out.textContent=state.tasteTextSizes[0]+'px'}}refresh()}
function renderTasteIconRow(){const notes=noteList();if(!tasteIconRow)return;tasteIconRow.innerHTML='';if(state.blocks?.taste_icons?.visible===false){tasteIconRow.style.setProperty('display','none','important');return}tasteIconRow.style.removeProperty('display');const globalTextSize=parseFloat(state.blocks?.taste_icons?.textSize||6.8)||6.8;tasteIconRow.style.setProperty('--taste-text-size',globalTextSize+'px');notes.forEach((note,idx)=>{const icon=normalizeTasteIcon((state.tasteIcons&&state.tasteIcons[idx])||suggestIcon(note),note);if(!icon)return;const iconSize=((state.tasteIconSizes&&state.tasteIconSizes[idx])||18);const textSize=((state.tasteTextSizes&&state.tasteTextSizes[idx])||globalTextSize);const span=document.createElement('span');span.title=note;span.style.setProperty('--item-icon-size',iconSize+'px');span.style.setProperty('--item-text-size',textSize+'px');span.innerHTML='<b>'+tasteIconSvg(icon)+'</b><small style="font-size:'+textSize+'px!important">'+(note||'').toUpperCase()+'</small>';tasteIconRow.appendChild(span)});tasteIconRow.style.setProperty('display',tasteIconRow.children.length?'flex':'none','important')}
function renderTasteRows(){const notes=noteList();const list=notes.length?notes:[''];if(forceBlankTasteRow&&notes.length&&list.length<6){list.push('')}forceBlankTasteRow=false;tasteRows.innerHTML='';list.slice(0,6).forEach((note,idx)=>{const row=document.createElement('div');row.className='taste-row';const input=document.createElement('input');input.className='form-control';input.placeholder='Contoh: Apricot';input.value=note;input.setAttribute('data-taste-note','1');const select=document.createElement('select');select.className='form-select';select.setAttribute('data-taste-icon','1');iconChoices.forEach(([val,label])=>{const opt=document.createElement('option');opt.value=val;opt.textContent=label;select.appendChild(opt)});select.value=normalizeTasteIcon((state.tasteIcons&&state.tasteIcons[idx])||suggestIcon(note),note);const iconSizeWrap=document.createElement('label');iconSizeWrap.className='taste-size-input';iconSizeWrap.innerHTML='<span>Ikon</span>';const iconSize=document.createElement('input');iconSize.type='number';iconSize.className='form-control';iconSize.min='10';iconSize.max='40';iconSize.step='0.5';iconSize.value=(state.tasteIconSizes&&state.tasteIconSizes[idx])||18;iconSize.setAttribute('data-taste-icon-size','1');iconSizeWrap.appendChild(iconSize);const textSizeWrap=document.createElement('label');textSizeWrap.className='taste-size-input';textSizeWrap.innerHTML='<span>Teks</span>';const textSize=document.createElement('input');textSize.type='number';textSize.className='form-control';textSize.min='5';textSize.max='20';textSize.step='0.2';textSize.value=(state.tasteTextSizes&&state.tasteTextSizes[idx])||6.8;textSize.setAttribute('data-taste-text-size','1');textSizeWrap.appendChild(textSize);const preview=document.createElement('div');preview.className='taste-icon-preview';const paint=()=>{preview.innerHTML=select.value?'<i class="ri '+select.value+'"></i>':'-'};const remove=document.createElement('button');remove.type='button';remove.className='btn btn-sm btn-outline-danger';remove.innerHTML='<i class="ri ri-close-line"></i>';input.addEventListener('input',()=>{if(!select.dataset.userPicked){select.value=suggestIcon(input.value)}paint();syncTasteValueFromRows()});select.addEventListener('change',()=>{select.dataset.userPicked='1';paint();syncTasteValueFromRows()});iconSize.addEventListener('input',syncTasteValueFromRows);textSize.addEventListener('input',syncTasteValueFromRows);remove.addEventListener('click',()=>{row.remove();syncTasteValueFromRows();if(!tasteRows.children.length){tastingNotesValue.value='';renderTasteRows();refresh()}});preview.addEventListener('click',()=>select.focus());paint();row.appendChild(input);row.appendChild(select);row.appendChild(iconSizeWrap);row.appendChild(textSizeWrap);row.appendChild(preview);row.appendChild(remove);tasteRows.appendChild(row)})}
function renderTasteRowsV2(){
  const notes=noteList(), list=notes.length?notes:[''];
  if(forceBlankTasteRow&&notes.length&&list.length<6){list.push('')}
  forceBlankTasteRow=false;
  tasteRows.innerHTML='';
  list.slice(0,6).forEach((note,idx)=>{
    const row=document.createElement('div');
    row.className='taste-row';
    const input=document.createElement('input');
    input.className='form-control';
    input.placeholder='Contoh: Apricot';
    input.value=note;
    input.setAttribute('data-taste-note','1');
    const select=document.createElement('select');
    select.className='d-none';
    select.setAttribute('data-taste-icon','1');
    iconChoices.forEach(([val,label])=>{
      const opt=document.createElement('option');
      opt.value=val;
      opt.textContent=label;
      select.appendChild(opt);
    });
    select.value=normalizeTasteIcon((state.tasteIcons&&state.tasteIcons[idx])||suggestIcon(note),note);
    const picker=document.createElement('div');
    picker.className='taste-icon-picker';
    const toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='taste-icon-toggle';
    const menu=document.createElement('div');
    menu.className='taste-icon-menu';
    const iconLabel=val=>(iconChoices.find(([v])=>v===val)||['','Tanpa ikon'])[1];
    const paintPicker=()=>{
      const icon=select.value;
      toggle.innerHTML='<span>'+(icon?'<i class="ri '+icon+'"></i>':'<i class="ri ri-close-line"></i>')+'<em>'+iconLabel(icon)+'</em></span><i class="ri ri-arrow-down-s-line"></i>';
      menu.querySelectorAll('.taste-icon-option').forEach(btn=>btn.classList.toggle('active',btn.dataset.icon===icon));
    };
    iconChoices.forEach(([val,label])=>{
      const btn=document.createElement('button');
      btn.type='button';
      btn.className='taste-icon-option';
      btn.dataset.icon=val;
      btn.innerHTML=(val?'<i class="ri '+val+'"></i>':'<i class="ri ri-close-line"></i>')+'<span>'+label+'</span>';
      btn.addEventListener('click',()=>{
        select.value=val;
        select.dataset.userPicked='1';
        picker.classList.remove('open');
        paintPicker();
        syncTasteValueFromRows();
      });
      menu.appendChild(btn);
    });
    toggle.addEventListener('click',()=>{
      document.querySelectorAll('.taste-icon-picker.open').forEach(node=>{if(node!==picker)node.classList.remove('open')});
      picker.classList.toggle('open');
    });
    picker.appendChild(select);
    picker.appendChild(toggle);
    picker.appendChild(menu);
    const iconSizeWrap=document.createElement('label');
    iconSizeWrap.className='taste-size-input';
    iconSizeWrap.innerHTML='<span>Ikon</span>';
    const iconSize=document.createElement('input');
    iconSize.type='number';
    iconSize.className='form-control';
    iconSize.min='10';
    iconSize.max='40';
    iconSize.step='0.5';
    iconSize.value=(state.tasteIconSizes&&state.tasteIconSizes[idx])||18;
    iconSize.setAttribute('data-taste-icon-size','1');
    iconSizeWrap.appendChild(iconSize);
    const textSizeWrap=document.createElement('label');
    textSizeWrap.className='taste-size-input';
    textSizeWrap.innerHTML='<span>Teks</span>';
    const textSize=document.createElement('input');
    textSize.type='number';
    textSize.className='form-control';
    textSize.min='5';
    textSize.max='20';
    textSize.step='0.2';
    textSize.value=(state.tasteTextSizes&&state.tasteTextSizes[idx])||(state.blocks?.taste_icons?.textSize)||6.8;
    textSize.setAttribute('data-taste-text-size','1');
    textSizeWrap.appendChild(textSize);
    const remove=document.createElement('button');
    remove.type='button';
    remove.className='btn btn-sm btn-outline-danger';
    remove.innerHTML='<i class="ri ri-close-line"></i>';
    input.addEventListener('input',()=>{
      if(!select.dataset.userPicked){select.value=suggestIcon(input.value)}
      paintPicker();
      syncTasteValueFromRows();
    });
    iconSize.addEventListener('input',syncTasteValueFromRows);
    textSize.addEventListener('input',syncTasteValueFromRows);
    remove.addEventListener('click',()=>{
      row.remove();
      syncTasteValueFromRows();
      if(!tasteRows.children.length){tastingNotesValue.value='';renderTasteRowsV2();refresh()}
    });
    paintPicker();
    row.appendChild(input);
    row.appendChild(picker);
    row.appendChild(iconSizeWrap);
    row.appendChild(textSizeWrap);
    row.appendChild(remove);
    tasteRows.appendChild(row);
  });
}
function hydrateMetaFields(){metaFields.forEach(f=>{const k=f.dataset.metaField;const legacy=k==='elevation_text'?(state.meta&&state.meta.elevation):'';f.value=(state.meta&&state.meta[k])||legacy||(defaults.meta&&defaults.meta[k])||''})}
function syncMetaFromFields(){state.meta=state.meta||{};metaFields.forEach(f=>{state.meta[f.dataset.metaField]=(f.value||'').trim()})}
function textValue(name,fallback=''){const field=by(name);return ((field&&field.value)||fallback||'').trim()}
function metaValue(name,fallback=''){const field=metaBy(name);return ((field&&field.value)||(state.meta&&state.meta[name])||fallback||'').trim()}
function dotLevel(v){const t=(v||'').toLowerCase().replace(/\s+/g,' ').trim();if(t==='dark'||t==='full'||t==='espresso roast')return 5;if(t==='medium - dark'||t==='medium-dark'||t==='medium - full'||t==='medium-full'||t==='omni roast')return 4;if(t==='medium'||t==='filter roast')return 3;if(t==='light - medium'||t==='light-medium')return 2;if(t==='light')return 1;return 3}
function paintDots(key,val){const wrap=document.querySelector('[data-info-dots="'+key+'"]');if(!wrap)return;const level=dotLevel(val);wrap.innerHTML='';for(let i=1;i<=5;i++){const dot=document.createElement('span');dot.className='info-dot'+(i<=level?' filled':'');wrap.appendChild(dot)}}
function setInfo(key,value){document.querySelectorAll('[data-info-value="'+key+'"]').forEach(e=>e.textContent=value)}
function renderInfoPanel(){const blank='\u00a0',roast=textValue('roast_level','Medium')||'Medium',body=metaValue('body_level','Medium')||'Medium',origin=textValue('origin','Gunung Prau, Dieng, Jawa Tengah')||'Gunung Prau, Dieng, Jawa Tengah',elevation=metaValue('elevation_text','1.400 - 1.800 mdpl')||'1.400 - 1.800 mdpl',process=textValue('process_method','Wine')||'Wine',bean=metaValue('bean_type','Whole Bean')||'Whole Bean',weight=textValue('weight_text','200 g')||'200 g',footer=metaValue('footer_note','Single Origin')||'Single Origin',batch=textValue('batch_no','1')||'1',roasted=textValue('roast_date',''),bestBefore=textValue('expiry_date','');setInfo('roast_level',roast);setInfo('body_level',body);setInfo('origin',origin);setInfo('elevation',elevation);setInfo('process_method',process);setInfo('bean_type',bean);setInfo('weight_text',weight);setInfo('footer_note',footer);setInfo('batch_no',batch||blank);setInfo('roast_date',roasted||blank);setInfo('expiry_date',bestBefore||blank);document.querySelectorAll('[data-info-value="footer_note"]').forEach(e=>{if(e.dataset.block&&state.blocks?.[e.dataset.block]?.visible===false){e.style.setProperty('display','none','important');return}e.style.setProperty('display',footer?'block':'none','important')});paintDots('roast_level',roast);paintDots('body_level',body)}
function val(n){const v=(by(n)?.value||'').trim(); if(n==='coffee_name')return v||'WINE PRAU'; if(n==='origin')return v||'Gunung Prau, Dieng, Jawa Tengah'; if(n==='process_method')return v||'Wine'; if(n==='roast_level')return v||'Medium'; if(n==='weight_text')return v||'200 g'; if(n==='tasting_notes')return v||'HIBISCUS, RIPE PEACH, RED WINE'; if(n==='brew_suggestion')return v||'FILTER'; if(n==='batch_no')return v||'1'; if(n==='roast_date')return v; if(n==='expiry_date')return v||''; if(n==='description')return v||metaValue('footer_note','Single Origin'); return v}
function blockValue(n){if(n==='side_ribbon')return metaValue('ribbon_text',metaValue('footer_note','Single Origin')); if(n==='roastery_kicker')return metaValue('footer_note','Single Origin'); return val(n)}
function getBlockElement(n){if(n==='info_panel')return document.querySelector('.label-info-panel[data-block="info_panel"]'); if(n==='logo')return document.querySelector('.label-logo-main[data-block="logo"]'); if(n==='badge_logo')return document.querySelector('.label-badge-logo[data-block="badge_logo"]'); if(n==='side_ribbon')return document.querySelector('.label-side-ribbon[data-block="side_ribbon"]'); if(n==='taste_icons')return document.querySelector('.taste-icon-row[data-block="taste_icons"]'); if(n==='roastery_kicker')return document.querySelector('.label-roastery-kicker[data-block="roastery_kicker"]'); return document.querySelector('.label-text[data-block="'+n+'"]')}
function clamp(v,min,max){return Math.min(Math.max(v,min),max)}
function applyVisibility(node,n,s){
  const visible=s.visible!==false;
  node.classList.toggle('is-hidden-by-user',!visible);
  if(!visible){
    node.style.setProperty('display','none','important');
    node.classList.remove('active');
    return false;
  }
  node.style.removeProperty('display');
  if(hiddenByDefaultBlocks.includes(n)){
    node.style.setProperty('display','block','important');
  }
  return true;
}
function updateVisibilityButton(s){
  if(!toggleBlockVisible)return;
  const visible=s.visible!==false;
  toggleBlockVisible.classList.toggle('btn-success',visible);
  toggleBlockVisible.classList.toggle('btn-outline-dark',!visible);
  toggleBlockVisible.querySelector('i').className='ri '+(visible?'ri-eye-line':'ri-eye-off-line')+' me-1';
  toggleBlockVisible.querySelector('span').textContent=visible?'Tampil':'Sembunyi';
}
function syncElementToggles(){
  document.querySelectorAll('[data-element-toggle]').forEach(button=>{
    const key=button.dataset.elementToggle;
    const visible=state.blocks?.[key]?.visible!==false;
    button.classList.toggle('is-hidden',!visible);
    const icon=button.querySelector('i');
    if(icon)icon.className='ri '+(visible?'ri-eye-line':'ri-eye-off-line');
    button.title=visible?'Klik untuk menyembunyikan elemen':'Klik untuk menampilkan elemen';
  });
}
function getBlockMetrics(n){const node=getBlockElement(n);if(!node)return null;const canvasRect=canvas.getBoundingClientRect(),nodeRect=node.getBoundingClientRect(),s=Object.assign({},defaults.blocks[n]||{},state.blocks[n]||{});return{node,canvasRect,nodeRect,leftPx:nodeRect.left-canvasRect.left,topPx:nodeRect.top-canvasRect.top,widthPx:nodeRect.width,heightPx:nodeRect.height,canvasWidth:canvasRect.width,canvasHeight:canvasRect.height,xPct:+(s.x||0),yPct:+(s.y||0),wPct:+(s.w||50),hPct:+(s.h||30)}}
function hideGuides(){if(!guides.wrap)return;guides.wrap.classList.remove('is-active');[guides.centerV,guides.centerH,guides.currentV,guides.currentH].forEach(g=>g&&g.classList.remove('is-snap'))}
function showGuides(n){if(!guides.wrap)return;const m=getBlockMetrics(n);if(!m)return;const centerX=m.leftPx+(m.widthPx/2),centerY=m.topPx+(m.heightPx/2),canvasCenterX=m.canvasWidth/2,canvasCenterY=m.canvasHeight/2,snapX=Math.abs(centerX-canvasCenterX)<=8,snapY=Math.abs(centerY-canvasCenterY)<=8;guides.wrap.classList.add('is-active');guides.centerV.style.left=canvasCenterX+'px';guides.centerH.style.top=canvasCenterY+'px';guides.currentV.style.left=centerX+'px';guides.currentH.style.top=centerY+'px';guides.centerV.classList.toggle('is-snap',snapX);guides.currentV.classList.toggle('is-snap',snapX);guides.centerH.classList.toggle('is-snap',snapY);guides.currentH.classList.toggle('is-snap',snapY);guides.badge.textContent='X '+m.xPct.toFixed(1)+'% · Y '+m.yPct.toFixed(1)+'%'}
function beginDrag(evt,n){const metrics=getBlockMetrics(n);if(!metrics)return;active=n;blockSelect.value=n;load();const refreshed=getBlockMetrics(n);if(!refreshed)return;dragState={block:n,pointerId:evt.pointerId,startClientX:evt.clientX,startClientY:evt.clientY,startLeftPx:refreshed.leftPx,startTopPx:refreshed.topPx,nodeWidth:refreshed.widthPx,nodeHeight:refreshed.heightPx,canvasWidth:refreshed.canvasWidth,canvasHeight:refreshed.canvasHeight};if(refreshed.node.setPointerCapture&&evt.pointerId!==undefined){try{refreshed.node.setPointerCapture(evt.pointerId)}catch(e){}}showGuides(n);evt.preventDefault()}
function moveDrag(evt){if(!dragState)return;const dx=evt.clientX-dragState.startClientX,dy=evt.clientY-dragState.startClientY;const allowOverflow=dragState.block==='side_ribbon';const minLeft=allowOverflow?-(dragState.nodeWidth*.22):0;const minTop=allowOverflow?-(dragState.nodeHeight*.22):0;const maxLeft=allowOverflow?(dragState.canvasWidth-dragState.nodeWidth)+(dragState.nodeWidth*.08):Math.max(0,dragState.canvasWidth-dragState.nodeWidth);const maxTop=allowOverflow?(dragState.canvasHeight-dragState.nodeHeight)+(dragState.nodeHeight*.08):Math.max(0,dragState.canvasHeight-dragState.nodeHeight);let left=clamp(dragState.startLeftPx+dx,minLeft,maxLeft),top=clamp(dragState.startTopPx+dy,minTop,maxTop);const centerTolerance=8;const centerX=left+(dragState.nodeWidth/2),centerY=top+(dragState.nodeHeight/2),canvasCenterX=dragState.canvasWidth/2,canvasCenterY=dragState.canvasHeight/2;if(Math.abs(centerX-canvasCenterX)<=centerTolerance){left=clamp(canvasCenterX-(dragState.nodeWidth/2),minLeft,maxLeft)}if(Math.abs(centerY-canvasCenterY)<=centerTolerance){top=clamp(canvasCenterY-(dragState.nodeHeight/2),minTop,maxTop)}const s=state.blocks[dragState.block]||(state.blocks[dragState.block]={});s.x=+((left/dragState.canvasWidth)*100).toFixed(3);s.y=+((top/dragState.canvasHeight)*100).toFixed(3);c.posX.value=s.x;c.posY.value=s.y;outs();refresh();showGuides(dragState.block);evt.preventDefault()}
function endDrag(){if(!dragState)return;const node=getBlockElement(dragState.block);if(node&&node.releasePointerCapture&&dragState.pointerId!==undefined){try{node.releasePointerCapture(dragState.pointerId)}catch(e){}}dragState=null;hideGuides()}
function bindDragHandles(){document.querySelectorAll('[data-block]').forEach(node=>{if(node.dataset.dragBound==='1')return;node.dataset.dragBound='1';node.addEventListener('pointerdown',function(evt){if(evt.button!==undefined&&evt.button!==0)return;beginDrag(evt,this.dataset.block)})})}
function normalizeHex(hex,fallback='#fff4ec'){
  const raw=(hex||'').trim();
  if(/^#[0-9a-f]{6}$/i.test(raw))return raw;
  if(/^#[0-9a-f]{3}$/i.test(raw))return '#'+raw.slice(1).split('').map(ch=>ch+ch).join('');
  return fallback;
}
function hexToRgba(hex,alpha=1){
  const h=normalizeHex(hex);
  const r=parseInt(h.slice(1,3),16),g=parseInt(h.slice(3,5),16),b=parseInt(h.slice(5,7),16);
  return 'rgba('+r+','+g+','+b+','+alpha+')';
}
function hexToRgb(hex,fallback='#7a0f2b'){
  const h=normalizeHex(hex,fallback);
  return{r:parseInt(h.slice(1,3),16),g:parseInt(h.slice(3,5),16),b:parseInt(h.slice(5,7),16)};
}
function svgTintFilter(hex){
  const rgb=hexToRgb(hex),r=(rgb.r/255).toFixed(4),g=(rgb.g/255).toFixed(4),b=(rgb.b/255).toFixed(4);
  const svg='<svg xmlns="http://www.w3.org/2000/svg"><filter id="t" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0 0 0 0 '+r+' 0 0 0 0 '+g+' 0 0 0 0 '+b+' 0 0 0 1 0"/></filter></svg>';
  return 'url("data:image/svg+xml;utf8,'+encodeURIComponent(svg)+'#t")';
}
function applyColor(node,color){node.style.setProperty('color',color,'important')}
function applyBg(node,color,alpha=1){node.style.setProperty('background',hexToRgba(color,alpha),'important')}
function applyLogoTint(node,s,n){
  const shadow=n==='logo'?'drop-shadow(0 12px 20px rgba(105,28,30,.18))':'drop-shadow(0 6px 12px rgba(84,21,24,.16))';
  const filter=s.logoTint?svgTintFilter(s.color||'#7a0f2b')+' '+shadow:shadow;
  node.style.setProperty('filter',filter,'important');
}
const tintCache=new Map();
const PRINT_LOGO_DPI=600;
const PRINT_PREVIEW_PX_PER_MM=4;
const PRINT_CSS_PX_PER_MM=96/25.4;
function imageReady(img){
  if(!img)return Promise.resolve(false);
  if(img.complete&&img.naturalWidth>0)return Promise.resolve(true);
  return new Promise(resolve=>{
    const done=()=>resolve(!!(img.naturalWidth>0));
    img.addEventListener('load',done,{once:true});
    img.addEventListener('error',()=>resolve(false),{once:true});
  });
}
function loadTintSource(src){
  return new Promise(resolve=>{
    const img=new Image();
    img.crossOrigin='anonymous';
    img.onload=()=>resolve(img);
    img.onerror=()=>resolve(null);
    img.src=src;
  });
}
async function rasterLogoDataUrl(sourceImg,options={}){
  if(!sourceImg)return null;
  await imageReady(sourceImg);
  const src=sourceImg.currentSrc||sourceImg.src||sourceImg.getAttribute('src')||'';
  if(!src)return null;
  const tint=!!options.tint;
  const color=normalizeHex(options.color||'#7a0f2b','#7a0f2b');
  const targetWidth=Math.max(1,Math.round(options.targetWidthPx||0));
  const key=src+'|'+(tint?color:'original')+'|'+targetWidth;
  if(tintCache.has(key))return tintCache.get(key);
  const img=sourceImg.naturalWidth?sourceImg:(await loadTintSource(src));
  if(!img||!img.naturalWidth||!img.naturalHeight)return null;
  try{
    const width=targetWidth||img.naturalWidth;
    const height=Math.max(1,Math.round(width*(img.naturalHeight/img.naturalWidth)));
    const canvasLogo=document.createElement('canvas');
    canvasLogo.width=width;
    canvasLogo.height=height;
    const ctx=canvasLogo.getContext('2d');
    ctx.imageSmoothingEnabled=true;
    ctx.imageSmoothingQuality='high';
    ctx.drawImage(img,0,0,width,height);
    if(tint){
      const data=ctx.getImageData(0,0,canvasLogo.width,canvasLogo.height);
      const rgb=hexToRgb(color,'#7a0f2b');
      for(let i=0;i<data.data.length;i+=4){
        if(data.data[i+3]>0){
          data.data[i]=rgb.r;
          data.data[i+1]=rgb.g;
          data.data[i+2]=rgb.b;
        }
      }
      ctx.putImageData(data,0,0);
    }
    const out=canvasLogo.toDataURL('image/png');
    tintCache.set(key,out);
    return out;
  }catch(e){
    return null;
  }
}
function printLogoTargetWidthPx(block,s){
  const labelW=Math.max(40,Math.min(160,parseInt(canvasWidthEl.value||90,10)));
  const defaultW=block==='logo'?44:14;
  const base=block==='logo'?24:18;
  const widthMm=labelW*((s.w||defaultW)/100);
  const scale=clamp((s.size||base)/base,.5,3);
  const minPx=block==='logo'?900:520;
  return Math.min(2400,Math.max(minPx,Math.ceil(widthMm*scale*PRINT_LOGO_DPI/25.4)));
}
function originalLogoSource(sourceImg){
  return sourceImg.currentSrc||sourceImg.src||sourceImg.getAttribute('src')||'';
}
async function waitForPrintAssets(root){
  if(document.fonts&&document.fonts.ready){
    try{await document.fonts.ready}catch(e){}
  }
  const images=[...root.querySelectorAll('img')];
  if(images.length){
    await Promise.all(images.map(imageReady));
  }
}
function iconClassFromNode(node){
  return [...node.classList].find(cls=>cls!=='ri'&&cls.indexOf('ri-')===0)||'ri-star-line';
}
function replaceIconWithSvg(node,icon){
  const wrap=document.createElement('span');
  wrap.innerHTML=tasteIconSvg(icon).replace('class="taste-svg-icon"','class="taste-svg-icon print-vector-icon"');
  if(wrap.firstElementChild)node.replaceWith(wrap.firstElementChild);
}
function materializeVectorIcons(root){
  root.querySelectorAll('.taste-icon-row b i[class*="ri-"]').forEach(node=>replaceIconWithSvg(node,iconClassFromNode(node)));
  root.querySelectorAll('.label-side-ribbon i[class*="ri-"]').forEach(node=>replaceIconWithSvg(node,iconClassFromNode(node)));
}
function applyTextShadow(node,s){
  const enabled=s.shadow!==undefined?!!s.shadow:false;
  node.style.setProperty('text-shadow',enabled?'0 2px 8px rgba(0,0,0,.58), 0 0 2px rgba(0,0,0,.42)':'none','important');
}
function apply(n){
  const s=state.blocks[n]||{}, node=getBlockElement(n);
  if(!node)return;
  if(!applyVisibility(node,n,s))return;
  if(n==='info_panel'){
    node.style.left=(s.x||10.5)+'%';
    node.style.top=(s.y||69.2)+'%';
    node.style.right='auto';
    node.style.bottom='auto';
    node.style.width=(s.w||79)+'%';
    node.style.height=(s.h||24.5)+'%';
    node.style.fontFamily='"'+(s.font||'Space Grotesk')+'",sans-serif';
    node.style.fontSize=(s.size||6.5)+'px';
    applyColor(node,s.color||'#6e2b2d');
    applyBg(node,s.bgColor||'#fff4ec',.88);
    node.style.letterSpacing=(s.letter||.03)+'px';
    node.style.textShadow='none';
    node.classList.toggle('active',n===active);
    return;
  }
  if(n==='logo'||n==='badge_logo'){
    const base=n==='logo'?24:18,scale=(s.size||base)/base;
    node.style.left=(s.x||0)+'%';
    node.style.top=(s.y||0)+'%';
    node.style.width=(s.w||(n==='logo'?44:14))+'%';
    node.style.height='auto';
    node.style.transform='scale('+scale+')';
    node.style.transformOrigin='top left';
    applyLogoTint(node,s,n);
    node.classList.toggle('active',n===active);
    return;
  }
  if(n==='side_ribbon'){
    const label=node.querySelector('span');
    node.style.left=(s.x||2.1)+'%';
    node.style.top=(s.y||.8)+'%';
    node.style.width=(s.w||8.5)+'%';
    node.style.height=(s.h||21.5)+'%';
    applyColor(node,s.color||'#fff6ea');
    applyBg(node,s.bgColor||'#7a3d46',.78);
    applyTextShadow(node,s);
    node.style.fontFamily='"'+(s.font||'Space Grotesk')+'",sans-serif';
    node.style.setProperty('--ribbon-text-size',(s.size||9)+'px');
    node.style.setProperty('--ribbon-icon-size',((s.size||9)*2)+'px');
    node.style.setProperty('--ribbon-letter-spacing',(s.letter||4.2));
    if(label){
      const text=blockValue(n)||'Single Origin';
      label.textContent=s.uppercase?text.toUpperCase():text;
    }
    node.classList.toggle('active',n===active);
    return;
  }
  if(n==='taste_icons'){
    node.style.left=(s.x||16)+'%';
    node.style.top=(s.y||54.1)+'%';
    node.style.right='auto';
    node.style.width=(s.w||68)+'%';
    node.style.fontSize=(s.size||18)+'px';
    node.style.setProperty('--taste-icon-size',(s.size||18)+'px');
    applyColor(node,s.color||'#fff6ea');
    applyTextShadow(node,s);
    node.style.justifyContent=s.align==='left'?'flex-start':(s.align==='right'?'flex-end':'center');
    node.classList.toggle('active',n===active);
    return;
  }
  node.textContent=s.uppercase?blockValue(n).toUpperCase():blockValue(n);
  node.style.left=(s.x||0)+'%';
  node.style.top=(s.y||0)+'%';
  node.style.right='auto';
  node.style.width=(s.w||50)+'%';
  node.style.fontSize=(s.size||12)+'px';
  node.style.fontFamily='"'+(s.font||'Jost')+'",sans-serif';
  applyColor(node,s.color||'#2c1711');
  applyTextShadow(node,s);
  node.style.fontWeight=s.bold?'900':'500';
  node.style.fontStyle=s.italic?'italic':'normal';
  node.style.textAlign=s.align||'left';
  node.style.letterSpacing=(s.letter||0)+'px';
  node.classList.toggle('active',n===active);
}
function paperPresetSize(paper,orientation){let w=210,h=297;if(paper==='A3'){w=297;h=420}if(orientation==='landscape'){const tmp=w;w=h;h=tmp}return{w,h}}
function normalizedPrintState(){
  const base=Object.assign({},defaults.print,state.print||{});
  const preset=paperPresetSize(base.paper,base.orientation);
  if(base.paper!=='CUSTOM'){base.paperW=preset.w;base.paperH=preset.h}
  base.paperW=clamp(parseFloat(base.paperW)||preset.w,80,500);
  base.paperH=clamp(parseFloat(base.paperH)||preset.h,80,500);
  base.perSheet=Math.max(1,Math.min(40,parseInt(base.perSheet||4,10)||4));
  base.margin=clamp(parseFloat(base.margin)||0,0,40);
  base.gap=clamp(parseFloat(base.gap)||0,0,30);
  base.cutLine=!!base.cutLine;
  return base;
}
function syncPrintControlsFromState(){
  state.print=normalizedPrintState();
  if(!printControls.paper)return;
  printControls.paper.value=state.print.paper||'A4';
  printControls.orientation.value=state.print.orientation||'portrait';
  printControls.paperW.value=state.print.paperW;
  printControls.paperH.value=state.print.paperH;
  printControls.paperW.readOnly=state.print.paper!=='CUSTOM';
  printControls.paperH.readOnly=state.print.paper!=='CUSTOM';
  printControls.perSheet.value=state.print.perSheet;
  printControls.margin.value=state.print.margin;
  printControls.gap.value=state.print.gap;
  printControls.cutLine.checked=!!state.print.cutLine;
}
function syncPrintStateFromControls(){
  if(!printControls.paper)return;
  state.print=state.print||clone(defaults.print);
  state.print.paper=printControls.paper.value||'A4';
  state.print.orientation=printControls.orientation.value||'portrait';
  state.print.paperW=parseFloat(printControls.paperW.value)||210;
  state.print.paperH=parseFloat(printControls.paperH.value)||297;
  state.print.perSheet=parseInt(printControls.perSheet.value||4,10)||4;
  state.print.margin=parseFloat(printControls.margin.value)||0;
  state.print.gap=parseFloat(printControls.gap.value)||0;
  state.print.cutLine=!!printControls.cutLine.checked;
  state.print=normalizedPrintState();
}
function printCapacity(print,w,h){
  const usableW=Math.max(1,print.paperW-(print.margin*2));
  const usableH=Math.max(1,print.paperH-(print.margin*2));
  const cols=Math.max(1,Math.floor((usableW+print.gap)/(w+print.gap)));
  const rows=Math.max(1,Math.floor((usableH+print.gap)/(h+print.gap)));
  return{cols,rows,total:cols*rows};
}
function updatePrintPreviewState(){
  syncPrintStateFromControls();
  const w=Math.max(40,Math.min(160,parseInt(canvasWidthEl.value||90,10))),h=Math.max(60,Math.min(240,parseInt(canvasHeightEl.value||140,10)));
  const cap=printCapacity(state.print,w,h);
  const count=Math.min(state.print.perSheet,cap.total);
  if(previewShell)previewShell.classList.toggle('show-cut-line',!!state.print.cutLine);
  if(printFitBadge)printFitBadge.textContent=count+' / '+cap.total+' muat';
}
function ensurePrintPortal(){
  let portal=el('printSheetPortal');
  if(!portal){
    portal=document.createElement('div');
    portal.id='printSheetPortal';
    portal.className='coffee-label-page print-sheet print-sheet-portal';
    portal.setAttribute('aria-hidden','true');
    document.body.appendChild(portal);
  }else if(!portal.classList.contains('coffee-label-page')){
    portal.classList.add('coffee-label-page');
  }
  return portal;
}
async function materializePrintClone(cloneCanvas){
  materializeVectorIcons(cloneCanvas);
  const logoPairs=[
    ['logo','.label-logo-main[data-block="logo"]'],
    ['badge_logo','.label-badge-logo[data-block="badge_logo"]']
  ];
  for(const [block,selector] of logoPairs){
    const s=state.blocks?.[block]||{};
    const liveLogo=getBlockElement(block);
    const cloneLogo=cloneCanvas.querySelector(selector);
    if(!liveLogo||!cloneLogo)continue;
    const originalSrc=originalLogoSource(liveLogo);
    const printLogo=s.logoTint
      ? await rasterLogoDataUrl(liveLogo,{
          tint:true,
          color:s.color||'#7a0f2b',
          targetWidthPx:printLogoTargetWidthPx(block,s)
        })
      : originalSrc;
    if(printLogo){
      cloneLogo.src=printLogo;
      cloneLogo.removeAttribute('srcset');
    }
    cloneLogo.style.setProperty('filter','none','important');
    cloneLogo.style.setProperty('image-rendering','auto','important');
  }
}
async function buildPrintSheet(){
  updatePrintPreviewState();
  const target=ensurePrintPortal();
  const w=Math.max(40,Math.min(160,parseInt(canvasWidthEl.value||90,10))),h=Math.max(60,Math.min(240,parseInt(canvasHeightEl.value||140,10)));
  const designW=w*PRINT_PREVIEW_PX_PER_MM,designH=h*PRINT_PREVIEW_PX_PER_MM,printScale=PRINT_CSS_PX_PER_MM/PRINT_PREVIEW_PX_PER_MM;
  const cap=printCapacity(state.print,w,h);
  const count=Math.min(state.print.perSheet,cap.total);
  const pageStyle=el('labelPrintPageStyle')||document.head.appendChild(document.createElement('style'));
  pageStyle.id='labelPrintPageStyle';
  pageStyle.textContent='@page{size:'+state.print.paperW+'mm '+state.print.paperH+'mm;margin:0}';
  document.body.style.setProperty('--print-paper-w',state.print.paperW+'mm');
  document.body.style.setProperty('--print-paper-h',state.print.paperH+'mm');
  document.body.style.setProperty('--print-margin',state.print.margin+'mm');
  document.body.style.setProperty('--print-gap',state.print.gap+'mm');
  document.body.style.setProperty('--print-cols',cap.cols);
  document.body.style.setProperty('--label-print-w',w+'mm');
  document.body.style.setProperty('--label-print-h',h+'mm');
  document.body.style.setProperty('--label-design-w',designW+'px');
  document.body.style.setProperty('--label-design-h',designH+'px');
  document.body.style.setProperty('--label-print-scale',printScale.toFixed(8));
  if(printSheet)printSheet.innerHTML='';
  target.innerHTML='';
  for(let i=0;i<count;i++){
    const slot=document.createElement('div');
    slot.className='print-label-slot'+(state.print.cutLine?' cut-line':'');
    const cloneCanvas=canvas.cloneNode(true);
    cloneCanvas.removeAttribute('id');
    cloneCanvas.querySelectorAll('[id]').forEach(node=>node.removeAttribute('id'));
    cloneCanvas.querySelectorAll('.active').forEach(node=>node.classList.remove('active'));
    cloneCanvas.querySelectorAll('.drag-guides').forEach(node=>node.remove());
    cloneCanvas.style.setProperty('--label-preview-w',designW+'px');
    cloneCanvas.style.setProperty('--label-preview-h',designH+'px');
    cloneCanvas.style.setProperty('--label-print-w',w+'mm');
    cloneCanvas.style.setProperty('--label-print-h',h+'mm');
    cloneCanvas.style.setProperty('--label-design-w',designW+'px');
    cloneCanvas.style.setProperty('--label-design-h',designH+'px');
    cloneCanvas.style.setProperty('--label-print-scale',printScale.toFixed(8));
    await materializePrintClone(cloneCanvas);
    slot.appendChild(cloneCanvas);
    target.appendChild(slot);
  }
  await waitForPrintAssets(target);
}
function syncSizeControls(w,h){canvasWidthEl.value=w;canvasHeightEl.value=h;labelWidthRange.value=w;labelHeightRange.value=h;labelWidthOut.textContent=w+'mm';labelHeightOut.textContent=h+'mm'}
function refresh(){syncMetaFromFields();const w=Math.max(40,Math.min(160,parseInt(canvasWidthEl.value||90,10))),h=Math.max(60,Math.min(240,parseInt(canvasHeightEl.value||140,10))),t=themePresetEl.value||'heritage-cream',m=artworkModeEl.value||'full',fit=artworkFitEl?.value||'stretch',p=patternModeEl?.value||'contour'; syncSizeControls(w,h); state.canvas.width=w;state.canvas.height=h;state.canvas.theme=t; state.canvas.artworkMode=m; state.canvas.artworkFit=fit; state.canvas.patternMode=p; canvas.style.setProperty('--label-preview-w',(w*PRINT_PREVIEW_PX_PER_MM)+'px'); canvas.style.setProperty('--label-preview-h',(h*PRINT_PREVIEW_PX_PER_MM)+'px'); canvas.style.setProperty('--label-print-w',w+'mm'); canvas.style.setProperty('--label-print-h',h+'mm'); canvas.className='label-canvas theme-'+t+' artwork-mode-'+m+' artwork-fit-'+fit+' pattern-mode-'+p; Object.keys(state.blocks).forEach(apply); renderTasteIconRow(); renderInfoPanel(); updatePrintPreviewState();syncElementToggles();if(isUniversalTemplate)refreshUniversalPreview();designInput.value=JSON.stringify(state);bindDragHandles()}
function outs(){o.fontSize.textContent=c.fontSize.value+'px';o.posX.textContent=c.posX.value+'%';o.posY.textContent=c.posY.value+'%';o.blockWidth.textContent=c.blockWidth.value+'%';o.panelHeight.textContent=c.panelHeight.value+'%';o.letterSpacing.textContent=c.letterSpacing.value+'px'}
function updateTasteTextControl(s){if(!tasteTextControl.input)return;const enabled=active==='taste_icons';tasteTextControl.wrap.style.display=enabled?'grid':'none';tasteTextControl.wrap.style.opacity=enabled?'1':'.45';const size=parseFloat(s.textSize||state.blocks?.taste_icons?.textSize||6.8)||6.8;tasteTextControl.input.value=size;tasteTextControl.out.textContent=size+'px'}
function setGlobalTasteTextSize(size){size=Math.max(5,Math.min(20,parseFloat(size)||6.8));state.blocks.taste_icons=state.blocks.taste_icons||{};state.blocks.taste_icons.textSize=size;state.tasteTextSizes=noteList().map(()=>size);tasteRows.querySelectorAll('[data-taste-text-size]').forEach(input=>{input.value=size});if(tasteIconRow)tasteIconRow.style.setProperty('--taste-text-size',size+'px');if(tasteTextControl.out)tasteTextControl.out.textContent=size+'px';refresh()}
function load(){
  const fallback=defaults.blocks[active]||{},s=Object.assign({},fallback,state.blocks[active]||{});
  c.fontFamily.value=s.font||fallback.font||'Jost';
  c.fontColor.value=s.color||fallback.color||'#7d1720';
  if(c.bgColor){
    const bgEnabled=active==='info_panel'||active==='side_ribbon';
    c.bgColor.value=normalizeHex(s.bgColor||fallback.bgColor||(active==='side_ribbon'?'#7a3d46':'#fff4ec'));
    c.bgColor.disabled=!bgEnabled;
    c.bgColor.closest('div').style.opacity=bgEnabled?'1':'.45';
  }
  c.fontSize.value=s.size||fallback.size||12;
  c.posX.value=(s.x!==undefined?s.x:(fallback.x||0));
  c.posY.value=(s.y!==undefined?s.y:(fallback.y||0));
  c.blockWidth.value=s.w||fallback.w||50;
  c.panelHeight.value=s.h||fallback.h||28;
  c.letterSpacing.value=(s.letter!==undefined?s.letter:(fallback.letter||0));
  if(blockSourceHint){
    blockSourceHint.textContent=blockHints[active]||'Sumber: elemen visual label.';
  }
  updateVisibilityButton(s);
  if(c.panelHeight){
    const heightEnabled=active==='info_panel'||active==='side_ribbon';
    c.panelHeight.disabled=!heightEnabled;
    c.panelHeight.closest('.range-line').style.opacity=heightEnabled?'1':'.45';
  }
  updateTasteTextControl(s);
  outs();
  refresh();
}
function save(sourceControl){
  const s=state.blocks[active]||(state.blocks[active]={});
  const previousSize=+s.size||+(defaults.blocks[active]?.size)||12;
  s.font=c.fontFamily.value;
  s.color=c.fontColor.value;
  if((active==='logo'||active==='badge_logo')&&sourceControl===c.fontColor){
    s.logoTint=true;
  }
  if(c.bgColor&&(active==='info_panel'||active==='side_ribbon')){
    s.bgColor=c.bgColor.value;
  }
  s.size=+c.fontSize.value||12;
  s.x=+c.posX.value||0;
  s.y=+c.posY.value||0;
  s.w=+c.blockWidth.value||50;
  if(active==='info_panel'||active==='side_ribbon')s.h=+c.panelHeight.value||(active==='info_panel'?30:20.5);
  if(active==='taste_icons'){
    const currentText=+(tasteTextControl.input?.value||s.textSize||6.8)||6.8;
    const ratio=previousSize>0?s.size/previousSize:1;
    const nextText=Math.max(5,Math.min(20,+(currentText*ratio).toFixed(1)));
    s.textSize=nextText;
    state.tasteTextSizes=noteList().map(()=>nextText);
    tasteRows.querySelectorAll('[data-taste-text-size]').forEach(input=>{input.value=nextText});
    if(tasteTextControl.input){
      tasteTextControl.input.value=nextText;
      tasteTextControl.out.textContent=nextText+'px';
    }
    if(tasteIconRow)tasteIconRow.style.setProperty('--taste-text-size',nextText+'px');
  }
  s.letter=+c.letterSpacing.value||0;
  refresh();
}
fields.forEach(e=>{e.addEventListener('input',refresh);e.addEventListener('change',refresh)});metaFields.forEach(e=>{e.addEventListener('input',refresh);e.addEventListener('change',refresh)});[canvasWidthEl,canvasHeightEl,themePresetEl,artworkModeEl,artworkFitEl,patternModeEl].filter(Boolean).forEach(e=>{e.addEventListener('input',refresh);e.addEventListener('change',refresh)});labelWidthRange.addEventListener('input',function(){canvasWidthEl.value=this.value;refresh()});labelHeightRange.addEventListener('input',function(){canvasHeightEl.value=this.value;refresh()});Object.values(c).filter(Boolean).forEach(e=>e.addEventListener('input',evt=>{outs();save(evt.target)}));
if(tasteTextControl.input){tasteTextControl.input.addEventListener('input',function(){setGlobalTasteTextSize(this.value)})}
if(coffeeProductPick){coffeeProductPick.addEventListener('change',function(){const opt=this.selectedOptions&&this.selectedOptions[0];if(!opt||!opt.dataset.name)return;const coffeeName=by('coffee_name');if(coffeeName){coffeeName.value=opt.dataset.name;coffeeName.dispatchEvent(new Event('input',{bubbles:true}))}})}
Object.values(printControls).filter(Boolean).forEach(ctrl=>{ctrl.addEventListener('input',function(){syncPrintStateFromControls();if(ctrl===printControls.paper||ctrl===printControls.orientation){syncPrintControlsFromState()}refresh()});ctrl.addEventListener('change',function(){syncPrintStateFromControls();if(ctrl===printControls.paper||ctrl===printControls.orientation){syncPrintControlsFromState()}refresh()})});
if(toggleBlockVisible){toggleBlockVisible.addEventListener('click',function(){const s=state.blocks[active]||(state.blocks[active]={});s.visible=s.visible===false;refresh();updateVisibilityButton(s)})}
document.querySelectorAll('[data-element-toggle]').forEach(button=>button.addEventListener('click',function(){
  const key=this.dataset.elementToggle;
  if(!key)return;
  active=key;
  blockSelect.value=key;
  const s=state.blocks[key]||(state.blocks[key]={});
  s.visible=s.visible===false;
  load();
}));
function exportTemplateDesign(){const design=clone(state);delete design.meta;delete design.layout;design.schema='roastery-label-template-v1';return design}
function closeTemplateModal(){
  if(!saveTemplateModal)return;
  saveTemplateModal.hidden=true;
  saveTemplateModal.setAttribute('aria-hidden','true');
  if(templateNameField)templateNameField.classList.remove('is-invalid');
}
function openTemplateModal(){
  syncTasteValueFromRows();syncMetaFromFields();syncPrintStateFromControls();refresh();
  if(!saveTemplateModal||!templateNameField)return;
  templateNameField.value='Template ' + (textValue('coffee_name','Label Kopi')||'Label Kopi');
  if(templateDescriptionField)templateDescriptionField.value='';
  templateNameField.classList.remove('is-invalid');
  saveTemplateModal.hidden=false;
  saveTemplateModal.setAttribute('aria-hidden','false');
  window.setTimeout(()=>templateNameField.focus(),0);
}
function submitTemplate(){
  const name=(templateNameField?.value||'').trim();
  if(name===''){
    if(templateNameField){templateNameField.classList.add('is-invalid');templateNameField.focus()}
    return;
  }
  templateNameInput.value=name;
  templateDescriptionInput.value=(templateDescriptionField?.value||'').trim();
  templateDesignJsonInput.value=JSON.stringify(exportTemplateDesign());
  saveTemplateForm.submit();
}
if(saveTemplateBtn)saveTemplateBtn.addEventListener('click',openTemplateModal);
if(confirmTemplateSave)confirmTemplateSave.addEventListener('click',submitTemplate);
if(cancelTemplateSave)cancelTemplateSave.addEventListener('click',closeTemplateModal);
if(templateNameField)templateNameField.addEventListener('input',function(){this.classList.remove('is-invalid')});
if(templateNameField)templateNameField.addEventListener('keydown',function(evt){if(evt.key==='Enter'){evt.preventDefault();submitTemplate()}});
if(saveTemplateModal)saveTemplateModal.addEventListener('click',function(evt){if(evt.target===saveTemplateModal)closeTemplateModal()});
if(templateSelect){
  const showTemplateHelp=()=>{const option=templateSelect.selectedOptions&&templateSelect.selectedOptions[0];if(templateSelectHelp&&option)templateSelectHelp.textContent=option.dataset.description||'Template awal yang tetap dapat Anda ubah.';};
  showTemplateHelp();
  templateSelect.addEventListener('change',function(){const option=this.selectedOptions&&this.selectedOptions[0],url=option?.value||'';if(url)window.location.assign(url)});
}
document.addEventListener('click',function(evt){if(!evt.target.closest('.taste-icon-picker')){document.querySelectorAll('.taste-icon-picker.open').forEach(node=>node.classList.remove('open'))}});
blockSelect.addEventListener('change',function(){active=this.value;load()});document.querySelectorAll('[data-block]').forEach(e=>e.addEventListener('click',function(){active=this.dataset.block;blockSelect.value=active;load()}));
document.querySelectorAll('[data-toggle-style]').forEach(b=>b.addEventListener('click',function(){const s=state.blocks[active]||(state.blocks[active]={});s[this.dataset.toggleStyle]=!s[this.dataset.toggleStyle];refresh()}));document.querySelectorAll('[data-align]').forEach(b=>b.addEventListener('click',function(){(state.blocks[active]||(state.blocks[active]={})).align=this.dataset.align;refresh()}));
resetPremiumLayout.addEventListener('click',function(){state=merge(defaults,{});themePresetEl.value='heritage-cream';artworkModeEl.value='full';if(artworkFitEl)artworkFitEl.value='stretch';if(patternModeEl)patternModeEl.value='contour';active='logo';blockSelect.value=active;hydrateMetaFields();syncPrintControlsFromState();renderTasteRowsV2();load()});
addTasteNote.addEventListener('click',function(){forceBlankTasteRow=true;renderTasteRowsV2();refresh()});
document.querySelectorAll('#artworkGallery .gallery-tile').forEach(tile=>tile.addEventListener('click',function(){document.querySelectorAll('#artworkGallery .gallery-tile').forEach(t=>t.classList.remove('active'));this.classList.add('active');galleryPath.value=this.dataset.path||'';img.src=this.dataset.url||'';img.style.display='';bg.classList.remove('no-image');if(isUniversalTemplate)refreshUniversalPreview()}));
document.querySelectorAll('#logoGallery .gallery-tile').forEach(tile=>tile.addEventListener('click',function(){document.querySelectorAll('#logoGallery .gallery-tile').forEach(t=>t.classList.remove('active'));this.classList.add('active');galleryLogoPath.value=this.dataset.path||'';if(logoEl)logoEl.src=this.dataset.url||logoEl.src}));
document.querySelectorAll('#badgeLogoGallery .gallery-tile').forEach(tile=>tile.addEventListener('click',function(){document.querySelectorAll('#badgeLogoGallery .gallery-tile').forEach(t=>t.classList.remove('active'));this.classList.add('active');galleryBadgeLogoPath.value=this.dataset.path||'';if(badgeLogoEl)badgeLogoEl.src=this.dataset.url||badgeLogoEl.src}));
imageInput.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(f.type!=='image/png'){alert('Artwork harus PNG.');this.value='';return}galleryPath.value='';document.querySelectorAll('#artworkGallery .gallery-tile').forEach(t=>t.classList.remove('active'));const r=new FileReader();r.onload=e=>{img.src=e.target.result;img.style.display='';bg.classList.remove('no-image');if(isUniversalTemplate)refreshUniversalPreview()};r.readAsDataURL(f)});
function isLogoFile(f){return !!f&&(f.type==='image/png'||f.type==='image/svg+xml'||/\.svg$/i.test(f.name||''))}
logoInput.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(!isLogoFile(f)){alert('Logo harus PNG atau SVG.');this.value='';return}galleryLogoPath.value='';document.querySelectorAll('#logoGallery .gallery-tile').forEach(t=>t.classList.remove('active'));const r=new FileReader();r.onload=e=>{if(logoEl)logoEl.src=e.target.result};r.readAsDataURL(f)});
badgeLogoInput.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(!isLogoFile(f)){alert('Logo badge harus PNG atau SVG.');this.value='';return}galleryBadgeLogoPath.value='';document.querySelectorAll('#badgeLogoGallery .gallery-tile').forEach(t=>t.classList.remove('active'));const r=new FileReader();r.onload=e=>{if(badgeLogoEl)badgeLogoEl.src=e.target.result};r.readAsDataURL(f)});
window.addEventListener('pointermove',moveDrag);
window.addEventListener('pointerup',endDrag);
window.addEventListener('pointercancel',endDrag);
function ensureUniversalPrintPortal(){let portal=el('universalPrintPortal');if(!portal){portal=document.createElement('div');portal.id='universalPrintPortal';portal.setAttribute('aria-hidden','true');document.body.appendChild(portal)}return portal}
async function buildUniversalPrintSheet(){refreshUniversalPreview();const portal=ensureUniversalPrintPortal();portal.innerHTML='';const count=Math.max(1,Math.min(24,parseInt(state.print?.perSheet||4,10)||4));for(let i=0;i<count;i++){const slot=document.createElement('div');slot.className='universal-print-slot';const copy=universalLabel.cloneNode(true);copy.removeAttribute('id');copy.querySelectorAll('[id]').forEach(node=>node.removeAttribute('id'));slot.appendChild(copy);portal.appendChild(slot)}await waitForPrintAssets(portal)}
async function openPrint(){refresh();if(isUniversalTemplate){await buildUniversalPrintSheet();document.body.classList.add('namua-label-printing');window.print();setTimeout(()=>document.body.classList.remove('namua-label-printing'),500);return}await buildPrintSheet();document.body.classList.add('coffee-label-printing');window.print();setTimeout(()=>document.body.classList.remove('coffee-label-printing'),500)}
window.addEventListener('afterprint',()=>{document.body.classList.remove('coffee-label-printing');document.body.classList.remove('namua-label-printing')});
formEl.addEventListener('submit',function(){syncTasteValueFromRows();syncMetaFromFields();syncPrintStateFromControls();refresh()});printBtn.addEventListener('click',openPrint);syncMetaFromFields();syncPrintControlsFromState();renderTasteRowsV2();load();
<?php if ($autoPrint): ?>setTimeout(openPrint,700);<?php endif; ?>
})();
</script>
<?php endif; ?>
