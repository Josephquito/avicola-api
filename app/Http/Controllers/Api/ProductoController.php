<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Unidades específicas permitidas por categoría, más allá del tipo
     * de medida genérico (ej. "Aves" es tipo 'conteo', pero solo tiene
     * sentido venderlas por 'Unidad', nunca por 'Docena' o 'Caja').
     * Las categorías que no aparecen aquí no tienen restricción extra
     * (aceptan cualquier unidad compatible con su tipo de medida).
     */
    private const UNIDADES_ESPECIFICAS = [
        'Aves' => ['Unidad'],
        'Huevos' => ['Unidad'],
        'Muebles y enseres' => ['Unidad', 'Caja', 'Funda'],
    ];

    public function index()
    {
        return Producto::with(['categoria', 'unidad'])->orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'categoria_id' => ['required', 'exists:categorias,id'],
            'unidad_id' => ['required', 'exists:unidades_medida,id'],
            'requiere_ciclo_saco' => ['sometimes', 'boolean'],
        ]);

        $error = $this->validarCompatibilidad($data['categoria_id'], $data['unidad_id']);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $producto = Producto::create($data);

        return response()->json($producto->load(['categoria', 'unidad']), 201);
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'categoria_id' => ['sometimes', 'exists:categorias,id'],
            'unidad_id' => ['sometimes', 'exists:unidades_medida,id'],
            'requiere_ciclo_saco' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $categoriaId = $data['categoria_id'] ?? $producto->categoria_id;
        $unidadId = $data['unidad_id'] ?? $producto->unidad_id;

        $error = $this->validarCompatibilidad($categoriaId, $unidadId);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $producto->update($data);

        return response()->json($producto->load(['categoria', 'unidad']));
    }

    public function destroy(Producto $producto)
    {
        $producto->update(['activo' => false]);

        return response()->json(['message' => 'Producto desactivado.']);
    }

    private function validarCompatibilidad(int $categoriaId, int $unidadId): ?string
    {
        $categoria = Categoria::find($categoriaId);
        $unidad = UnidadMedida::find($unidadId);

        if (! in_array($unidad->tipo_medida, $categoria->tipos_medida_permitidos)) {
            return "La unidad '{$unidad->nombre}' ({$unidad->tipo_medida}) no es compatible con la categoría '{$categoria->nombre}'.";
        }

        $unidadesEspecificas = self::UNIDADES_ESPECIFICAS[$categoria->nombre] ?? null;
        if ($unidadesEspecificas !== null && ! in_array($unidad->nombre, $unidadesEspecificas)) {
            return "La categoría '{$categoria->nombre}' solo admite: ".implode(', ', $unidadesEspecificas).'.';
        }

        return null;
    }
}