<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Aves', 'tipos_medida_permitidos' => ['conteo']],
            ['nombre' => 'Huevos', 'tipos_medida_permitidos' => ['conteo']],
            ['nombre' => 'Alimento', 'tipos_medida_permitidos' => ['peso']],
            ['nombre' => 'Medicina', 'tipos_medida_permitidos' => ['conteo', 'volumen', 'peso']],
            ['nombre' => 'Muebles y enseres', 'tipos_medida_permitidos' => ['conteo']],
        ];

        foreach ($categorias as $categoria) {
            Categoria::updateOrCreate(['nombre' => $categoria['nombre']], $categoria);
        }
    }
}