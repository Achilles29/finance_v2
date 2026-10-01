(() => {
  'use strict';
  const root = document.getElementById('roastConnectAdmin');
  if (!root) return;
  const $ = id => document.getElementById(id);
  let settings = JSON.parse(root.dataset.settings), busy = false;
  function render() {
    $('rcName').value = settings.name;
    $('rcDivision').value = settings.division_id || '';
    $('rcDestination').value = settings.destination_type;
    $('rcEnabled').checked = settings.enabled;
    const expired = settings.expires_at && Date.parse(settings.expires_at.replace(' ','T')+'Z') <= Date.now();
    $('rcStatus').textContent = expired ? 'Token kedaluwarsa' : settings.enabled ? 'Akses katalog aktif' : 'Akses katalog nonaktif';
    $('rcStatus').className = 'badge ' + (settings.enabled && !expired ? 'bg-success' : 'bg-secondary');
    $('rcTokenState').textContent = settings.has_token ? `Token tersimpan · akhiran …${settings.token_tail}. Berlaku sampai ${settings.expires_at || '—'} UTC.` : 'Belum ada token koneksi.';
    $('rcRotate').textContent = settings.has_token ? 'Ganti token koneksi' : 'Buat token koneksi';
    $('rcRotateHint').textContent = settings.has_token ? 'Mengganti token langsung menonaktifkan token lama. Setelah itu, isi token baru di Roast Studio. Pilihan divisi dan status akses pada formulir juga disimpan.' : 'Pembuatan token sekaligus menyimpan pilihan divisi dan status akses pada formulir di atas.';
  }
  function message(text, error = false) {
    $('rcMessage').textContent = text;
    $('rcMessage').className = 'alert ' + (error ? 'alert-danger' : 'alert-success');
    $('rcMessage').hidden = false;
  }
  async function submit(rotate) {
    if (busy || !$('rcForm').reportValidity()) return;
    busy = true; $('rcMessage').hidden = true;
    $('rcSave').disabled = true; $('rcRotate').disabled = true;
    const body = new FormData($('rcForm'));
    Object.entries({revision:settings.revision,name:$('rcName').value,division_id:$('rcDivision').value,destination_type:$('rcDestination').value,enabled:$('rcEnabled').checked?'1':'0',valid_days:$('rcValidity').value}).forEach(([key,value])=>body.set(key,value));
    try {
      const response = await fetch(rotate ? root.dataset.rotateUrl : root.dataset.saveUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Pengaturan belum tersimpan.');
      settings = data.settings; render();
      if (data.token) {
        $('rcNewToken').value = data.token; $('rcTokenPanel').hidden = false;
        $('rcTokenPanel').scrollIntoView({behavior:'smooth',block:'center'});
      }
      message(rotate ? 'Token berhasil dibuat. Salin dan tempel ke pengaturan Integrasi di Roast Studio.' : 'Pengaturan akses katalog tersimpan.');
    } catch (error) { message(error.message || 'Koneksi terputus. Muat ulang halaman untuk memeriksa status.',true); }
    finally { busy = false; $('rcSave').disabled = false; $('rcRotate').disabled = false; }
  }
  $('rcForm').addEventListener('submit',e=>{e.preventDefault();submit(false);});
  $('rcRotate').addEventListener('click',()=>submit(true));
  $('rcCopyToken').addEventListener('click',async()=>{
    try { await navigator.clipboard.writeText($('rcNewToken').value); message('Token disalin. Tempel pada pengaturan Integrasi Roast Studio.'); }
    catch { $('rcNewToken').focus(); $('rcNewToken').select(); message('Token dipilih. Gunakan Salin pada perangkat Anda.'); }
  });
  function hideToken() { $('rcNewToken').value=''; $('rcTokenPanel').hidden=true; }
  $('rcHideToken').addEventListener('click',hideToken);
  window.addEventListener('pagehide',hideToken);
  render();
})();
