#!/usr/bin/env python3
"""API simulada: WhatsApp Cloud (/v21.0/<id>/messages) y verificación de captcha (/siteverify). Registra peticiones en un archivo."""
import json, sys
from http.server import BaseHTTPRequestHandler, HTTPServer
PORT = int(sys.argv[1]); LOG = sys.argv[2]
class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def do_POST(self):
        body = self.rfile.read(int(self.headers.get('Content-Length', 0))).decode()
        open(LOG, 'a').write(json.dumps({'path': self.path, 'auth': self.headers.get('Authorization'), 'body': body}) + '\n')
        if self.path.endswith('/siteverify'):
            ok = 'response=good' in body
            out = json.dumps({'success': ok}); code = 200
        elif 'BADTOKEN' in (self.headers.get('Authorization') or ''):
            out = json.dumps({'error': {'message': 'Invalid token'}}); code = 401
        else:
            out = json.dumps({'messages': [{'id': 'wamid.x'}]}); code = 200
        self.send_response(code); self.send_header('Content-Type', 'application/json'); self.end_headers(); self.wfile.write(out.encode())
HTTPServer(('127.0.0.1', PORT), H).serve_forever()
