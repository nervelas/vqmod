<?php
declare(strict_types=1);

namespace MenuGold\Core;

use MenuGold\Models\Restaurant;

final class Middleware
{
    public static function run(string $name, array $opts = []): void
    {
        switch ($name) {
            case 'instalado':
                if (!App::installed()) redirect('install/');
                break;

            case 'auth':
                Auth::tryRemember();
                if (!Auth::check()) {
                    Session::set('_intended', Request::fullUrl());
                    if (Request::isAjax()) json_out(['ok' => false, 'error' => 'Sesión expirada', 'redirect' => App::url('ingresar')], 401);
                    redirect('ingresar');
                }
                break;

            case 'invitado':
                if (Auth::check()) redirect(Auth::homeFor());
                break;

            case 'super':
                if (!Auth::isSuper()) throw HttpException::forbidden('Solo el administrador de la plataforma puede entrar aquí.');
                break;

            case 'restaurante':
                // El usuario debe pertenecer a un restaurante activo
                $rid = Auth::restaurantId();
                if ($rid <= 0) {
                    if (Auth::isSuper()) {
                        $rid = (int)Session::get('super_rest', 0);
                        if ($rid <= 0) redirect('super/restaurantes');
                    } else {
                        throw HttpException::forbidden('Tu usuario no está asignado a un restaurante.');
                    }
                }
                $r = (new Restaurant())->find($rid);
                if (!$r) throw HttpException::forbidden('Restaurante no disponible.');
                if ($r['estado'] === 'suspendido' && !Auth::isSuper()) {
                    throw HttpException::forbidden('La cuenta de tu restaurante está suspendida.');
                }
                App::setRestaurant($r);
                break;

            case 'csrf':
                if (Request::isPost()) Csrf::enforce();
                break;

            case 'permiso':
                $p = (string)($opts['permiso'] ?? '');
                if ($p !== '' && !Auth::can($p)) throw HttpException::forbidden();
                break;
        }
    }
}
