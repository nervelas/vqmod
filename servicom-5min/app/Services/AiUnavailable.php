<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * La IA no está disponible (sin clave, sin presupuesto, archivo demasiado
 * grande, error de red...). El mensaje ya viene en español amable y puede
 * mostrarse tal cual al usuario.
 */
class AiUnavailable extends \RuntimeException
{
}
