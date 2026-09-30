# Models

## Propósito
Entidades Eloquent. Todas usan `Auditable` (bitácora automática) y `#[Fillable([...])]` (si un campo no está ahí, `create`/`update` lo descartan en silencio).

## Índice
| Modelo | Tabla | Notas |
|---|---|---|
| `User` | `users` | Sanctum `HasApiTokens`. `isAdmin()`, `isSocio()`. `password` hasheada por cast. |
| `ActivityLog` | `activity_logs` | Destino de `Auditable`; no usa `Auditable` él mismo. |
| `Contacto` | `contactos` | Proveedor/cliente. `activo`. |
| `CuentaEfectivo` | `cuentas_efectivo` | `saldoActual()` calcula saldo desde `movimientos` (listas de tipos hardcodeadas). |
| `Categoria` | `categorias` | `tipos_medida_permitidos` (array). El nombre es contrato del código. |
| `UnidadMedida` | `unidades_medida` | `tipo_medida`: conteo/peso/volumen. |
| `Producto` | `productos` | `requiere_ciclo_saco` (alimento en sacos). Sin unidad específica → ver `ProductoController`. |
| `Movimiento` | `movimientos` | Cabecera de toda transacción de dinero / factura. `anulado`. Relaciones: cuentaEfectivo, cuentaDestino, cuentaPendiente, socio, contacto, usuario, items. |
| `MovimientoItem` | `movimiento_items` | Línea de factura. `categoriaNombre()` deriva del producto qué comportamiento aplica. |
| `CuentaPendiente` | `cuentas_pendientes` | `estaSaldada()`, `estaVencida()`, `serie_id`. |
| `Lote` | `lotes` | `cantidadTotal()`, `edadActualDias()`, `corregirSexo()`, `iniciarEmpolla()`, `terminarEmpolla()`. |
| `SacoAlimento` | `sacos_alimento` | Ciclo `en_espera → en_uso → terminado`: `iniciarUso()`, `terminar()`, `diasDeUso()`. |
| `StockProducto` | `stock_productos` | Libro de stock: compra = cantidad positiva, consumo = negativa; stock actual = `sum(cantidad)`. |
| `ProduccionHuevo` | `producciones_huevos` | Legado — la producción de huevos hoy son líneas de `produccion`. |

Glosario y valores de columnas: [docs/DOMAIN.md](../../docs/DOMAIN.md).

## Reglas
- Lógica propia de una entidad = método aquí, no en el controller.
- `activityDescription()` en español, formato "{actor} {verbo} ...". Agregarlo a todo modelo nuevo.
- Nuevo campo: migración + agregarlo a `Fillable` + cast si aplica.
