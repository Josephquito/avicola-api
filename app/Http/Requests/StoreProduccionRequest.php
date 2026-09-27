<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreProduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string'],
            'items.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'items.*.edad_inicial_dias' => ['nullable', 'integer', 'min:0'],
            'items.*.cantidad_huevos' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);
            $productoIds = collect($items)->pluck('producto_id')->filter()->unique();
            $productos = Producto::with('categoria')->whereIn('id', $productoIds)->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $producto = $productos->get($item['producto_id'] ?? null);
                if (! $producto) continue;

                $categoria = $producto->categoria?->nombre;

                match ($categoria) {
                    'Aves' => $this->exigir($validator, $item, $index, ['cantidad', 'edad_inicial_dias']),
                    'Huevos' => $this->exigir($validator, $item, $index, ['cantidad_huevos']),
                    default => $validator->errors()->add(
                        "items.$index.producto_id",
                        "La categoría \"{$categoria}\" no es válida para producción."
                    ),
                };
            }
        });
    }

    private function exigir(Validator $validator, array $item, int $index, array $campos): void
    {
        foreach ($campos as $campo) {
            if (empty($item[$campo])) {
                $validator->errors()->add("items.$index.$campo", 'Este campo es requerido para este tipo de producto.');
            }
        }
    }
}