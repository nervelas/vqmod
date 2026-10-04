<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/** Enlaza los eventos de una cita con flujos de trabajo y webhooks. Un fallo aquí nunca rompe la reserva. */
final class Hooks
{
    public static function booking(string $event, int $bookingId): void
    {
        try {
            WorkflowService::fire($event, $bookingId);
        } catch (\Throwable $e) {
            Logger::error('Flujos: falló ' . $event . ' para la cita ' . $bookingId, $e);
        }
        try {
            WebhookService::dispatch($event, WebhookService::bookingPayload($bookingId));
        } catch (\Throwable $e) {
            Logger::error('Webhooks: falló ' . $event . ' para la cita ' . $bookingId, $e);
        }
    }
}
