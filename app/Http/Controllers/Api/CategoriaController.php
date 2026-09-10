<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        return Categoria::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:categorias'],
            'tipos_medida_permitidos' => ['required', 'array', 'min:1'],
            'tipos_medida_permitidos.*' => ['in:peso,volumen,conteo'],
        ]);

        $categoria = Categoria::create($data);

        return response()->json($categoria, 201);
    }

    public function update(Request $request, Categoria $categoria)
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255', 'unique:categorias,nombre,'.$categoria->id],
            'tipos_medida_permitidos' => ['sometimes', 'array', 'min:1'],
            'tipos_medida_permitidos.*' => ['in:peso,volumen,conteo'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $categoria->update($data);

        return response()->json($categoria);
    }

    public function destroy(Categoria $categoria)
    {
        $categoria->update(['activo' => false]);

        return response()->json(['message' => 'Categoría desactivada.']);
    }
}