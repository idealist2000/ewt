const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const wp=code=>execFileSync('/workspace/ewt-local/wp.sh',['eval',code],{encoding:'utf8'}).trim();
(async()=>{
 let id,browser;
 try {
  id=Number(wp(`$id=wp_insert_post(array('post_status'=>'publish','post_title'=>'Present perfect learning','post_content'=>'<h2>Form</h2><p>Present perfect connects past events with the present. We use have or has with a past participle to describe experiences.</p><h2>Practice</h2><p>Read the examples and write your own sentences about experiences.</p><img src="/missing-test-image.png" alt="English grammar notebook">')); update_post_meta($id,'_ewt_focus_keyword','present perfect'); ewt_apply_seo_fields($id,'present perfect','Present perfect learning','',false); echo $id;`));
  assert.ok(id>0);const url=wp(`echo get_permalink(${id});`);
  browser=await chromium.launch({executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
  const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[],requests=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const req=route.request(),u=new URL(req.url());
   if(u.hostname!=='127.0.0.1')return route.abort();
   if(u.pathname.endsWith('admin-ajax.php')&&(req.postData()||'').includes('action=ewt_lesson_assistant')){
    requests.push(new URLSearchParams(req.postData()));
    return route.fulfill({json:{success:true,data:{answer:'Mock lesson answer'}}});
   }return route.continue();
  });
  await page.clock.install();
  await page.goto(url,{waitUntil:'domcontentloaded'});
  await page.locator('.ewt-single-content p').first().scrollIntoViewIfNeeded();
  await page.clock.runFor(35000);
  const panel=page.locator('#ewt-chatbot-panel'),toggle=page.locator('#ewt-chatbot-toggle');
  assert.equal(await panel.isVisible(),false);assert.equal(requests.length,0);
  assert.match(await toggle.textContent(),/Sorunuz var mı/);
  const title=await page.title();assert.ok(title.startsWith('Present Perfect'));assert.ok([...title].length<=55);assert.match(title,/2 Bölüm/);
  assert.equal(await page.locator('[data-ewt-seo-resources]').count(),1);
  for(const a of await page.locator('[data-ewt-seo-resources] a').all()){
   assert.equal(await a.getAttribute('target'),'_blank');assert.match(await a.getAttribute('rel'),/noopener/);
  }
  assert.match(await page.locator('.ewt-single-content img').first().getAttribute('alt'),/present perfect/);
  await toggle.click();assert.equal(await panel.isVisible(),true);
  await page.getByRole('button',{name:'✨ Daha fazla örnek',exact:true}).click();
  await page.getByText('Mock lesson answer',{exact:true}).waitFor();assert.equal(requests.length,1);assert.equal(requests[0].get('request_type'),'examples');assert.match(requests[0].get('context'),/Present perfect/);
  await page.getByRole('button',{name:'❓ Sorun var / soru sor',exact:true}).click();
  await page.locator('#ewt-chatbot-input').fill('How do I form this tense?');
  await page.locator('#ewt-chatbot-send').click();await page.locator('.ewt-msg').filter({hasText:'Mock lesson answer'}).nth(1).waitFor();assert.equal(requests[1].get('request_type'),'question');
  assert.match(await page.locator('.ewt-chatbot-footer .ewt-call').getAttribute('href'),/^tel:/);
  assert.match(await page.locator('.ewt-chatbot-footer .ewt-wa').getAttribute('href'),/^https:\/\/wa.me\//);
  await page.keyboard.press('Escape');assert.equal(await panel.isVisible(),false);
  await page.setViewportSize({width:390,height:844});
  const bounds=await toggle.boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=390);assert.ok(bounds.width>bounds.height);
  await toggle.click();assert.equal(await panel.isVisible(),true);await page.locator('#ewt-chatbot-close').click();assert.equal(await panel.isVisible(),false);
  await page.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'domcontentloaded'});
  await page.locator('#user_login').fill('ewt_local_admin');await page.locator('#user_pass').fill(fs.readFileSync('/workspace/ewt-local/admin-password','utf8').trim());
  await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
  await page.goto('http://127.0.0.1:8080/wp-admin/themes.php?page=ewt-seo-maintenance',{waitUntil:'domcontentloaded'});
  await page.locator('[data-seo="preview"]').click();await page.locator('[data-seo="rows"] table').waitFor();
  assert.match(await page.locator('[data-seo="status"]').textContent(),/içerik işlendi/);
  const data=await page.evaluate(async cursor=>{
   const body=new URLSearchParams({action:'ewt_seo_maintenance',nonce:ewtSeoMaintenance.nonce,mode:'apply',cursor:String(cursor)});
   return (await fetch(ewtSeoMaintenance.url,{method:'POST',body})).json();
  },id-1);
  assert.equal(data.success,true);assert.equal(data.data.rows.find(r=>r.id===id).status,'updated');
  const unauth=await browser.newContext();const denied=await unauth.request.post('http://127.0.0.1:8080/wp-admin/admin-ajax.php',{form:{action:'ewt_seo_maintenance',mode:'apply'}});assert.equal(denied.status(),400);await unauth.close();
  assert.deepEqual(errors,[]);console.log('PASS: no automatic chatbot or API calls; explicit examples/questions, contact links, mobile controls, SEO frontend, authenticated maintenance and access restriction');
 }finally {if(browser)await browser.close();if(id)wp(`wp_delete_post(${id},true);`);}
})().catch(e=>{console.error(e);process.exit(1);});
