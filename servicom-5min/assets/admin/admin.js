(function(){
  var b=document.querySelector('button.menu'),n=document.querySelector('nav.side');
  if(b&&n){b.addEventListener('click',function(){var o=n.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false');});}
  document.querySelectorAll('[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(f.getAttribute('data-confirm'))){e.preventDefault();}});});
  var el=document.querySelector('[data-tick]');
  if(el){var id=el.getAttribute('data-tick');var run=function(){fetch('/admin/construir/'+id,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    var bar=document.getElementById('tick-bar'),msg=document.getElementById('tick-msg');
    if(bar){bar.style.width=(j.progreso||0)+'%';}if(msg){msg.textContent=(j.estado||'')+' · '+(j.progreso||0)+'%'+(j.mensaje?' · '+j.mensaje:'');}
    if(j.estado==='construyendo'){setTimeout(run,1500);}else{location.reload();}
  }).catch(function(){setTimeout(run,4000);});};run();}
})();
