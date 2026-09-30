# Arquitectura

## Capas
`route` (`routes/api.php`, `auth:sanctum`) → `controller` (`app/Http/Controllers/Api/`) → `FormRequest` (validación, solo en el flujo de facturas) → `Service` (`app/Services/Facturacion/`, transacción + reglas) → `Model` (Eloquent, trait `Auditable`).

Controllers simples (CRUD de catálogos, cuentas, lotes, sacos, stock) validan inline con `$request->validate()` y hablan directo con el modelo. El flujo de **facturas** (compra / venta / producción) es el único que usa `FormRequest` + `Service`.

Toda la lógica de negocio (doble partida, ACID, saldos, stock, permisos) vive aquí. El frontend solo envía datos y muestra errores — ver `frontend/docs/ARCHITECTURE.md`.

## API
- REST JSON, prefijo `/api`, sin versionado ni Eloquent Resources (los controllers devuelven modelos/arrays con `response()->json()`).
- Auth: Laravel Sanctum, token Bearer. `POST /login` (público) devuelve `{user, token}`; `POST /logout` borra el token actual. Resto de rutas dentro de `auth:sanctum`.
- Rutas admin: grupo `is_admin` (`IsAdmin`, alias en `bootstrap/app.php`) → `403 {message: 'No autorizado.'}`. Hoy solo `admin/users`.
- Errores de negocio: `422 {message}` (controllers) o `RuntimeException` desde el service. Ver deuda en [TAREAS.md](TAREAS.md): el `RuntimeException` no se convierte a 422.
- Listados paginados (`paginate(30)`): movimientos, sacos, stock, cuentas pendientes, activity logs. El resto devuelve array completo.
- Índice de endpoints: [app/Http/CLAUDE.md](../app/Http/CLAUDE.md).

## Flujo de una factura (compra / venta / producción)
1. `StoreCompraRequest` / `StoreVentaRequest` / `StoreProduccionRequest` valida forma y exige campos por línea según la **categoría del producto** (`withValidator`).
2. Controller (`app/Http/Controllers/Api/movimientos/`) llama `FacturaService::crear($tipo, $validated)`.
3. Dentro de `DB::transaction`: calcula total → valida saldo si es `contado` → crea `Movimiento` → por cada línea crea `MovimientoItem` y `MovimientoItemProcessor::procesar()` (efecto en inventario) → si `credito`, crea `CuentaPendiente` (raíz de su propia serie).
4. `eliminar()` y `anular()` revierten los efectos de cada línea (`revertir()`), solo si nada consumió lo generado (`puedeEliminarseOAnularse`).

Detalle por categoría: [app/Services/CLAUDE.md](../app/Services/CLAUDE.md).

## Movimientos simples (sin líneas)
`MovimientoController`: aporte/retiro de capital, transferencia (+ comisión como movimiento aparte), cobro, pago, gasto operativo. Cada uno valida inline y crea `Movimiento`; cobro/pago/gasto usan transacción.

## Saldo de cuentas
No hay columna de saldo. `CuentaEfectivo::saldoActual()` suma `movimientos` por `tipo` (listas de entradas/salidas hardcodeadas en el modelo). Todo movimiento de dinero **debe** tener `cuenta_efectivo_id` y su `tipo` estar en esas listas — si se agrega un tipo nuevo, actualizar `saldoActual()` **y** `CuentaEfectivoController::movimientos()`.

## Auditoría (bitácora)
Trait `App\Traits\Auditable` en cada modelo: al `created/updated/deleted` escribe `activity_logs` (`user_id`, `action`, `model_type`, `model_id`, `description`, `changes`). Cada modelo puede sobreescribir `activityDescription()` para el texto. El login también escribe log a mano en `AuthController`. Se expone en `GET /activity-logs`.

## Base de datos
- SQLite por defecto en dev (`database/database.sqlite`, `.env.example`). Docker: `docker-compose.yml` → contenedor `avicola-api`, puerto host 3007, nginx + supervisord (`docker/`).
- Migraciones en `database/migrations/` (historial de cambios de esquema, incluidas las que simplificaron recurrencias y pasaron a facturas por líneas).
- Seeders: `AdminUserSeeder`, `CategoriaSeeder` (Aves, Huevos, Alimento, Medicina, Muebles y enseres), `UnidadMedidaSeeder`. **Las categorías por nombre son parte del contrato**: el código hace `match` sobre `categoria->nombre`.
