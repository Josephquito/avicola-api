# Convenciones

Base: reglas Laravel Boost en [../CLAUDE.md](../CLAUDE.md) (Pint, Pest, `make:` de artisan, etc.). Aquí solo lo propio del proyecto.

## Nombres
- Dominio en español (`Lote`, `CuentaPendiente`, `SacoAlimento`), igual que tablas, columnas y textos de error. Términos técnicos en inglés donde ya es estándar Laravel.
- Tablas en plural español `snake_case` (`cuentas_efectivo`, `unidades_medida`, `producciones_huevos`). Si el plural no se infiere, el modelo define `$table`.
- Rutas en `kebab-case` plural (`/cuentas-efectivo`, `/sacos-alimento`). Route model binding con parámetro con nombre del modelo en `snake_case` (`{saco_alimento}`, `{cuenta_pendiente}`); para `cuentas-efectivo` el parámetro es `{cuentas_efectivo}`.
- Acciones no-CRUD = `POST /recurso/{id}/accion-en-kebab` (`/anular`, `/iniciar-uso`, `/marcar-produccion`) y método `camelCase` en el controller.
- Controllers en `app/Http/Controllers/Api/`, un controller por recurso. Subcarpeta solo para agrupar (`Admin/`, `movimientos/`).

## Estructura de un recurso nuevo
```
routes/api.php                  → dentro de auth:sanctum
app/Models/<Recurso>.php        → #[Fillable([...])], casts(), use Auditable, activityDescription()
app/Http/Controllers/Api/<Recurso>Controller.php
database/migrations/            → php artisan make:migration
```
Si el flujo tiene líneas/inventario/dinero → `FormRequest` + método en `FacturaService` (no en el controller). Referencia mínima de CRUD completo: `CategoriaController`. Referencia de flujo complejo: `movimientos/CompraController` + `FacturaService`.

## Modelos
- `#[Fillable([...])]` como atributo (Laravel 13), `casts()` como método. **Todo campo asignado con `create()`/`update()` debe estar en `Fillable`** — si no, se descarta en silencio.
- Todo modelo de negocio usa `Auditable`. Los que solo usan `Auth::id()` para autor guardan `user_id` explícito.
- Lógica de dominio de una entidad = método en el modelo (`Lote::corregirSexo()`, `SacoAlimento::iniciarUso()`, `CuentaPendiente::estaSaldada()`), no en el controller.

## Validación
- Recurso simple: `$request->validate([...])` inline en el controller.
- Flujo de factura: `FormRequest` con reglas base + `withValidator()` que exige campos según categoría del producto.
- Mensajes de error de negocio en español, claros para el usuario final (el front los muestra tal cual).

## Borrado
- Catálogos y entidades referenciadas (`contactos`, `productos`, `cuentas-efectivo`): **soft-disable** (`activo=false`), `DELETE` responde `{message}`. Crear de nuevo con mismo identificador **reactiva** el existente (contactos por cédula/teléfono, cuentas por número/nombre).
- Facturas: `DELETE` borra físico solo si nada dependiente; `POST .../anular` marca `anulado=true`. Ambos pasan por `FacturaService`.
- Cuenta pendiente con pago/cobro: no se elimina.

## Dinero y ACID
- Todo flujo que cree más de un registro (movimiento + línea + inventario + cuenta pendiente) va en `DB::transaction`.
- Validar disponibilidad (saldo, stock, aves, huevos) **dentro** de la transacción, con `lockForUpdate()` donde haya carrera (ver `procesarAve`). El `FormRequest` valida forma, no estado.
- Montos: `decimal:2` en casts; comparar con `bccomp`, no con `==` sobre floats.
- Nunca movimientos huérfanos: todo movimiento con dinero → `cuenta_efectivo_id` (o `cuenta_pendiente` si es crédito).

## Tests
Pest (`php artisan test --compact`). Hoy solo hay `ExampleTest` — ver [TAREAS.md](TAREAS.md). Tests nuevos: Feature, con factories, en `tests/Feature/`.
