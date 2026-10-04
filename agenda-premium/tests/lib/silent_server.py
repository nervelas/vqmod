#!/usr/bin/env python3
"""Acepta conexiones y nunca responde (solo pruebas de tiempos de espera). Uso: silent_server.py PUERTO"""
import socket, sys, time

s = socket.socket()
s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind(('127.0.0.1', int(sys.argv[1])))
s.listen(5)
conns = []
while True:
    conns.append(s.accept()[0])
    time.sleep(0.01)
