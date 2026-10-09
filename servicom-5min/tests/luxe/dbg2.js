const path=require('path');const {chromium}=require(path.join(__dirname,'../portal/e2e/node_modules/playwright-core'));
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',args:['--no-sandbox','--host-resolver-rules=MAP *.servicom.test 127.0.0.1']});
const [url,out,sel]=process.argv.slice(2);
const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto(url,{waitUntil:'networkidle'});
await p.evaluate((s)=>{const e=document.querySelector(s);e&&e.scrollIntoView();},sel);
await p.waitForTimeout(1800);
await p.screenshot({path:out});await b.close();})();
