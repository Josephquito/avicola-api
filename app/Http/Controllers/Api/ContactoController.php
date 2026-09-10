<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use Illuminate\Http\Request;

class ContactoController extends Controller
{
    public function index()
    {
        return Contacto::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'cedula_ruc' => ['nullable', 'string', 'max:20'],
        ]);

        $existente = null;

        if (! empty($data['cedula_ruc'])) {
            $existente = Contacto::where('cedula_ruc', $data['cedula_ruc'])->first();
        }

        if (! $existente && ! empty($data['telefono'])) {
            $existente = Contacto::where('telefono', $data['telefono'])->first();
        }

        if ($existente) {
            if ($existente->activo) {
                return response()->json([
                    'message' => 'Ya existe un contacto activo con esa cédula/RUC o teléfono.',
                ], 422);
            }

            $existente->update(array_merge($data, ['activo' => true]));

            return response()->json($existente);
        }

        $contacto = Contacto::create($data);

        return response()->json($contacto, 201);
    }

    public function update(Request $request, Contacto $contacto)
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'cedula_ruc' => ['nullable', 'string', 'max:20'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $contacto->update($data);

        return response()->json($contacto);
    }

    public function destroy(Contacto $contacto)
    {
        $contacto->update(['activo' => false]);

        return response()->json(['message' => 'Contacto desactivado.']);
    }
}