<?php
function mk($name,$bg,$shapes){ $im=imagecreatetruecolor(400,200); imagesavealpha($im,true); imagealphablending($im,false);
 imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127)); imagealphablending($im,true);
 foreach($shapes as $s){ list($t,$c,$a)=array($s[0],$s[1],$s[2]); $col=imagecolorallocate($im,$c[0],$c[1],$c[2]);
  if($t=='e') imagefilledellipse($im,$a[0],$a[1],$a[2],$a[3],$col); else imagefilledrectangle($im,$a[0],$a[1],$a[2],$a[3],$col);} imagepng($im,"/tmp/logos/$name.png"); }
mk('rojo_negro',0,[['e',[200,30,40],[110,100,150,150]],['r',[20,20,20],[200,70,380,130]],['r',[200,30,40],[200,140,300,150]]]);
mk('azul_oro',0,[['e',[20,50,120],[110,100,160,160]],['r',[201,164,92],[200,80,380,100]],['r',[20,50,120],[200,110,330,125]]]);
mk('verde',0,[['e',[20,130,80],[200,100,170,170]]]);
mk('gris',0,[['e',[90,90,90],[200,100,170,170]],['r',[0,0,0],[100,90,300,110]]]);
mk('magenta_naranja',0,[['e',[220,30,140],[120,100,150,150]],['e',[250,140,20],[280,100,120,120]]]);
