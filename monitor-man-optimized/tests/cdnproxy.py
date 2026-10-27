#!/usr/bin/env python3
"""Minimal CDN emulator (Cloudflare 'Cache Everything' semantics):
GET + no wordpress_logged_in cookie + origin 200 + NO Set-Cookie  => cached 300s.
Anything with Set-Cookie is passed through uncached (BYPASS), like Cloudflare/Arvan."""
import http.server, socketserver, urllib.request, time, threading
ORIGIN='http://localhost:8080'; cache={}; lock=threading.Lock()
class H(http.server.BaseHTTPRequestHandler):
    protocol_version='HTTP/1.1'
    def log_message(self,*a): pass
    def _fwd(self, method, body=None):
        req=urllib.request.Request(ORIGIN+self.path, data=body, method=method, headers={k:v for k,v in self.headers.items() if k.lower() not in ('host','accept-encoding')})
        try: r=urllib.request.urlopen(req)
        except urllib.error.HTTPError as e: r=e
        return r.status, list(r.headers.items()), r.read()
    def _send(self, st, hdrs, data, state):
        self.send_response(st)
        for k,v in hdrs:
            if k.lower() not in ('transfer-encoding','connection','content-length'): self.send_header(k,v)
        self.send_header('Content-Length',str(len(data))); self.send_header('X-Cache',state); self.end_headers(); self.wfile.write(data)
    def do_GET(self):
        logged='wordpress_logged_in' in (self.headers.get('Cookie') or '')
        key=self.path
        with lock: hit=cache.get(key)
        if hit and not logged and hit[0]>time.time(): return self._send(*hit[1:], 'HIT')
        st,h,d=self._fwd('GET')
        cacheable = st==200 and not logged and not any(k.lower()=='set-cookie' for k,_ in h)
        if cacheable:
            with lock: cache[key]=(time.time()+300,st,h,d)
        self._send(st,h,d,'MISS' if cacheable else 'BYPASS')
    def do_POST(self):
        n=int(self.headers.get('Content-Length') or 0); st,h,d=self._fwd('POST', self.rfile.read(n)); self._send(st,h,d,'DYNAMIC')
class T(socketserver.ThreadingMixIn, http.server.HTTPServer): daemon_threads=True
T(('127.0.0.1',8090),H).serve_forever()
