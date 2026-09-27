<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaEfectivo;
use App\Models\CuentaPendiente;
use App\Models\Movimiento;
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

    if ($request->filled('fecha_desde')) {
        $query->whereDate('fecha', '>=', $request->input('fecha_desde'));
    }

    if ($request->filled('fecha_hasta')) {
        $query->whereDate('fecha', '<=', $request->input('fecha_hasta'));
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

        $cuentaPendiente->update(['saldo_pendiente' => 0]);

        $siguienteCuenta = null;

        if ($data['recordar_pago'] ?? false) {
            $siguienteCuenta = CuentaPendiente::create([
                'tipo' => $cuentaPendiente->tipo,
                'contacto_id' => $cuentaPendiente->contacto_id,
                'serie_id' => $cuentaPendiente->serie_id,
                'concepto' => $cuentaPendiente->concepto,
                'monto_original' => $data['monto'],
                'saldo_pendiente' => $data['monto'],
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
                'monto_original' => $data['monto'],
                'saldo_pendiente' => $data['monto'],
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
}