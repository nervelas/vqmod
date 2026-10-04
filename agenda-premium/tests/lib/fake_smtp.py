#!/usr/bin/env python3
"""Servidor SMTP de prueba (solo pruebas). Registra cada correo recibido en un archivo JSONL.
Uso: fake_smtp.py PUERTO MODO SALIDA [--cert C --key K] [--auth usuario:clave]
MODO: plain | ssl (SSL implícito) | starttls
Si existe el archivo SALIDA.fail con un número N > 0, rechaza los siguientes N mensajes con 451."""
import base64, json, os, socket, ssl, sys, threading

port, mode, out = int(sys.argv[1]), sys.argv[2], sys.argv[3]
args = sys.argv[4:]
cert = args[args.index('--cert') + 1] if '--cert' in args else None
key = args[args.index('--key') + 1] if '--key' in args else None
auth = args[args.index('--auth') + 1] if '--auth' in args else None
advertise = args[args.index('--auth-mechs') + 1] if '--auth-mechs' in args else 'LOGIN PLAIN'
lock = threading.Lock()


def record(entry):
    with lock:
        with open(out, 'a') as f:
            f.write(json.dumps(entry) + '\n')


def take_failure():
    p = out + '.fail'
    with lock:
        try:
            n = int(open(p).read().strip() or 0)
        except Exception:
            return False
        if n > 0:
            open(p, 'w').write(str(n - 1))
            return True
    return False


def handle(conn):
    ctx = None
    if cert:
        ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        ctx.load_cert_chain(cert, key)
    if mode == 'ssl':
        try:
            conn = ctx.wrap_socket(conn, server_side=True)
        except Exception:
            return
    def send(s):
        conn.sendall((s + '\r\n').encode())

    buf = b''

    def rl():
        nonlocal buf
        while b'\r\n' not in buf:
            d = conn.recv(65536)
            if not d:
                return None
            buf += d
        line, buf = buf.split(b'\r\n', 1)
        return line.decode('utf-8', 'replace')

    send('220 fake.test ESMTP listo')
    state = {'from': None, 'rcpt': [], 'user': None, 'tls': mode == 'ssl'}
    while True:
        line = rl()
        if line is None:
            break
        cmd = line.upper()
        if cmd.startswith('EHLO'):
            caps = ['250-fake.test', '250-8BITMIME']
            if mode == 'starttls' and not state['tls']:
                caps.append('250-STARTTLS')
            if auth:
                caps.append('250-AUTH ' + advertise)
            caps[-1] = '250 ' + caps[-1][4:]
            send('\r\n'.join(caps))
        elif cmd.startswith('HELO'):
            send('250 fake.test')
        elif cmd == 'STARTTLS':
            send('220 listo para TLS')
            conn = ctx.wrap_socket(conn, server_side=True)
            buf = b''
            state['tls'] = True
        elif cmd.startswith('AUTH PLAIN'):
            parts = line.split(' ')
            tok = parts[2] if len(parts) > 2 else None
            if tok is None:
                send('334 ')
                tok = rl()
            try:
                _, u, p = base64.b64decode(tok).decode().split('\0')
            except Exception:
                send('501 mal formado')
                continue
            if auth == u + ':' + p:
                state['user'] = u
                send('235 autenticado')
            else:
                send('535 credenciales incorrectas')
        elif cmd.startswith('AUTH LOGIN'):
            send('334 ' + base64.b64encode(b'Username:').decode())
            u = base64.b64decode(rl()).decode()
            send('334 ' + base64.b64encode(b'Password:').decode())
            p = base64.b64decode(rl()).decode()
            if auth == u + ':' + p:
                state['user'] = u
                send('235 autenticado')
            else:
                send('535 credenciales incorrectas')
        elif cmd.startswith('MAIL FROM:'):
            if auth and not state['user']:
                send('530 se requiere autenticacion')
                continue
            state['from'] = line[10:].strip().strip('<>')
            state['rcpt'] = []
            send('250 ok')
        elif cmd.startswith('RCPT TO:'):
            addr = line[8:].strip().strip('<>')
            if 'rechazado' in addr:
                send('550 buzon no existe')
            else:
                state['rcpt'].append(addr)
                send('250 ok')
        elif cmd == 'DATA':
            send('354 adelante')
            acc = buf
            buf = b''
            while b'\r\n.\r\n' not in b'\r\n' + acc:
                d = conn.recv(65536)
                if not d:
                    return
                acc += d
            full = b'\r\n' + acc
            idx = full.index(b'\r\n.\r\n')
            data = full[2:idx]
            buf = full[idx + 5:]
            # quitar relleno de puntos
            text = data.decode('utf-8', 'replace')
            text = '\r\n'.join(l[1:] if l.startswith('..') else l for l in text.split('\r\n'))
            if take_failure():
                send('451 error temporal, intenta mas tarde')
                continue
            record({'from': state['from'], 'rcpt': state['rcpt'], 'user': state['user'], 'tls': state['tls'], 'data': text})
            send('250 aceptado')
        elif cmd == 'RSET':
            send('250 ok')
        elif cmd == 'QUIT':
            send('221 adios')
            break
        else:
            send('502 comando no implementado')
    try:
        conn.close()
    except Exception:
        pass


srv = socket.socket()
srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
srv.bind(('127.0.0.1', port))
srv.listen(8)
while True:
    c, _ = srv.accept()
    threading.Thread(target=handle, args=(c,), daemon=True).start()
