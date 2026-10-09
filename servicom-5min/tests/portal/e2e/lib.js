const { chromium } = require('playwright-core');
exports.launch = () => chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--host-resolver-rules=MAP *.servicom.test 127.0.0.1'] });
exports.BASE = 'http://127.0.0.1:8131';
exports.SIZES = [360, 390, 768, 1024, 1440];
exports.newPage = async (b, w, opts = {}) => {
  const ctx = await b.newContext({ viewport: { width: w, height: w < 700 ? 780 : 900 }, deviceScaleFactor: 1, hasTouch: w < 700, isMobile: w < 700, ...opts });
  const page = await ctx.newPage();
  page.errors = [];
  page.on('console', m => { if (['error', 'warning'].includes(m.type())) page.errors.push(m.type() + ': ' + m.text()); });
  page.on('pageerror', e => page.errors.push('pageerror: ' + e.message));
  page.on('requestfailed', r => page.errors.push('reqfail: ' + r.url()));
  return page;
};
exports.overflow = async (page) => page.evaluate(() => ({ sw: document.documentElement.scrollWidth, iw: window.innerWidth, bw: document.body.scrollWidth }));
