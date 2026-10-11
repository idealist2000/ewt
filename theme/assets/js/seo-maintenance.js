(function () {
 'use strict';
 const root=document.getElementById('ewt-seo-maintenance');if(!root)return;
 const el=name=>root.querySelector('[data-seo="'+name+'"]');
 let start=0,next=0,more=false,halt=false,busy=false;
 function controls(locked){
  busy=locked;
  for(const key of ['preview','next','apply','all'])el(key).disabled=locked || (key==='next'&&!more) || (key==='apply'&&!el('rows').children.length);
 }
 function render(rows){
  const table=document.createElement('table');table.className='widefat striped';
  const heading=document.createElement('tr');
  for(const name of ['İçerik / FK','Yeni SEO başlığı','Yoğunluk','Durum']){const th=document.createElement('th');th.textContent=name;heading.append(th);}
  table.append(heading);
  const names={ready:'Hazır',updated:'Güncellendi',missing:'FK eksik',blocked:'Düzenleme gerekli',forbidden:'Yetki yok'};
  for(const row of rows){
   const tr=document.createElement('tr');
   const values=[row.title+' / '+row.fk,row.new_title,row.density.percent+'% · '+row.density.occurrences+'/'+row.density.words+' · Hedef kullanım: '+row.density.suggested_occurrences,(names[row.status]||row.status)+(row.message?' — '+row.message:'')];
   for(const value of values){const td=document.createElement('td');td.textContent=value;tr.append(td);}table.append(tr);
  }
  el('rows').replaceChildren(table);
 }
 async function request(mode,cursor){
  const body=new URLSearchParams({action:'ewt_seo_maintenance',nonce:ewtSeoMaintenance.nonce,mode,cursor:String(cursor)});
  const response=await fetch(ewtSeoMaintenance.url,{method:'POST',body,credentials:'same-origin'});
  const data=await response.json();if(!response.ok||!data.success)throw new Error(data.data?.message||'İşlem tamamlanamadı.');return data.data;
 }
 async function run(mode,cursor){
  if(busy)return;controls(true);el('status').textContent='İşleniyor…';
  try {const data=await request(mode,cursor);start=cursor;next=data.cursor;more=data.more;render(data.rows);el('status').textContent=data.rows.length+' içerik işlendi.';}
  catch(error){el('status').textContent=error.message;}
  finally{controls(false);}
 }
 el('preview').addEventListener('click',()=>run('preview',0));
 el('next').addEventListener('click',()=>run('preview',next));
 el('apply').addEventListener('click',()=>run('apply',start));
 el('stop').addEventListener('click',()=>{halt=true;el('status').textContent='Mevcut parti tamamlanınca duracak.';});
 el('all').addEventListener('click',async()=>{
  if(busy)return;halt=false;controls(true);el('stop').hidden=false;let cursor=0,processed=0,updated=0,skipped=0;
  try {
   do {const data=await request('apply',cursor);render(data.rows);processed+=data.rows.length;updated+=data.rows.filter(r=>r.status==='updated').length;skipped+=data.rows.filter(r=>r.status!=='updated').length;start=cursor;cursor=data.cursor;next=cursor;more=data.more;el('status').textContent=processed+' incelendi · '+updated+' güncellendi · '+skipped+' atlandı.';}while(more&&!halt);
   el('status').textContent+=(halt?' İşlem durduruldu.':' İşlem tamamlandı.');
  }catch(error){el('status').textContent=error.message;}
  finally{el('stop').hidden=true;controls(false);}
 });
}());
