#!/usr/bin/env python3
"""Servidor SMTP de prueba: guarda cada mensaje recibido en un archivo JSON. Soporta AUTH LOGIN/PLAIN y un modo de fallo."""
import asyncio, base64, json, os, sys
PORT = int(sys.argv[1]); OUT = sys.argv[2]; FAIL = os.path.join(OUT, 'FAIL')
os.makedirs(OUT, exist_ok=True)
n = 0
async def handle(r, w):
    global n
    async def send(s): w.write((s + '\r\n').encode()); await w.drain()
    await send('220 test ESMTP')
    st = {'auth': None, 'from': '', 'to': []}
    while True:
        line = (await r.readline()).decode(errors='ignore').rstrip('\r\n')
        if not line: break
        u = line.upper()
        if u.startswith('EHLO'):
            w.write(b'250-test\r\n250-AUTH LOGIN PLAIN\r\n250 8BITMIME\r\n'); await w.drain()
        elif u.startswith('AUTH LOGIN'):
            await send('334 VXNlcm5hbWU6'); user = base64.b64decode((await r.readline()).strip()).decode()
            await send('334 UGFzc3dvcmQ6'); pw = base64.b64decode((await r.readline()).strip()).decode()
            st['auth'] = (user, pw); await send('235 ok')
        elif u.startswith('AUTH PLAIN'):
            parts = base64.b64decode(line.split()[2]).split(b'\0'); st['auth'] = (parts[1].decode(), parts[2].decode()); await send('235 ok')
        elif u.startswith('MAIL FROM'): st['from'] = line[10:]; await send('250 ok')
        elif u.startswith('RCPT TO'):
            if os.path.exists(FAIL): await send('550 rechazado'); continue
            st['to'].append(line[8:]); await send('250 ok')
        elif u == 'DATA':
            await send('354 go'); buf = b''
            while True:
                l = await r.readline()
                if l in (b'.\r\n', b''): break
                buf += l
            n += 1
            json.dump({'auth': st['auth'], 'from': st['from'], 'to': st['to'], 'data': buf.decode(errors='ignore')}, open(os.path.join(OUT, f'{n:04d}.json'), 'w'))
            await send('250 queued')
        elif u == 'QUIT': await send('221 bye'); break
        else: await send('250 ok')
    w.close()
async def main():
    s = await asyncio.start_server(handle, '127.0.0.1', PORT)
    async with s: await s.serve_forever()
asyncio.run(main())
