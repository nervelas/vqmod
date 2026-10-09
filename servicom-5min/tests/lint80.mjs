import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime } from '@php-wasm/node';
import fs from 'fs'; import path from 'path';
const roots = process.argv.slice(2);
const php = new PHP(await loadNodeRuntime('8.0',{emscriptenOptions:{processId:1}}));
function walk(d, out=[]) { if (fs.statSync(d).isFile()) { if (d.endsWith('.php')) out.push(d); return out; } for (const e of fs.readdirSync(d,{withFileTypes:true})) { const p=path.join(d,e.name); if (e.isDirectory()) { if (['node_modules','.git','fonts','dist'].includes(e.name)) continue; walk(p,out);} else if (e.name.endsWith('.php')) out.push(p);} return out; }
const files = roots.flatMap(r => walk(r)); let bad = 0; php.mkdir('/lint');
for (const f of files) {
  php.writeFile('/lint/t.php', fs.readFileSync(f));
  const r = await php.run({ code: '<?php try { token_get_all(file_get_contents("/lint/t.php"), TOKEN_PARSE); echo "OK"; } catch (\\Throwable $e) { echo "ERR ".$e->getMessage()." L".$e->getLine(); }' });
  if (r.text !== 'OK') { bad++; console.log('FALLA', f, r.text); }
}
console.log(`PHP ${(await php.run({code:'<?php echo PHP_VERSION;'})).text}: ${files.length} archivos, ${bad} con errores de sintaxis`);
process.exit(bad?1:0);
