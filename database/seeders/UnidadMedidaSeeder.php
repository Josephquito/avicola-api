<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['nombre' => 'Unidad', 'abreviatura' => 'u', 'tipo_medida' => 'conteo'],
            ['nombre' => 'Docena', 'abreviatura' => 'doc', 'tipo_medida' => 'conteo'],
            ['nombre' => 'Saco', 'abreviatura' => 'saco', 'tipo_medida' => 'conteo'],
            ['nombre' => 'Cartón', 'abreviatura' => 'cartón', 'tipo_medida' => 'conteo'],
            ['nombre' => 'Funda', 'abreviatura' => 'funda', 'tipo_medida' => 'conteo'],
            ['nombre' => 'Caja', 'abreviatura' => 'caja', 'tipo_medida' => 'conteo'],
            ['nombre' => 'Quintal', 'abreviatura' => 'qq', 'tipo_medida' => 'peso'],
            ['nombre' => 'Libra', 'abreviatura' => 'lb', 'tipo_medida' => 'peso'],
            ['nombre' => 'Kilogramo', 'abreviatura' => 'kg', 'tipo_medida' => 'peso'],
            ['nombre' => 'Gramo', 'abreviatura' => 'g', 'tipo_medida' => 'peso'],
            ['nombre' => 'Tonelada', 'abreviatura' => 'ton', 'tipo_medida' => 'peso'],
            ['nombre' => 'Litro', 'abreviatura' => 'l', 'tipo_medida' => 'volumen'],
            ['nombre' => 'Mililitro', 'abreviatura' => 'ml', 'tipo_medida' => 'volumen'],
            ['nombre' => 'Galón', 'abreviatura' => 'gal', 'tipo_medida' => 'volumen'],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::updateOrCreate(['nombre' => $unidad['nombre']], $unidad);
        }
    }
}