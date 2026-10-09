const path='/home/user/vqmod/servicom-5min/tests/portal/e2e/node_modules/playwright-core';
const {chromium}=require(path);
(async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROME||require('fs').readdirSync('/opt/pw-browsers').filter(d=>/^chromium-/.test(d)).map(d=>'/opt/pw-browsers/'+d+'/chrome-linux/chrome')[0]});
 const p=await b.newPage({viewport:{width:1700,height:900}});
 const t0=Date.now();
 await p.goto('file://'+__dirname+'/sheet.html');
 await p.waitForTimeout(500);
 console.log('render ms',Date.now()-t0);
 const figs=await p.$$('figure');
 // groups: by motif (20 figures each)
 const motifs=['scales','pulse','gear','fabric','plate','route','chart','globe','abstract'];
 for(let m=0;m<9;m++){
   // screenshot of first palette+others: clip region covering figures of this motif
   const first=await figs[m*20].boundingBox(); const last=await figs[m*20+19].boundingBox();
   const top=first.y-2; const bottom=last.y+last.height+24;
   await p.screenshot({path:`/tmp/art-sheet-${motifs[m]}.png`,fullPage:true,clip:{x:0,y:top,width:1700,height:bottom-top}});
 }
 await b.close();
})();
