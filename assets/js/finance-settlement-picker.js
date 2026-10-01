(() => {
  'use strict';
  if (window.financeSettlementPickerLoaded) return;
  window.financeSettlementPickerLoaded = true;
  const boot = () => document.querySelectorAll('.finance-settlement-picker').forEach(box => {
    const select=box.querySelector('[data-role="settlement-control"]'), charge=box.querySelector('[data-role="settlement-charge"]');
    const status=box.querySelector('[data-picker-status]');let page=1,pages=1,serial=0,chargeSerial=0,timer;
    const label=r=>`#${r.id} · ${r.revenue_date} · ${r.method_name} · ${r.provider_reference}`;
    const get=async(kind,params)=>{const response=await fetch(box.dataset.lookup+kind+'?'+new URLSearchParams(params),{credentials:'same-origin'});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.message||'Pencarian gagal.');return data;};
    const charges=async()=>{
      const sequence=++chargeSerial,selected=charge.value;charge.replaceChildren(new Option('Pilih rincian biaya jika terkait settlement',''));
      if(!select.value)return;
      try{const data=await get('charges',{id:select.value});if(sequence!==chargeSerial)return;
        data.rows.forEach(r=>{if(r.mutation_id && String(r.id)!==selected)return;const option=new Option(`#${r.id} · ${r.document_no}/${r.line_reference} · ${r.charge_date} · ${r.direction} ${Number(r.amount).toLocaleString('id-ID')} · ${r.category}`,r.id);charge.add(option);});
        if(selected)charge.value=selected;
      }catch(e){if(sequence===chargeSerial)status.textContent='Rincian biaya belum dimuat. '+e.message;}
    };
    const search=async()=>{
      const sequence=++serial,params={page};box.querySelectorAll('[data-search]').forEach(e=>params[e.dataset.search]=e.value);
      try{const data=await get('settlements',params);if(sequence!==serial)return;
        const old=select.selectedOptions[0]?.cloneNode(true),selected=select.value;
        select.replaceChildren(new Option('Tidak terkait settlement',''));data.rows.forEach(r=>select.add(new Option(label(r),r.id)));
        if(selected&&!data.rows.some(r=>String(r.id)===selected)&&old)select.add(old);select.value=selected;
        page=data.page;pages=data.pages;const count=box.querySelector('[data-pages]');if(count)count.textContent=`${page}/${pages} · ${data.total} rekap`;
        status.textContent='';box.querySelectorAll('[data-page]').forEach(b=>b.disabled=Number(b.dataset.page)<0?page<=1:page>=pages);
      }catch(e){if(sequence===serial)status.textContent=e.message;}
    };
    box.querySelectorAll('[data-search]').forEach(input=>{input.addEventListener('change',e=>e.stopPropagation());input.addEventListener('input',()=>{clearTimeout(timer);page=1;timer=setTimeout(search,300);});});
    box.querySelectorAll('[data-page]').forEach(button=>button.addEventListener('click',()=>{page=Math.max(1,Math.min(pages,page+Number(button.dataset.page)));search();}));
    select.addEventListener('change',()=>{charge.value='';charges();});
    if(select.value)get('settlements',{id:select.value}).then(data=>{const row=data.rows[0];if(row && select.value===String(row.id))select.selectedOptions[0].textContent=label(row);}).catch(e=>status.textContent=e.message);
    charges();if(!select.disabled)search();
  });
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
