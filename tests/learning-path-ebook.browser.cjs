const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async () => {
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
 try {
  const page=await browser.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
  await page.goto('http://127.0.0.1:8080/?ewt_path=1&ewt_level=b2&ewt_goal=toefl&ewt_target=toefl-100p&ewt_audience=postgraduate',{waitUntil:'domcontentloaded'});
  const form=page.locator('#ewt-path-ebook-form');await form.waitFor();
  const btn=form.locator('button[type="submit"]');await btn.click();
  assert.match(await page.locator('#ewt-path-ebook-status').textContent(),/birkaç saniye/);
  const bad=await page.evaluate(async()=>{
   const form=document.getElementById('ewt-path-ebook-form');const body=new FormData(form);body.set('nonce','invalid');
   return (await fetch(form.getAttribute('action'),{method:'POST',body})).json();
  });assert.equal(bad.success,false);
  await page.waitForTimeout(3100);
  await page.evaluate(()=>window.open=()=>null);
  await btn.click();
  await page.locator('#ewt-path-ebook-open:not([hidden])').waitFor();
  assert.match(await page.locator('#ewt-path-ebook-status').textContent(),/içerik bağlantısı hazır/);
  const url=await page.locator('#ewt-path-ebook-open').getAttribute('href');
  const doc=await browser.newPage();await doc.goto(url,{waitUntil:'domcontentloaded'});
  assert.ok(await doc.locator('.ewt-link-title').count()>0);
  assert.match(await doc.locator('h1').textContent(),/B2 TOEFL/);
  assert.match(await doc.locator('body').textContent(),/Lisansüstü/);
  assert.equal(await doc.locator('.ewt-chapter').count(),0);
  assert.equal((await doc.locator('body').textContent()).includes('This lesson explains how to talk about daily routines'),false);
  const hrefs=await doc.locator('.ewt-link-title').evaluateAll(links=>links.map(a=>a.href));
  const pdf=await doc.pdf({format:'A4',printBackground:true});
  assert.equal(pdf.subarray(0,5).toString(),'%PDF-');
  fs.writeFileSync('/workspace/ewt-local/learning-path-links.pdf',pdf);
  fs.writeFileSync('/workspace/ewt-local/learning-path-pdf-links.json',JSON.stringify(hrefs));
  const uriCount=(pdf.toString('latin1').match(/\/Subtype \/Link/g)||[]).length;
  assert.ok(uriCount>=hrefs.length);
  for(const href of hrefs) assert.ok(pdf.toString('latin1').includes(href));
  await doc.setViewportSize({width:390,height:844});
  assert.equal(await doc.locator('.ewt-link-doc').evaluate(el=>el.scrollWidth<=el.clientWidth),true);
  await doc.close();
  const forced=await page.evaluate(async()=>{
   const form=document.getElementById('ewt-path-ebook-form');const body=new FormData(form);
   body.set('format','epub');body.set('rendered_at',Math.floor(Date.now()/1000)-10);body.append('ids[]','99999999');
   return(await fetch(form.getAttribute('action'),{method:'POST',body})).json();
  });assert.equal(forced.success,true);assert.equal(forced.data.format,'pdf');assert.ok(forced.data.count>0);
  const nojs=await browser.newPage({javaScriptEnabled:false});await nojs.goto(page.url(),{waitUntil:'domcontentloaded'});
  assert.equal(await nojs.locator('#ewt-path-ebook-form button').isVisible(),false);await nojs.close();
  assert.deepEqual(errors,[]);
  console.log('PASS: nonce validation, wait guard, popup fallback, actual PDF with clickable URIs, link-only output, target context, mobile layout, server-derived IDs and PDF-only format');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
