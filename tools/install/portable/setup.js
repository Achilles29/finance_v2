'use strict';
(() => {
  const $ = id => document.getElementById(id), form = $('settings');
  let secret = '', probeId = '', probedConfig = '', probingConfig = '', commandId = '', commandKind = '', timer, busy = false, authorized = false;
  const titles = {READY:'Siap menerima pengaturan.',QUEUED:'Pengaturan diterima. Layanan memeriksa antrean setiap menit; tunggu tanpa mengirim ulang.',ACTIVATING:'Memeriksa aktivasi dan kuota instalasi…',WAITING_ACTIVATION:'Menunggu aktivasi dari Control. Database belum dipasang.',DATABASE:'Menyiapkan database dan akun admin…',WEB_CHECK:'Memeriksa halaman login melalui HTTPS…',REPORTING:'Mengirim hasil pemasangan ke Control…',COMPLETE:'Pemasangan selesai. Silakan login menggunakan akun admin yang Anda buat.'};
  function notice(text, error = false) { $('notice').hidden = !text; $('notice').textContent = text; $('notice').classList.toggle('error', error); }
  function show(id) { ['access','settings','review','progress'].forEach(x => $(x).hidden = x !== id); document.querySelectorAll('[data-step]').forEach(x => x.classList.toggle('active', x.dataset.step === id)); }
  function config() { const f = new FormData(form); return {base_url:f.get('base_url'),database:{host:f.get('host'),port:Number(f.get('port')),socket:'',name:f.get('database'),user:f.get('db_user'),password:f.get('db_password')}}; }
  function owner() { const f = new FormData(form); return {username:f.get('username'),email:f.get('email'),password:f.get('password')}; }
  function id() { return Array.from(crypto.getRandomValues(new Uint8Array(16)), x => x.toString(16).padStart(2,'0')).join(''); }
  function remember() { try {sessionStorage.setItem('finance.setup.command', JSON.stringify({id:commandId,kind:commandKind}));}catch (_) {} }
  function restore() { try {const c=JSON.parse(sessionStorage.getItem('finance.setup.command')||'{}'); if(/^[a-f0-9]{32}$/.test(c.id)&&c.kind==='install'){commandId=c.id;commandKind=c.kind;}}catch (_){} }
  async function call(body) {
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 15000);
    try { const r=await fetch('/setup',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',cache:'no-store',body:JSON.stringify({...body,secret}),signal:controller.signal}); const s=await r.json(); if(!r.ok)throw new Error((s.message||'Pemeriksaan belum berhasil. Periksa kode setup dan persiapan server.')+(/^[A-Z_]+$/.test(s.code||'')?' (Kode: '+s.code+')':'')); return s; }
    finally {clearTimeout(timeout);}
  }
  function checks(values) { Object.entries(values||{}).forEach(([key,value]) => {const card=document.querySelector(`[data-check="${key}"]`);if(!card)return;card.classList.toggle('ok',['OK','VERIFIED'].includes(value));card.classList.toggle('warn',['UNAVAILABLE','NEEDS_ADMIN','INCOMPLETE','NEEDS_REVIEW'].includes(value));card.querySelector('.check-dot').textContent=['OK','VERIFIED'].includes(value)?'✓':'•';card.querySelector('.check-text').textContent=({OK:'Siap',VERIFIED:'Paket telah diverifikasi',NEEDS_REVIEW:'Berkas perlu diperiksa',UNAVAILABLE:'Belum berjalan / perlu diperiksa',NEEDS_ADMIN:'Perlu persiapan admin',INCOMPLETE:'Ekstrak seluruh ZIP'})[value]||'Perlu pemeriksaan';}); }
  function display(s) {
    checks(s.checks); if(s.message)notice(s.message,true);
    const p=s.progress||{}, c=s.command||{};
    if(s.closed||p.phase==='COMPLETE'){clearInterval(timer);show('progress');$('bar').value=100;$('progress-heading').textContent='Finance berhasil dipasang';$('progress-message').textContent=titles.COMPLETE;$('login').hidden=false;$('resume').hidden=true;$('edit-input').hidden=true;notice('');try{sessionStorage.removeItem('finance.setup.command');}catch(_){}return;}
    if(commandKind==='probe'&&c.ok===true){probeId=commandId;probedConfig=probingConfig;$('probe-result').textContent='✓ Koneksi berhasil. Database kosong dan versi sesuai.';$('review-button').disabled=JSON.stringify(config())!==probedConfig;$('probe').disabled=false;clearInterval(timer);notice('');}
    if(commandKind==='probe'&&c.ok===false){$('probe-result').textContent=c.message||'Pengujian belum berhasil.';$('probe').disabled=false;probeId='';clearInterval(timer);notice(c.message||'Periksa isian database.',true);}
    if(commandKind==='install'||(commandKind!=='probe'&&!['READY',''].includes(p.phase||''))){
      show('progress');$('bar').value=p.percent||15;$('progress-message').textContent=p.message||c.message||titles[p.phase]||titles.QUEUED;
      document.querySelectorAll('[data-phase]').forEach(x=>x.classList.toggle('current',x.dataset.phase===p.phase));
      if(p.phase==='ATTENTION'||c.ok===false){clearInterval(timer);notice(p.message||c.message||'Proses perlu pemeriksaan.',true);$('edit-input').hidden=!s.can_edit;}
    }
  }
  async function poll() {if(busy)return;busy=true;try{display(await call({action:'status',id:commandId}));}catch(e){clearInterval(timer);notice(e.message||'Koneksi terputus. Gunakan Periksa status; jangan kirim pemasangan ulang.',true);$('resume').hidden=false;$('probe').disabled=false;}finally{busy=false;}}
  function polling(){clearInterval(timer);timer=setInterval(poll,4000);}
  document.querySelectorAll('[data-toggle]').forEach(button=>button.addEventListener('click',()=>{const input=$(button.dataset.toggle), visible=input.type==='password';input.type=visible?'text':'password';button.textContent=visible?'Sembunyikan':'Lihat';button.setAttribute('aria-pressed',String(visible));}));
  document.querySelectorAll('[data-go]').forEach(b=>b.addEventListener('click',()=>show(b.dataset.go)));
  $('access-form').addEventListener('submit',async e=>{e.preventDefault();secret=$('setup-code').value.trim();notice('Memeriksa kesiapan…');try{const s=await call({action:'status'});authorized=true;checks(s.checks);restore();if(s.closed){display(s);return;}if(s.permission_expired||s.checks.service!=='OK'||s.checks.server!=='OK'||s.checks.package!=='VERIFIED'){notice(s.message||'Persiapan belum selesai. Jalankan kembali satu perintah persiapan.',true);return;}notice('');show('settings');if(commandKind==='install'){show('progress');polling();await poll();}}catch(e){notice(e.message,true);}});
  try {if(location.protocol==='https:')$('base-url').value=new URL(location.href).origin+'/';}catch(_){}
  form.addEventListener('input',e=>{if(['base_url','host','port','database','db_user','db_password'].includes(e.target.name)){probeId='';$('review-button').disabled=true;$('probe-result').textContent='Pengaturan berubah. Uji koneksi kembali.';}});
  $('probe').onclick=async()=>{if(!authorized)return;const fields=['base_url','host','port','database','db_user','db_password'];for(const name of fields){if(!form.elements[name].reportValidity())return;}$('probe').disabled=true;commandId=id();commandKind='probe';probingConfig=JSON.stringify(config());remember();$('probe-result').textContent='Menunggu layanan pengujian (biasanya paling lama satu menit)…';try{await call({action:'probe',id:commandId,config:config()});polling();}catch(e){$('probe').disabled=false;notice(e.message,true);}};
  form.addEventListener('submit',e=>{e.preventDefault();if(!probeId||JSON.stringify(config())!==probedConfig){notice('Uji koneksi kembali sebelum melanjutkan.',true);return;}const c=config(),a=owner();$('summary').replaceChildren();[['Alamat aplikasi',c.base_url],['Server database',`${c.database.host}:${c.database.port}`],['Database',c.database.name],['User database',c.database.user],['Admin Finance',a.username],['Email admin',a.email||'Tidak diisi'],['Paket lisensi','Mengikuti pengiriman terverifikasi dari penjual']].forEach(([k,v])=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=k;dd.textContent=v;$('summary').append(dt,dd);});$('confirm').checked=false;$('install').disabled=true;show('review');notice('');});
  $('confirm').onchange=()=>{$('install').disabled=!$('confirm').checked;};
  $('install').onclick=async()=>{if(!$('confirm').checked||!probeId||JSON.stringify(config())!==probedConfig)return;$('install').disabled=true;commandId=id();commandKind='install';remember();show('progress');$('progress-message').textContent=titles.QUEUED;try{await call({action:'install',id:commandId,config:config(),owner:owner(),probe_id:probeId,confirmed:true,replace_input:true});polling();}catch(e){notice(e.message+' Gunakan Periksa status sebelum mencoba lagi.',true);}};
  $('resume').onclick=()=>{notice('Memeriksa proses yang sama; tidak mengulang pemasangan.');polling();poll();};
  $('edit-input').onclick=()=>{commandId='';commandKind='';probeId='';remember();$('review-button').disabled=true;show('settings');notice('Perbaiki isian dan uji koneksi lagi. Identitas aktivasi tetap dipertahankan.');};
  document.documentElement.dataset.setupReady = 'true';
})();
