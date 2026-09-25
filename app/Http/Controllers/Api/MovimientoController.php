<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaEfectivo;
use App\Models\CuentaPendiente;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\StockProducto;
use App\Models\SacoAlimento;
use App\Models\Lote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
    $query = Movimiento::with(['cuentaEfectivo', 'cuentaDestino', 'socio:id,name', 'contacto', 'usuario:id,name']);

    if ($request->has('tipo')) {
        $tipos = is_array($request->input('tipo'))
            ? $request->input('tipo')
            : explode(',', $request->input('tipo'));
        $query->whereIn('tipo', $tipos);
    }

    if ($request->has('cuenta_pendiente_id')) {
        $query->where('cuenta_pendiente_id', $request->input('cuenta_pendiente_id'));
    }

    return $query->latest('fecha')->paginate(30);
    }

    public function aportarCapital(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'socio_id' => ['required', 'exists:users,id'],
            'cuenta_efectivo_id' => ['required', 'exists:cuentas_efectivo,id'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $socio = User::find($data['socio_id']);
        if (! $socio->es_socio) {
            return response()->json(['message' => 'El usuario seleccionado no está marcado como socio.'], 422);
        }

        $movimiento = Movimiento::create([
            ...$data,
            'tipo' => 'aporte_capital',
            'user_id' => auth()->id(),
        ]);

        return response()->json($movimiento->load(['cuentaEfectivo', 'socio:id,name']), 201);
    }

    public function retirarCapital(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'socio_id' => ['required', 'exists:users,id'],
            'cuenta_efectivo_id' => ['required', 'exists:cuentas_efectivo,id'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $socio = User::find($data['socio_id']);
        if (! $socio->es_socio) {
            return response()->json(['message' => 'El usuario seleccionado no está marcado como socio.'], 422);
        }

        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }

        $movimiento = Movimiento::create([
            ...$data,
            'tipo' => 'retiro_capital',
            'user_id' => auth()->id(),
        ]);

        return response()->json($movimiento->load(['cuentaEfectivo', 'socio:id,name']), 201);
    }

public function transferir(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'cuenta_efectivo_id' => ['required', 'exists:cuentas_efectivo,id'],
        'cuenta_destino_id' => ['required', 'exists:cuentas_efectivo,id', 'different:cuenta_efectivo_id'],
        'comision' => ['nullable', 'numeric', 'min:0.31', 'max:1.00'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $comision = $data['comision'] ?? 0;
    $totalADescontar = $data['monto'] + $comision;

    $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
    if ($totalADescontar > $cuenta->saldoActual()) {
        return response()->json([
            'message' => 'Saldo insuficiente en la cuenta de origen (monto + comisión).',
        ], 422);
    }

    $movimiento = Movimiento::create([
        'tipo' => 'transferencia',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'cuenta_efectivo_id' => $data['cuenta_efectivo_id'],
        'cuenta_destino_id' => $data['cuenta_destino_id'],
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $movimientoComision = null;

    if ($comision > 0) {
        $movimientoComision = Movimiento::create([
            'tipo' => 'comision_transferencia',
            'fecha' => $data['fecha'],
            'monto' => $comision,
            'cuenta_efectivo_id' => $data['cuenta_efectivo_id'],
            'descripcion' => "Comisión por transferencia #{$movimiento->id}",
            'user_id' => auth()->id(),
        ]);
    }

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'cuentaDestino']),
        'comision' => $movimientoComision,
    ], 201);
}

public function cobrar(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'cuenta_efectivo_id' => ['required', 'exists:cuentas_efectivo,id'],
        'cuenta_pendiente_id' => ['required', 'exists:cuentas_pendientes,id'],
        'descripcion' => ['nullable', 'string'],
        'recordar_pago' => ['sometimes', 'boolean'],
        'fecha_siguiente' => ['required_if:recordar_pago,true', 'nullable', 'date', 'after_or_equal:today'],
        'monto_referencial_siguiente' => ['required_if:recordar_pago,true', 'nullable', 'numeric', 'min:0.01'],
    ]);

    $cuentaPendiente = CuentaPendiente::find($data['cuenta_pendiente_id']);

    if ($cuentaPendiente->tipo !== 'por_cobrar') {
        return response()->json(['message' => 'Esa cuenta pendiente no es una cuenta por cobrar.'], 422);
    }

    if ($cuentaPendiente->estaSaldada()) {
        return response()->json(['message' => 'Esta cuenta ya está saldada.'], 422);
    }

    return DB::transaction(function () use ($data, $cuentaPendiente) {
        $movimiento = Movimiento::create([
            'tipo' => 'cobro',
            'fecha' => $data['fecha'],
            'monto' => $data['monto'],
            'cuenta_efectivo_id' => $data['cuenta_efectivo_id'],
            'cuenta_pendiente_id' => $cuentaPendiente->id,
            'contacto_id' => $cuentaPendiente->contacto_id,
            'descripcion' => $data['descripcion'] ?? null,
            'user_id' => auth()->id(),
        ]);

        // El monto es referencial: cualquier pago salda la cuenta por
        // completo, sin importar si fue de más o de menos.
        $cuentaPendiente->update(['saldo_pendiente' => 0]);

        $siguienteCuenta = null;

        if ($data['recordar_pago'] ?? false) {
            $siguienteCuenta = CuentaPendiente::create([
                'tipo' => $cuentaPendiente->tipo,
                'contacto_id' => $cuentaPendiente->contacto_id,
                'serie_id' => $cuentaPendiente->serie_id,
                'concepto' => $cuentaPendiente->concepto,
                'monto_original' => $data['monto_referencial_siguiente'],
                'saldo_pendiente' => $data['monto_referencial_siguiente'],
                'fecha' => $data['fecha_siguiente'],
            ]);
        }

        return response()->json([
            'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
            'cuenta_pendiente' => $cuentaPendiente->fresh(),
            'siguiente_cuenta_pendiente' => $siguienteCuenta,
        ], 201);
    });
}

