<?php

namespace App\Http\Requests;

use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreVentaRequest extends FormRequest
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
            'items.*.lote_id' => ['nullable', 'exists:lotes,id'],
            'items.*.cantidad_gallinas' => ['nullable', 'integer', 'min:0'],
            'items.*.cantidad_gallos' => ['nullable', 'integer', 'min:0'],
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
                    'Aves' => $this->validarLineaAve($validator, $item, $index),
                    'Huevos' => empty($item['cantidad_huevos'])
                        ? $validator->errors()->add("items.$index.cantidad_huevos", 'Este campo es requerido para vender huevos.')
                        : null,
                    default => $validator->errors()->add(
                        "items.$index.producto_id",
                        "La categoría \"{$categoria}\" no es válida para una venta."
                    ),
                };
            }
        });
    }

    private function validarLineaAve(Validator $validator, array $item, int $index): void
    {
        if (empty($item['lote_id'])) {
            $validator->errors()->add("items.$index.lote_id", 'Selecciona de qué lote provienen estas aves.');
            return;
        }

        $cantidadGallinas = $item['cantidad_gallinas'] ?? 0;
        $cantidadGallos = $item['cantidad_gallos'] ?? 0;

        if ($cantidadGallinas === 0 && $cantidadGallos === 0) {
            $validator->errors()->add("items.$index.cantidad_gallinas", 'Indica al menos un ave a vender.');
            return;
        }

        $lote = Lote::find($item['lote_id']);
        if (! $lote) return; // ya lo marcó 'exists'

        if ($cantidadGallinas > $lote->cantidad_gallinas) {
            $validator->errors()->add("items.$index.cantidad_gallinas", 'No hay suficientes gallinas disponibles en este lote.');
        }
        if ($cantidadGallos > $lote->cantidad_gallos) {
            $validator->errors()->add("items.$index.cantidad_gallos", 'No hay suficientes gallos disponibles en este lote.');
        }
    }
}