const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const bank = require('../theme/assets/placement-questions.json');
(async () => {
  const browser = await chromium.launch({executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport:{width:1440,height:1000}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      return url.hostname === '127.0.0.1' ? route.continue() : route.abort();
    });
    await page.goto('http://127.0.0.1:8080/',{waitUntil:'domcontentloaded'});
    await page.locator('#ewt-path-form').waitFor();
    assert.equal(await page.locator('#ewt-placement-test').count(),1);
    await page.selectOption('#ewt_level','b1');
    await page.selectOption('#ewt_audience','grade-5');
    await page.selectOption('#ewt_goal','toefl');
    await page.selectOption('#ewt_target','toefl-100p');
    await page.reload({waitUntil:'domcontentloaded'});
    assert.equal(await page.inputValue('#ewt_audience'),'grade-5');
    assert.equal(await page.inputValue('#ewt_target'),'toefl-100p');
    await page.selectOption('#ewt_goal','ielts');
    assert.equal(await page.inputValue('#ewt_target'),'');
    await page.selectOption('#ewt_target','ielts-7-0p');
    await page.locator('[data-pt="start"]').click();
    await page.locator('[data-pt="next"]').click();
    assert.match(await page.locator('[data-pt="error"]').textContent(),/Bir cevap/);
    await page.locator('[data-pt="options"] input[value="1"]').check();
    await page.locator('[data-pt="next"]').click();
    await page.locator('[data-pt="back"]').click();
    assert.equal(await page.locator('[data-pt="options"] input[value="1"]').isChecked(),true);
    await page.locator('[data-pt="cancel"]').click();
    await page.locator('[data-pt="start"]').click();
    for (const q of bank) {
      await page.locator('[data-pt="options"] input[value="'+q.answer+'"]').check();
      await page.locator('[data-pt="next"]').click();
    }
    assert.match(await page.locator('[data-pt="result-title"]').textContent(),/C2/);
    assert.match(await page.locator('[data-pt="summary"]').textContent(),/30\/30/);
    await page.locator('[data-pt="apply"]').click();
    await page.waitForURL('**/?ewt_path=1**');
    assert.equal(await page.inputValue('#ewt_level'),'c2');
    assert.equal(await page.inputValue('#ewt_goal'),'ielts');
    assert.equal(await page.inputValue('#ewt_target'),'ielts-7-0p');
    assert.equal(await page.inputValue('#ewt_audience'),'grade-5');
    assert.equal(await page.locator('#ewt-placement-test').count(),0);
    await page.goto('http://127.0.0.1:8080/development-speaking-smoke-test/',{waitUntil:'domcontentloaded'});
    assert.equal(await page.locator('#ewt-path-form').count(),1);
    assert.equal(await page.locator('#ewt-placement-test').count(),0);
    await page.goto('http://127.0.0.1:8080/',{waitUntil:'domcontentloaded'});
    await page.locator('#ewt-path-reset').click();
    assert.equal(await page.inputValue('#ewt_level'),'a1');
    assert.equal(await page.inputValue('#ewt_target'),'');
    await page.screenshot({path:'/workspace/ewt-local/learning-path-desktop.png',fullPage:false});
    await page.setViewportSize({width:390,height:844});
    const fits = await page.locator('.ewt-path-bar').evaluate(el => el.scrollWidth <= el.clientWidth);
    assert.equal(fits,true);
    await page.locator('[data-pt="start"]').click();
    await page.locator('#ewt-placement-test').scrollIntoViewIfNeeded();
    assert.equal(await page.locator('#ewt-placement-test').evaluate(el => el.scrollWidth <= el.clientWidth),true);
    await page.screenshot({path:'/workspace/ewt-local/learning-path-mobile.png',fullPage:false});

    const nojs = await browser.newPage({javaScriptEnabled:false});
    await nojs.goto('http://127.0.0.1:8080/?ewt_path=1&ewt_goal=toefl&ewt_level=b2&ewt_audience=grade-12',{waitUntil:'domcontentloaded'});
    assert.equal(await nojs.locator('#ewt-path-form').count(),1);
    assert.equal(await nojs.inputValue('#ewt_goal'),'toefl');
    assert.equal(await nojs.locator('#ewt-path-results-title').count(),1);
    await nojs.close();
    assert.deepEqual(errors,[]);
    console.log('PASS: persistence, dependent targets, quiz navigation/scoring, result handoff, every-page bar, homepage-only test, reset, mobile layout and browser JavaScript');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
