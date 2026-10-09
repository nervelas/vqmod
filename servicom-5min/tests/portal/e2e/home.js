const { launch, BASE, SIZES, newPage, overflow } = require('./lib');
(async () => {
  const b = await launch();
  for (const w of SIZES) {
    const p = await newPage(b, w);
    await p.goto(BASE + '/', { waitUntil: 'networkidle' });
    await p.waitForTimeout(1500);
    // scroll to reveal everything
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < h; y += 400) { await p.evaluate(y => window.scrollTo({top:y,behavior:'instant'}), y); await p.waitForTimeout(120); }
    await p.evaluate(() => window.scrollTo({top:0,behavior:'instant'})); await p.waitForTimeout(600);
    const o = await overflow(p);
    console.log(w, JSON.stringify(o), o.sw > o.iw ? 'OVERFLOW' : 'ok', p.errors.join('|'));
    await p.screenshot({ path: `/tmp/shots/f/home-${w}.png`, fullPage: true });
    await p.close();
  }
  await b.close();
})();
