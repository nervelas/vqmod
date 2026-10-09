import { PHP } from '@php-wasm/universal'; import { loadNodeRuntime } from '@php-wasm/node'; import fs from 'fs';
const php = new PHP(await loadNodeRuntime('8.0',{emscriptenOptions:{processId:1}}));
const root='/home/user/vqmod/servicom-5min';
php.mkdir('/app'); php.mkdir('/app/app'); php.mkdir('/app/app/Core'); php.mkdir('/app/tests');
for (const f of ['Config','Crypto']) php.writeFile(`/app/app/Core/${f}.php`, fs.readFileSync(`${root}/app/Core/${f}.php`));
php.writeFile('/app/tests/crypto.php', fs.readFileSync(`${root}/tests/crypto.php`));
const r = await php.run({ scriptPath: '/app/tests/crypto.php' });
console.log(r.text.split('\n').filter(l=>/FAIL|INFO|Crypto:/.test(l)).join('\n')); process.exit(0);
