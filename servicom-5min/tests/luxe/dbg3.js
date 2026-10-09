const path=require('path');const {chromium}=require(path.join(__dirname,'../portal/e2e/node_modules/playwright-core'));
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',args:['--no-sandbox','--host-resolver-rules=MAP *.servicom.test 127.0.0.1']});
const ctx=await b.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});const p=await ctx.newPage();
await p.goto(process.argv[2],{waitUntil:'networkidle'});
console.log(JSON.stringify(await p.evaluate(()=>{const o=[];document.querySelectorAll('body *').forEach(e=>{const r=e.getBoundingClientRect();if(r.right>400&&getComputedStyle(e).position!=='fixed'){o.push(e.tagName+'.'+(e.className&&e.className.baseVal===undefined?e.className:'')+' '+Math.round(r.right));}});return o.slice(0,15);}),null,1));await b.close();})();
