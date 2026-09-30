# Tareas

## Pendientes
Hallazgos al documentar (2026-09-29), sin corregir aún — confirmar con Joseph antes de tocar:

1. **`Lote` no tiene `etapa` en `#[Fillable]`** — `LoteController::marcarProduccion` hace `update(['etapa' => 'produccion'])`, que se descarta en silencio. Verificar y agregar `'etapa'`.
2. **`saldoActual()` ignora facturas** — `CuentaEfectivo::saldoActual()` y `CuentaEfectivoController::movimientos()` usan tipos viejos (`compra_alimento`, `venta_aves`, …). Los tipos actuales `compra` y `venta` (contado, con `cuenta_efectivo_id`) no suman ni restan, ni se excluyen los `anulado=true`. Además `gasto_operativo` está en `saldoActual()` pero no en `movimientos()`.
3. **Namespace vs carpeta** — carpeta `Controllers/Api/movimientos/` pero namespace `...\Movimientos`. Funciona en macOS (case-insensitive), rompe en Linux/Docker con PSR-4. Renombrar carpeta a `Movimientos/`.
4. **`RuntimeException` de `FacturaService`** (saldo insuficiente, stock, no se puede anular) no se convierte a 422 → responde 500. Convertir en handler o lanzar `ValidationException`.
5. **Cobro/pago siempre saldan** — `saldo_pendiente` se pone en 0 sin importar `monto`; no hay abonos parciales.
6. **Código muerto** — `MovimientoController::generarSiguienteCuotaSiAplica` (usa `recurrencia_id`, tabla retirada en la migración de simplificación); modelo/tabla `ProduccionHuevo` reemplazados por líneas de `produccion`.
7. **Sin tests** — solo `ExampleTest`. Prioridad: `FacturaService` (crear/anular/eliminar por categoría), `saldoActual`, cobro/pago.
8. **Front desactualizado** — `frontend/docs` y features mencionan endpoints `compra-muebles`, `venta-huevos`, etc. que ya no existen en `routes/api.php`.
9. **`AGENTS.md` duplica `CLAUDE.md`** (bloque Boost). Cambios propios solo en `CLAUDE.md` (arriba del bloque).

## En progreso

## Hechas
