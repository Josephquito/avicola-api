<?php

namespace App\Services\Facturacion;

use App\Models\CuentaEfectivo;
use App\Models\CuentaPendiente;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\MovimientoItem;
use App\Models\SacoAlimento;
use App\Models\StockProducto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FacturaService
{
    public function __construct(private MovimientoItemProcessor $procesador) {}

    /**
     * @param string $tipo 'compra' | 'venta' | 'produccion'
     * @param array $data Shape ya validado por el Request correspondiente:
     *   fecha, descripcion?, items[] siempre presente,
     *   y para compra/venta además: contacto_id, estado, cuenta_efectivo_id?
     */
    public function crear(string $tipo, array $data): Movimiento
    {
        return DB::transaction(function () use ($tipo, $data) {
            $esConDinero = $tipo !== 'produccion';

            $total = $esConDinero
                ? collect($data['items'])->sum(
                    fn ($item) => $this->cantidadFacturable($item) * ($item['precio_unitario'] ?? 0)
                )
                : null;

            if ($esConDinero && $data['estado'] === 'contado') {
                $this->validarSaldoSuficiente($data['cuenta_efectivo_id'], $total);
            }

            $movimiento = Movimiento::create([
                'tipo' => $tipo,
                'fecha' => $data['fecha'],
                'monto' => $total,
                'contacto_id' => $data['contacto_id'] ?? null,
                'estado' => $data['estado'] ?? null,
                'cuenta_efectivo_id' => ($esConDinero && ($data['estado'] ?? null) === 'contado') ? $data['cuenta_efectivo_id'] : null,
                'descripcion' => $data['descripcion'] ?? null,
                'user_id' => auth()->id(),
            ]);

            foreach ($data['items'] as $itemData) {
                $item = $this->crearItem($movimiento, $itemData, $tipo);
                $this->procesador->procesar($item, $tipo);
            }

            $cuentaPendiente = null;

            if ($esConDinero && $data['estado'] === 'credito') {
                $cuentaPendiente = CuentaPendiente::create([
                    'tipo' => $tipo === 'compra' ? 'por_pagar' : 'por_cobrar',
                    'contacto_id' => $data['contacto_id'],
                    'concepto' => $data['descripcion'] ?? ucfirst($tipo),
                    'monto_original' => $movimiento->monto,
                    'saldo_pendiente' => $movimiento->monto,
                    'movimiento_origen_id' => $movimiento->id,
                    'fecha' => $data['fecha_maxima_pago'] ?? $data['fecha'],
                ]);

                // Toda cuenta pendiente arranca como raíz de su propia
                // serie — mismo patrón que CuentaPendienteController::store().
                $cuentaPendiente->update(['serie_id' => $cuentaPendiente->id]);
            }

            return $movimiento->fresh(['items', 'contacto', 'cuentaEfectivo']);
        });
    }

    private function crearItem(Movimiento $movimiento, array $itemData, string $tipoMovimiento): MovimientoItem
    {
        $precioUnitario = $itemData['precio_unitario'] ?? null;
        $subtotal = $tipoMovimiento !== 'produccion' && $precioUnitario !== null
            ? $this->cantidadFacturable($itemData) * $precioUnitario
            : null;

        return $movimiento->items()->create([
            ...$itemData,
            'subtotal' => $subtotal,
        ]);
    }

    /// La cantidad relevante de una línea, sin importar qué campo la
    /// trae según la categoría del producto. Para Aves-venta suma
    /// gallinas + gallos (puede venir cualquiera de los dos, o ambos).
    private function cantidadFacturable(array $itemData): float
    {
        if (isset($itemData['cantidad'])) {
            return (float) $itemData['cantidad'];
        }
        if (isset($itemData['cantidad_sacos'])) {
            return (float) $itemData['cantidad_sacos'];
        }
        if (isset($itemData['cantidad_gallinas']) || isset($itemData['cantidad_gallos'])) {
            return (float) ($itemData['cantidad_gallinas'] ?? 0) + (float) ($itemData['cantidad_gallos'] ?? 0);
        }
        if (isset($itemData['cantidad_huevos'])) {
            return (float) $itemData['cantidad_huevos'];
        }
        return 0.0;
    }

    private function validarSaldoSuficiente(int $cuentaEfectivoId, float $total): void
    {
        $cuenta = CuentaEfectivo::find($cuentaEfectivoId);

        if ($total > $cuenta->saldoActual()) {
            throw new RuntimeException('Saldo insuficiente en la cuenta seleccionada.');
        }
    }

    public function eliminar(Movimiento $movimiento): void
    {
        if (! $this->puedeEliminarseOAnularse($movimiento)) {
            throw new RuntimeException('No se puede eliminar: ya tiene datos que dependen de esta factura. Usa "Anular" o revísalo manualmente.');
        }

        DB::transaction(function () use ($movimiento) {
            foreach ($movimiento->items as $item) {
                $this->procesador->revertir($item);
            }

            CuentaPendiente::where('movimiento_origen_id', $movimiento->id)->delete();

            $movimiento->items()->delete();
            $movimiento->delete();
        });
    }

    public function anular(Movimiento $movimiento): void
    {
        if ($movimiento->anulado) {
            throw new RuntimeException('Esta factura ya está anulada.');
        }

        if (! $this->puedeEliminarseOAnularse($movimiento)) {
            throw new RuntimeException('No se puede anular: ya hay datos que dependen de esta factura (stock consumido, aves vendidas, o pagos registrados).');
        }

        DB::transaction(function () use ($movimiento) {
            foreach ($movimiento->items as $item) {
                $this->procesador->revertir($item);
            }

            $cuentaPendiente = CuentaPendiente::where('movimiento_origen_id', $movimiento->id)->first();
            $cuentaPendiente?->delete();

            $movimiento->update([
                'anulado' => true,
                'anulado_en' => now(),
            ]);
        });
    }

    /// Una factura solo se puede eliminar/anular si nada "consumió" lo
    /// que generó: sin abonos a su CuentaPendiente, sin stock/sacos ya
    /// gastados, y sin aves vendidas de un lote que ella misma creó.
    private function puedeEliminarseOAnularse(Movimiento $movimiento): bool
    {
        $cuentaPendiente = CuentaPendiente::where('movimiento_origen_id', $movimiento->id)->first();
        if ($cuentaPendiente && bccomp($cuentaPendiente->saldo_pendiente, $cuentaPendiente->monto_original, 2) !== 0) {
            return false;
        }

        foreach ($movimiento->items as $item) {
            $categoria = $item->categoriaNombre();

            if (in_array($categoria, ['Muebles y enseres', 'Medicina'])) {
                $sigueIntacto = StockProducto::where('movimiento_item_id', $item->id)
                    ->where('tipo_movimiento', 'compra')
                    ->exists();
                if (! $sigueIntacto) return false;
            }

            if ($categoria === 'Alimento' && $item->producto->requiere_ciclo_saco) {
                $todosSinUsar = SacoAlimento::where('movimiento_item_id', $item->id)
                    ->where('estado', 'en_espera')
                    ->count() === (int) $item->cantidad_sacos;
                if (! $todosSinUsar) return false;
            }

            if ($categoria === 'Aves' && $item->lote_id === null) {
                // compra/producción: el lote que creó no debe haber
                // sido vendido/reducido desde entonces
                $lote = Lote::where('movimiento_item_id', $item->id)->first();
                if ($lote && $lote->cantidadTotal() !== (int) $item->cantidad) return false;
            }
        }

        return true;
    }
}