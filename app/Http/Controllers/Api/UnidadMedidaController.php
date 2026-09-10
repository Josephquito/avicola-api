<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;

class UnidadMedidaController extends Controller
{
    public function index()
    {
        return UnidadMedida::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:unidades_medida'],
            'abreviatura' => ['required', 'string', 'max:10'],
            'tipo_medida' => ['required', 'in:peso,volumen,conteo'],
        ]);

        $unidad = UnidadMedida::create($data);

        return response()->json($unidad, 201);
    }

    public function update(Request $request, UnidadMedida $unidades_medida)
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255', 'unique:unidades_medida,nombre,'.$unidades_medida->id],
            'abreviatura' => ['sometimes', 'string', 'max:10'],
            'tipo_medida' => ['sometimes', 'in:peso,volumen,conteo'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $unidades_medida->update($data);

        return response()->json($unidades_medida);
    }

    public function destroy(UnidadMedida $unidades_medida)
    {
        $unidades_medida->update(['activo' => false]);

        return response()->json(['message' => 'Unidad de medida desactivada.']);
    }
}