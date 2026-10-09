# Pruebas del mu-plugin Servicom Core (W2a)

Requisitos: `php` CLI con mysqli/curl, MariaDB local (`mysql` root por socket), `/opt/wp-core`.

- `./run_all.sh --fresh` crea el sitio (`/tmp/t2a`, BD `t2a_core`, prefijo aleatorio, `php -S 127.0.0.1:8111`) y corre todo.
- `./run_all.sh` solo sincroniza el código (`sync.sh`) y reejecuta. Redirija la salida a un archivo si la canaliza (el servidor queda en segundo plano).
- Pruebas sueltas: `php t_business.php | t_hardening | t_roles | t_instructions | t_preview | t_domain | t_selfcheck`.
- `lib.php` (aserciones y HTTP), `seed.php` (datos de prueba), `fixtures/` (tema trivial y simulador de wordpress.org, solo pruebas).
- Al final se revisa que `/tmp/t2a/debug.log` quede vacío.
