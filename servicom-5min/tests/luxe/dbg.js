const path=require('path');const {chromium}=require(path.join(__dirname,'../portal/e2e/node_modules/playwright-core'));
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',args:['--no-sandbox','--host-resolver-rules=MAP *.servicom.test 127.0.0.1']});
const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto(process.argv[2],{waitUntil:'networkidle'});
console.log(await p.evaluate(()=>({rv:document.querySelectorAll('.lx-rv').length,on:document.querySelectorAll('.lx-rv.is-in').length,js:document.documentElement.className,secs:[...document.querySelectorAll('.lx-sec')].map(s=>s.id+':'+s.offsetHeight)})));
await p.evaluate(()=>window.scrollTo(0,1500)); await p.waitForTimeout(1500);
console.log(await p.evaluate(()=>({on:document.querySelectorAll('.lx-rv.is-in').length})));
await p.screenshot({path:'/tmp/dbg.png'});await b.close();})();
