# Services

## Propósito
Lógica de negocio que cruza varias tablas y debe ser atómica. Hoy solo `Facturacion/`.

## Facturacion/

### FacturaService
`crear(string $tipo, array $data): Movimiento` — `$tipo` ∈ `compra | venta | produccion`. Todo dentro de `DB::transaction`:
1. total = Σ `cantidadFacturable × precio_unitario` (null en producción).
2. Si `contado`: valida `saldoActual()` de la cuenta ≥ total (`RuntimeException` si no).
3. Crea `Movimiento` (`cuenta_efectivo_id` solo si contado) y sus `MovimientoItem`.
4. Cada línea → `MovimientoItemProcessor::procesar()`.
5. Si `credito`: crea `CuentaPendiente` (`por_pagar` para compra, `por_cobrar` para venta) con `serie_id = id`, vencimiento = `fecha_maxima_pago`.

`eliminar()` (borrado físico) y `anular()` (`anulado=true`) revierten líneas y borran la cuenta pendiente. Ambos exigen `puedeEliminarseOAnularse()`: sin abonos a la cuenta pendiente, stock/medicina de la línea intacto, sacos todos `en_espera`, y lote creado sin aves vendidas.

### MovimientoItemProcessor
Efecto de cada línea según `categoriaNombre()`. `procesar()` / `revertir()` con `match` — categoría no listada lanza `RuntimeException`. Detalle por categoría: tabla en [docs/DOMAIN.md](../../docs/DOMAIN.md#línea-de-factura-movimiento_items).

Validación de disponibilidad (aves, huevos) **aquí**, dentro de la transacción; venta de aves usa `lockForUpdate()` sobre el lote.

## Reglas
- Categoría nueva en facturas: agregar a `procesar()`, `revertir()`, `puedeEliminarseOAnularse()`, al `withValidator` de cada Request y a `cantidadFacturable` si aporta un campo de cantidad nuevo.
- No meter reglas de factura en controllers.
- Si hay un `RuntimeException` de negocio nuevo, ver deuda #4 en [docs/TAREAS.md](../../docs/TAREAS.md).
