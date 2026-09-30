# Dominio (glosario, lado backend)

Glosario de negocio general: `frontend/docs/DOMAIN.md`. Aquí lo que el backend define o impone, con los valores exactos que guarda la BD.

## Categorías de producto (contrato del código)
`Aves`, `Huevos`, `Alimento`, `Medicina`, `Muebles y enseres`. La categoría del producto decide qué campos exige una línea y qué inventario afecta. Nombre distinto → error "categoría no soportada". `tipos_medida_permitidos` limita las unidades (`conteo`, `peso`, `volumen`); además `ProductoController::UNIDADES_ESPECIFICAS` restringe Aves/Huevos a `Unidad` y Muebles a `Unidad/Caja/Funda`.

## Movimiento (`movimientos`)
`tipo` por familia:
- **Factura** (con líneas en `movimiento_items`): `compra`, `venta`, `produccion`. Campos: `estado` (`contado` | `credito`), `monto` = total de líneas, `anulado`, `anulado_en`. `produccion` no tiene dinero (`monto`, `estado`, `contacto` nulos).
- **Capital**: `aporte_capital`, `retiro_capital` (requiere `socio_id` con `es_socio`).
- **Transferencia**: `transferencia` (`cuenta_efectivo_id` → `cuenta_destino_id`) + `comision_transferencia` como movimiento aparte (0.31–1.00).
- **Deuda**: `cobro`, `pago` (ligados a `cuenta_pendiente_id`).
- **Gasto**: `gasto_operativo` (contado o crédito).

## Línea de factura (`movimiento_items`)
Campos según categoría:
| Categoría | Campos exigidos | Efecto |
|---|---|---|
| Muebles y enseres / Medicina | `cantidad` | fila en `stock_productos` (`tipo_movimiento` compra) |
| Alimento con `requiere_ciclo_saco` | `cantidad_sacos`, `peso_por_saco` | crea N `sacos_alimento` en `en_espera` |
| Alimento sin ciclo de saco | `cantidad` | como stock simple |
| Aves (compra/producción) | `cantidad`, `edad_inicial_dias` | crea un `Lote` (`origen` = `compra` / `nacimiento`) |
| Aves (venta) | `lote_id`, `cantidad_gallinas` y/o `cantidad_gallos` | descuenta del lote; si queda en 0 → `activo=false` |
| Huevos (producción) | `cantidad_huevos` | sin tabla propia; stock = producido − vendido |
| Huevos (venta) | `cantidad_huevos` | valida stock disponible |

`subtotal = cantidad facturable × precio_unitario`; cantidad facturable = `cantidad` | `cantidad_sacos` | gallinas+gallos | `cantidad_huevos`.

## Lote (`lotes`)
`origen`: `compra` | `nacimiento`. `etapa`: `levante` (default) → `produccion` (`marcar-produccion`). `cantidad_gallinas`, `cantidad_gallos`, `cantidad_empollando`, `edad_inicial_dias` (+ días desde `fecha_ingreso` = edad actual), `costo_total`, `activo`. `lote_origen_id` para lotes derivados. `movimiento_item_id` liga el lote a la línea que lo creó.

## Saco de alimento (`sacos_alimento`)
`estado`: `en_espera` → `en_uso` (`iniciar-uso`) → `terminado` (`terminar`). Transiciones inválidas → 422.

## Cuenta de efectivo (`cuentas_efectivo`)
`tipo`: `caja` | `banco` (banco exige `numero_cuenta`). Ojo: el glosario del front dice `efectivo`; el valor real en BD/API es `caja`. Saldo calculado, no guardado.

## Cuenta pendiente (`cuentas_pendientes`)
`tipo`: `por_cobrar` | `por_pagar`. `monto_original`, `saldo_pendiente` (`<= 0` → saldada), `fecha` (vencimiento), `movimiento_origen_id` (factura o gasto a crédito que la creó). `serie_id` agrupa cuotas recurrentes; toda cuenta nace con `serie_id = su propio id`; "recordar pago" en cobro/pago crea la siguiente heredando `serie_id`. `GET .../serie` devuelve la cadena y `total_pagado` real.

## Usuario
`role`: `admin` | `user`. `es_socio` independiente del rol. Contraseña de exactamente 6 dígitos (`digits:6`). No se puede borrar/degradar al último admin ni borrarse a sí mismo. Solo admin gestiona usuarios.

## Contacto
Proveedor y cliente unificados. Único activo por `cedula_ruc` y por `telefono`.

## Bitácora (`activity_logs`)
`action`: `created` | `updated` | `deleted` | `login`. `changes` = JSON de atributos modificados en `updated`.
