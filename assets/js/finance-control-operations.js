(() => {
  'use strict';
  const config=document.getElementById('fc-operations-config');if(!config)return;
  const message=document.getElementById('fc-message');
  const notify=(text,error=false)=>{message.textContent=text;message.className='alert fc-alert '+(error?'alert-danger':'alert-success');message.scrollIntoView({block:'nearest'});};
  const get=async(kind,params)=>{const response=await fetch(config.dataset.base+'lookup/'+kind+'?'+new URLSearchParams(params),{credentials:'same-origin'});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.message||'Pencarian gagal.');return data;};
  document.querySelectorAll('[data-charge]').forEach(button=>button.addEventListener('click',()=>{
    const form=document.getElementById('fc-charge-form');if(!form||form.dataset.busy==='1')return;
    form.elements.existing_mutation_id.value='';const data=JSON.parse(button.dataset.charge);for(const [key,value] of Object.entries(data)){const field=form.elements.namedItem(key);if(field)field.value=value??'';}
    document.getElementById('fc-charge-editor').open=true;form.scrollIntoView({behavior:'smooth',block:'center'});
  }));
  const chargeForm=document.getElementById('fc-charge-form');
  chargeForm?.elements.category.addEventListener('change',()=>{const direction=chargeForm.elements.category.selectedOptions[0]?.dataset.direction;if(['IN','OUT'].includes(direction))chargeForm.elements.direction.value=direction;});
  chargeForm?.addEventListener('reset',()=>{setTimeout(()=>{chargeForm.elements.id.value='0';chargeForm.elements.revision.value='0';chargeForm.elements.request_key.value=Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join('');},0);});
  const linkForm=document.getElementById('fc-plan-link-form');
  linkForm?.elements.plan_id.addEventListener('change',()=>{linkForm.elements.revision.value=linkForm.elements.plan_id.selectedOptions[0]?.dataset.revision||'0';});
  for(const kind of ['history','mutation']){
    const form=document.getElementById(`fc-${kind}-search`);if(!form)continue;let page=1,pages=1,more=false,serial=0;
    const search=async()=>{const sequence=++serial;try{const params=Object.fromEntries(new FormData(form));params.page=page;
      const data=await get(kind==='history'?'settlements':'mutations',params);if(sequence!==serial)return;page=data.page;pages=data.pages||page;more=kind==='history'?page<pages:data.more;
      document.getElementById(`fc-${kind}-page`).textContent=kind==='history'?`${page}/${pages} · ${data.total} rekap`:`Halaman ${page}`;
      document.querySelectorAll(`[data-${kind}-page]`).forEach(b=>b.disabled=Number(b.dataset[kind+'Page'])<0?page<=1:!more);
      if(kind==='history'){const box=document.getElementById('fc-history-results');box.replaceChildren();const list=document.createElement('ul');list.className='list-unstyled';data.rows.forEach(r=>{const li=document.createElement('li'),a=document.createElement('a');a.href=config.dataset.base.replace(/\/$/,'')+'?'+new URLSearchParams({tab:'settlement',date:r.revenue_date,method_id:r.payment_method_id});a.textContent=`#${r.id} · ${r.revenue_date} · ${r.method_name} · ${r.provider_reference}`;li.append(a);list.append(li);});box.append(list);if(!data.rows.length)box.textContent='Tidak ada settlement sesuai filter.';}
      else{const select=document.getElementById('fc-mutation-options');select.replaceChildren(new Option(data.rows.length?'Pilih mutasi efektif':'Tidak ada mutasi sesuai filter',''));data.rows.forEach(r=>select.add(new Option(`#${r.id} · ${r.mutation_no} · ${r.mutation_date} · ${r.account_name} · ${r.mutation_type} ${Number(r.amount).toLocaleString('id-ID')}${r.available_amount!==undefined?' · Sisa '+Number(r.available_amount).toLocaleString('id-ID'):''}`,r.id)));}
    }catch(e){if(sequence===serial)notify(e.message,true);}};
    form.addEventListener('submit',event=>{event.preventDefault();page=1;search();});
    document.querySelectorAll(`[data-${kind}-page]`).forEach(button=>button.addEventListener('click',()=>{page=Math.max(1,page+Number(button.dataset[kind+'Page']));search();}));
  }
  const upload=document.getElementById('fc-evidence-upload');
  upload?.addEventListener('submit',async event=>{event.preventDefault();if(upload.dataset.busy==='1'||!upload.reportValidity())return;
    const file=upload.elements.evidence.files[0];if(!file||file.size>5242880){notify('Pilih PDF/JPG/PNG maksimal 5 MB.',true);return;}
    upload.dataset.busy='1';const button=upload.querySelector('button');button.disabled=true;
    try{const response=await fetch(config.dataset.base+'evidence/upload',{method:'POST',credentials:'same-origin',headers:{'X-Finance-Control-CSRF':config.dataset.csrf},body:new FormData(upload)});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.message||'Unggah ditolak.');notify(data.message);setTimeout(()=>location.reload(),1000);}
    catch(e){notify(e.message||'Unggah gagal. Periksa sesi dan batas unggahan server.',true);button.disabled=false;upload.dataset.busy='0';}
  });
})();
