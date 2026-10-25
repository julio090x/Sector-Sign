<?php
declare(strict_types=1);
/** Last authenticated receipt, not proof that Inventario Pro is currently online. */
function inventory_sync_health(?string $lastSync, bool $enabled, ?DateTimeImmutable $now = null): array {
    if (!$enabled) return ['status'=>'disabled','label'=>'Desactivada','age_minutes'=>null];
    if ($lastSync === null || trim($lastSync) === '') return ['status'=>'never','label'=>'Sin primera sincronización','age_minutes'=>null];
    $tz = new DateTimeZone('UTC');
    $last = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',trim($lastSync),$tz);
    if (!$last || $last->format('Y-m-d H:i:s') !== trim($lastSync)) {
        return ['status'=>'invalid_time','label'=>'Hora de sincronización inválida','age_minutes'=>null];
    }
    $now ??= new DateTimeImmutable('now',$tz);
    $elapsed = $now->getTimestamp() - $last->getTimestamp();
    if ($elapsed < -300) return ['status'=>'clock_skew','label'=>'Revisar hora del servidor','age_minutes'=>null];
    $minutes = max(0,intdiv($elapsed,60));
    if ($minutes <= 60) return ['status'=>'recent','label'=>'Solicitud firmada recibida recientemente','age_minutes'=>$minutes];
    if ($minutes <= 360) return ['status'=>'delayed','label'=>'Sin datos nuevos hace más de una hora','age_minutes'=>$minutes];
    return ['status'=>'stale','label'=>'Catálogo posiblemente desactualizado','age_minutes'=>$minutes];
}
