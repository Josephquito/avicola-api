# Http (controllers, requests, middleware)

## Propósito
Capa de entrada de la API. Controllers delgados; la lógica con dinero/inventario vive en `app/Services/` y en métodos de modelo. Patrón general: [docs/ARCHITECTURE.md](../../docs/ARCHITECTURE.md), convenciones: [docs/CONVENTIONS.md](../../docs/CONVENTIONS.md).

## Middleware
`IsAdmin` (alias `is_admin`, `bootstrap/app.php`) — 403 si el usuario no es admin.

## Requests
`StoreCompraRequest`, `StoreVentaRequest`, `StoreProduccionRequest` — validan facturas; exigen campos por línea según la categoría del producto.

## Índice de endpoints
Todo bajo `/api`, `auth:sanctum` salvo `POST /login`. Fuente de verdad: `routes/api.php` (`php artisan route:list --path=api`).

| Recurso | Controller | Endpoints |
|---|---|---|
| Auth | `AuthController` | `POST /login`, `POST /logout`, `GET /user` |
| Usuarios (admin) | `Admin/UserController` | `admin/users` index/store/update/destroy |
| Bitácora | `ActivityLogController` | `GET /activity-logs` |
| Contactos | `ContactoController` | `contactos` index/store/update/destroy (soft) |
| Cuentas de efectivo | `CuentaEfectivoController` | `cuentas-efectivo` CRUD (soft) + `/{id}/saldo` + `/{id}/movimientos` (extracto con saldo corrido) |
| Categorías | `CategoriaController` | `categorias` CRUD |
| Unidades de medida | `UnidadMedidaController` | `unidades-medida` CRUD |
| Productos | `ProductoController` | `productos` CRUD (soft); valida compatibilidad categoría↔unidad |
| Movimientos simples | `MovimientoController` | `GET /movimientos` (filtros `tipo`, `cuenta_pendiente_id`, `fecha_desde`, `fecha_hasta`), `POST /movimientos/{aporte-capital, retiro-capital, transferencia, cobro, pago, gasto-operativo}` |
| Compras | `movimientos/CompraController` | `POST /compras`, `GET/DELETE /compras/{id}`, `POST /compras/{id}/anular` |
| Ventas | `movimientos/VentaController` | ídem `/ventas` |
| Producción | `movimientos/ProduccionController` | ídem `/producciones` |
| Huevos | `HuevoStatsController` | `GET /huevos/resumen?fecha_desde&fecha_hasta` — `stock_actual`, `total_producido`, `total_vendido`, `gallinas_en_produccion`, `tasa_produccion_diaria`, `serie_diaria`, `historial` |
| Cuentas pendientes | `CuentaPendienteController` | `cuentas-pendientes` index (filtros `tipo`, `contacto_id`, `solo_pendientes`)/store/update/destroy + `/{id}/serie` |
| Stock de productos | `StockProductoController` | `GET /stock-productos`, `/stock-productos/producto/{id}`, `POST /stock-productos/consumo` |
| Sacos de alimento | `SacoAlimentoController` | `GET /sacos-alimento` (`producto_id`, `estado`), `/producto/{id}/resumen`, `POST /{id}/iniciar-uso`, `/{id}/terminar` |
| Lotes | `LoteController` | `GET /lotes?solo_activos=`, `POST /lotes/{id}/corregir-sexo`, `/marcar-produccion` |

Sin `show` para la mayoría de recursos (`apiResource(...)->only([index, store, update, destroy])`).

## Reglas al agregar endpoint
- Ruta dentro de `auth:sanctum`; admin-only dentro del grupo `is_admin`.
- Antes de crear controller: revisar si el flujo encaja en `FacturaService` o en un método de modelo.
- Actualizar la tabla de arriba.
