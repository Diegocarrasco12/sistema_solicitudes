<?php
/**
 * helpers_sla.php
 * Utilidades de SLA y semáforo para Servicios Generales (SG).
 *
 * Reglas por defecto:
 *  - SLA SG = 24 horas desde la fecha/hora de creación.
 *  - Semáforo:
 *      * RED   = vencido (now > fecha_vencimiento)
 *      * YELLOW= faltan 6h o menos para vencer
 *      * GREEN = faltan más de 6h
 *      * GREY  = sin fecha de vencimiento
 *
 * Si más adelante quieres SLA distintos por categoría, se puede extender
 * leyendo una tabla de configuración (p. ej., sla_categorias).
 */

declare(strict_types=1);

/**
 * Calcula la fecha/hora de vencimiento para un ticket de Servicios Generales.
 * @param DateTime $creado Fecha/hora de creación del ticket.
 * @return DateTime Fecha/hora de vencimiento (creado + 24h).
 */
function sg_calcular_vencimiento(DateTime $creado): DateTime {
  $v = clone $creado;
  $v->modify('+1 day'); // SLA base: 24 horas
  return $v;
}

/**
 * Devuelve el color del semáforo según la fecha de vencimiento.
 * @param string|null $fecha_vencimiento Fecha en formato 'Y-m-d H:i:s' o null.
 * @return string 'green' | 'yellow' | 'red' | 'grey'
 */
function color_semaforo(?string $fecha_vencimiento): string {
  if (!$fecha_vencimiento) return 'grey';
  try {
    $now = new DateTime('now');
    $v   = new DateTime($fecha_vencimiento);
  } catch (Exception $e) {
    // Si el formato no es válido, mostrar gris para no romper la UI
    return 'grey';
  }

  if ($now > $v) return 'red';

  $diffSeconds = $v->getTimestamp() - $now->getTimestamp();
  // Umbral de amarillo: 6 horas
  return ($diffSeconds <= 6 * 3600) ? 'yellow' : 'green';
}

/**
 * Devuelve una etiqueta legible del semáforo (opcional, por si quieres mostrar texto).
 * @param string $color
 * @return string
 */
function etiqueta_semaforo(string $color): string {
  switch ($color) {
    case 'red':    return 'VENCIDO';
    case 'yellow': return 'PRONTO A VENCER';
    case 'green':  return 'EN PLAZO';
    default:       return 'SIN FECHA';
  }
}
