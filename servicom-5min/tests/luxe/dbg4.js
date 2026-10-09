const path=require('path');const {chromium}=require(path.join(__dirname,'../portal/e2e/node_modules/playwright-core'));
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',args:['--no-sandbox','--host-resolver-rules=MAP *.servicom.test 127.0.0.1']});
const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto(process.argv[2],{waitUntil:'networkidle'});
await p.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=300){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,140));}});
await p.waitForTimeout(800);
console.log(JSON.stringify(await p.evaluate(()=>[...document.querySelectorAll('.lx-rv:not(.is-in)')].map(e=>e.className+' top='+Math.round(e.getBoundingClientRect().top+scrollY)+' h='+Math.round(e.getBoundingClientRect().height)+' sh='+document.body.scrollHeight)),null,1));
await b.close();})();
