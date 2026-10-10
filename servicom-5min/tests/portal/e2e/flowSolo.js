const path=require('path').join(__dirname,'node_modules/playwright-core');
const { chromium } = require(path);
(async()=>{
  const W=+process.argv[2]||390;
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',args:['--no-sandbox','--host-resolver-rules=MAP *.servicom.test 127.0.0.1']});
  const ctx=await b.newContext({viewport:{width:W,height:800}}); const p=await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(String(e))); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
  await p.goto('http://crear.servicom.test:8201/crear',{waitUntil:'networkidle'});
  const vis=await p.evaluate(()=>[...document.querySelectorAll('input:not([type=hidden]):not(.sr),textarea,select')].filter(e=>e.offsetParent!==null).map(e=>e.id||e.name));
  console.log('campos visibles en la primera pantalla:',JSON.stringify(vis));
  
  await p.setInputFiles('#pres-file',require('path').join(__dirname,'../../fixtures/solo_pdf/empresa.pdf'));
  let t0=Date.now(), last='';
  while(Date.now()-t0<240000){ const st=await p.evaluate(()=>({step:(document.querySelector('.step:not([hidden])')||{}).dataset?.step, done:!document.getElementById('b-done').hidden, fail:!document.getElementById('b-fail').hidden, pct:document.getElementById('b-pct').textContent})); const k=JSON.stringify(st); if(k!==last){console.log(Math.round((Date.now()-t0)/1000)+'s',k); last=k;} if(st.done||st.fail||(st.pct==="100 %"))break; await p.waitForTimeout(1000);}
  await p.screenshot({path:'/tmp/express-2.png',fullPage:true});
  const href=await p.evaluate(()=>document.getElementById('b-view').href); const done=await p.evaluate(()=>!document.getElementById('b-done').hidden);
  console.log('vista previa:',href); console.log('errores consola:',JSON.stringify(errs));
  const okAll = vis.length===0 && done && !errs.length; console.log(okAll?'SOLO ARCHIVO: OK':'SOLO ARCHIVO: FALLO'); await b.close(); process.exit(okAll?0:1);
  await b.close();
})().catch(e=>{console.error('FALLO',e.message);process.exit(1)});