public function pagar(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'cuenta_efectivo_id' => ['required', 'exists:cuentas_efectivo,id'],
        'cuenta_pendiente_id' => ['required', 'exists:cuentas_pendientes,id'],
        'descripcion' => ['nullable', 'string'],
        'recordar_pago' => ['sometimes', 'boolean'],
        'fecha_siguiente' => ['required_if:recordar_pago,true', 'nullable', 'date', 'after_or_equal:today'],
        'monto_referencial_siguiente' => ['required_if:recordar_pago,true', 'nullable', 'numeric', 'min:0.01'],
    ]);

    $cuentaPendiente = CuentaPendiente::find($data['cuenta_pendiente_id']);

    if ($cuentaPendiente->tipo !== 'por_pagar') {
        return response()->json(['message' => 'Esa cuenta pendiente no es una cuenta por pagar.'], 422);
    }

    if ($cuentaPendiente->estaSaldada()) {
        return response()->json(['message' => 'Esta cuenta ya está saldada.'], 422);
    }

    $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
    if ($data['monto'] > $cuenta->saldoActual()) {
        return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
    }

    return DB::transaction(function () use ($data, $cuentaPendiente) {
        $movimiento = Movimiento::create([
            'tipo' => 'pago',
            'fecha' => $data['fecha'],
            'monto' => $data['monto'],
            'cuenta_efectivo_id' => $data['cuenta_efectivo_id'],
            'cuenta_pendiente_id' => $cuentaPendiente->id,
            'contacto_id' => $cuentaPendiente->contacto_id,
            'descripcion' => $data['descripcion'] ?? null,
            'user_id' => auth()->id(),
        ]);

        $cuentaPendiente->update(['saldo_pendiente' => 0]);

        $siguienteCuenta = null;

        if ($data['recordar_pago'] ?? false) {
            $siguienteCuenta = CuentaPendiente::create([
                'tipo' => $cuentaPendiente->tipo,
                'contacto_id' => $cuentaPendiente->contacto_id,
                'serie_id' => $cuentaPendiente->serie_id,
                'concepto' => $cuentaPendiente->concepto,
                'monto_original' => $data['monto_referencial_siguiente'],
                'saldo_pendiente' => $data['monto_referencial_siguiente'],
                'fecha' => $data['fecha_siguiente'],
            ]);
        }

        return response()->json([
            'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
            'cuenta_pendiente' => $cuentaPendiente->fresh(),
            'siguiente_cuenta_pendiente' => $siguienteCuenta,
        ], 201);
    });
}
private function generarSiguienteCuotaSiAplica(CuentaPendiente $cuentaPendiente): ?CuentaPendiente
{
    if (! $cuentaPendiente->estaSaldada() || $cuentaPendiente->recurrencia_id === null) {
        return null;
    }

    $recurrencia = $cuentaPendiente->recurrencia;

    if (! $recurrencia->activa) {
        return null;
    }

    return CuentaPendiente::create([
        'tipo' => $recurrencia->tipo,
        'contacto_id' => $recurrencia->contacto_id,
        'recurrencia_id' => $recurrencia->id,
        'concepto' => $recurrencia->concepto,
        'monto_original' => $recurrencia->monto_base,
        'saldo_pendiente' => $recurrencia->monto_base,
        'fecha' => $recurrencia->siguienteFechaDesde($cuentaPendiente->fecha),
    ]);
}

public function gastoOperativo(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'descripcion' => ['required', 'string'],
    ]);

    if ($data['estado'] === 'contado') {
        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }
    }

    $movimiento = Movimiento::create([
        'tipo' => 'gasto_operativo',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'descripcion' => $data['descripcion'],
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_pagar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
    ], 201);
}

