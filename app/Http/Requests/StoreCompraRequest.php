<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'contacto_id' => ['required', 'exists:contactos,id'],
            'estado' => ['required', 'in:contado,credito'],
            'cuenta_efectivo_id' => ['required_if:estado,contado', 'nullable', 'exists:cuentas_efectivo,id'],
            'fecha_maxima_pago' => ['required_if:estado,credito', 'nullable', 'date', 'after_or_equal:today'],
            'descripcion' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0.01'],
            'items.*.descripcion' => ['nullable', 'string'],
            'items.*.cantidad' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.cantidad_sacos' => ['nullable', 'integer', 'min:1'],
            'items.*.peso_por_saco' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.edad_inicial_dias' => ['nullable', 'integer', 'min:0'],
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
                if (! $producto) continue; // ya lo marcó 'exists'

                $categoria = $producto->categoria?->nombre;

                match ($categoria) {
                    'Muebles y enseres', 'Medicina' => $this->exigir($validator, $item, $index, ['cantidad']),
                    'Alimento' => $producto->requiere_ciclo_saco
                        ? $this->exigir($validator, $item, $index, ['cantidad_sacos', 'peso_por_saco'])
                        : $this->exigir($validator, $item, $index, ['cantidad']),
                    'Aves' => $this->exigir($validator, $item, $index, ['cantidad', 'edad_inicial_dias']),
                    default => $validator->errors()->add(
                        "items.$index.producto_id",
                        "La categoría \"{$categoria}\" no es válida para una compra."
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