public function comprarMedicina(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'producto_id' => ['required', 'exists:productos,id'],
        'cantidad' => ['required', 'numeric', 'min:0.01'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $producto = Producto::find($data['producto_id']);

    $error = $this->validarCategoriaProducto($data['producto_id'], 'Medicina');
    if ($error) {
        return response()->json(['message' => $error], 422);
    }

    if ($data['estado'] === 'contado') {
        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }
    }

    $movimiento = Movimiento::create([
        'tipo' => 'compra_medicina',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_pagar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    $stock = StockProducto::create([
        'producto_id' => $data['producto_id'],
        'unidad_id' => $producto->unidad_id,
        'cantidad' => $data['cantidad'],
        'tipo_movimiento' => 'compra',
        'movimiento_id' => $movimiento->id,
        'fecha' => $data['fecha'],
        'descripcion' => $data['descripcion'] ?? null,
    ]);

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
        'stock' => $stock->load(['producto', 'unidad']),
    ], 201);
}

public function comprarMuebles(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'producto_id' => ['required', 'exists:productos,id'],
        'cantidad' => ['required', 'numeric', 'min:0.01'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $producto = Producto::find($data['producto_id']);

    $error = $this->validarCategoriaProducto($data['producto_id'], 'Muebles y enseres');
    if ($error) {
        return response()->json(['message' => $error], 422);
    }

    if ($data['estado'] === 'contado') {
        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }
    }

    $movimiento = Movimiento::create([
        'tipo' => 'compra_muebles',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_pagar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    $stock = StockProducto::create([
        'producto_id' => $data['producto_id'],
        'unidad_id' => $producto->unidad_id,
        'cantidad' => $data['cantidad'],
        'tipo_movimiento' => 'compra',
        'movimiento_id' => $movimiento->id,
        'fecha' => $data['fecha'],
        'descripcion' => $data['descripcion'] ?? null,
    ]);

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
        'stock' => $stock->load(['producto', 'unidad']),
    ], 201);
}

    private function validarCategoriaProducto(int $productoId, string $categoriaEsperada): ?string
    {
        $producto = Producto::with('categoria')->find($productoId);

        if ($producto->categoria->nombre !== $categoriaEsperada) {
            return "El producto seleccionado no pertenece a la categoría {$categoriaEsperada}.";
        }

        return null;
    }
    public function comprarAlimento(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'producto_id' => ['required', 'exists:productos,id'],
        'cantidad_sacos' => ['required_if:requiere_ciclo_saco,true', 'nullable', 'integer', 'min:1'],
        'peso_por_saco' => ['required_if:requiere_ciclo_saco,true', 'nullable', 'numeric', 'min:0.01'],
        'cantidad' => ['nullable', 'numeric', 'min:0.01'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $producto = Producto::find($data['producto_id']);

    $error = $this->validarCategoriaProducto($data['producto_id'], 'Alimento');
    if ($error) {
        return response()->json(['message' => $error], 422);
    }

    if ($producto->requiere_ciclo_saco && (empty($data['cantidad_sacos']) || empty($data['peso_por_saco']))) {
        return response()->json(['message' => 'Este producto requiere cantidad_sacos y peso_por_saco.'], 422);
    }

    if (! $producto->requiere_ciclo_saco && empty($data['cantidad'])) {
        return response()->json(['message' => 'Este producto requiere el campo cantidad.'], 422);
    }

    if ($data['estado'] === 'contado') {
        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }
    }

    $movimiento = Movimiento::create([
        'tipo' => 'compra_alimento',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_pagar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    $sacosCreados = [];
    $stock = null;

    if ($producto->requiere_ciclo_saco) {
        for ($i = 0; $i < $data['cantidad_sacos']; $i++) {
            $sacosCreados[] = \App\Models\SacoAlimento::create([
                'producto_id' => $producto->id,
                'unidad_id' => $producto->unidad_id,
                'peso' => $data['peso_por_saco'],
                'estado' => 'en_espera',
                'fecha_compra' => $data['fecha'],
                'movimiento_id' => $movimiento->id,
            ]);
        }
    } else {
        $stock = \App\Models\StockProducto::create([
            'producto_id' => $producto->id,
            'unidad_id' => $producto->unidad_id,
            'cantidad' => $data['cantidad'],
            'tipo_movimiento' => 'compra',
            'movimiento_id' => $movimiento->id,
            'fecha' => $data['fecha'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);
    }

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
        'sacos' => $sacosCreados,
        'stock' => $stock,
    ], 201);
}

public function comprarAves(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'producto_id' => ['required', 'exists:productos,id'],
        'cantidad' => ['required', 'integer', 'min:1'],
        'edad_inicial_dias' => ['required', 'integer', 'min:0'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $producto = Producto::with('categoria')->find($data['producto_id']);

 $error = $this->validarCategoriaProducto($data['producto_id'], 'Aves');
if ($error) {
    return response()->json(['message' => $error], 422);
}

    if ($data['estado'] === 'contado') {
        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }
    }

    $movimiento = Movimiento::create([
        'tipo' => 'compra_aves',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_pagar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    $lote = Lote::create([
        'origen' => 'compra',
        'producto_id' => $producto->id,
        'cantidad_gallinas' => $data['cantidad'],
        'cantidad_gallos' => 0,
        'edad_inicial_dias' => $data['edad_inicial_dias'],
        'fecha_ingreso' => $data['fecha'],
        'costo_total' => $data['monto'],
        'movimiento_id' => $movimiento->id,
    ]);

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
        'lote' => $lote->load('producto'),
    ], 201);
}

public function venderAves(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'lote_id' => ['required', 'exists:lotes,id'],
        'cantidad_gallinas' => ['required_without:cantidad_gallos', 'nullable', 'integer', 'min:0'],
        'cantidad_gallos' => ['required_without:cantidad_gallinas', 'nullable', 'integer', 'min:0'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $cantidadGallinas = $data['cantidad_gallinas'] ?? 0;
    $cantidadGallos = $data['cantidad_gallos'] ?? 0;

    if ($cantidadGallinas === 0 && $cantidadGallos === 0) {
        return response()->json(['message' => 'Debes vender al menos un ave.'], 422);
    }

    $lote = \App\Models\Lote::find($data['lote_id']);

    if ($cantidadGallinas > $lote->cantidad_gallinas) {
        return response()->json(['message' => 'No hay suficientes gallinas disponibles en este lote.'], 422);
    }

    if ($cantidadGallos > $lote->cantidad_gallos) {
        return response()->json(['message' => 'No hay suficientes gallos disponibles en este lote.'], 422);
    }

    $movimiento = Movimiento::create([
        'tipo' => 'venta_aves',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_cobrar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    $lote->update([
        'cantidad_gallinas' => $lote->cantidad_gallinas - $cantidadGallinas,
        'cantidad_gallos' => $lote->cantidad_gallos - $cantidadGallos,
    ]);

    if ($lote->cantidadTotal() === 0) {
        $lote->update(['activo' => false]);
    }

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
        'lote' => $lote->fresh(),
    ], 201);
}
public function venderHuevos(Request $request)
{
    $data = $request->validate([
        'fecha' => ['required', 'date'],
        'monto' => ['required', 'numeric', 'min:0.01'],
        'contacto_id' => ['required', 'exists:contactos,id'],
        'estado' => ['required', 'in:contado,credito'],
        'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
        'cantidad_huevos' => ['required', 'integer', 'min:1'],
        'descripcion' => ['nullable', 'string'],
    ]);

    $totalProducido = \App\Models\ProduccionHuevo::sum('cantidad');
    $totalVendido = Movimiento::where('tipo', 'venta_huevos')->sum('cantidad_huevos');
    $stockActual = $totalProducido - $totalVendido;

    if ($data['cantidad_huevos'] > $stockActual) {
        return response()->json(['message' => 'No hay suficiente stock de huevos para esta venta.'], 422);
    }

    if ($data['estado'] === 'contado') {
        $cuenta = CuentaEfectivo::find($data['cuenta_efectivo_id']);
        if ($data['monto'] > $cuenta->saldoActual()) {
            return response()->json(['message' => 'Saldo insuficiente en la cuenta seleccionada.'], 422);
        }
    }

    $movimiento = Movimiento::create([
        'tipo' => 'venta_huevos',
        'fecha' => $data['fecha'],
        'monto' => $data['monto'],
        'contacto_id' => $data['contacto_id'],
        'estado' => $data['estado'],
        'cuenta_efectivo_id' => $data['estado'] === 'contado' ? $data['cuenta_efectivo_id'] : null,
        'cantidad_huevos' => $data['cantidad_huevos'],
        'descripcion' => $data['descripcion'] ?? null,
        'user_id' => auth()->id(),
    ]);

    $cuentaPendiente = null;

    if ($data['estado'] === 'credito') {
        $cuentaPendiente = CuentaPendiente::create([
            'tipo' => 'por_cobrar',
            'contacto_id' => $data['contacto_id'],
            'monto_original' => $data['monto'],
            'saldo_pendiente' => $data['monto'],
            'movimiento_origen_id' => $movimiento->id,
            'fecha' => $data['fecha'],
        ]);
    }

    return response()->json([
        'movimiento' => $movimiento->load(['cuentaEfectivo', 'contacto']),
        'cuenta_pendiente' => $cuentaPendiente,
    ], 201);
}
}