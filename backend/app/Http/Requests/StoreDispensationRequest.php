<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [
                'auxiliar_farmacia',
                'regente_farmacia',
                'admin',
            ], true);
    }

    public function rules(): array
    {
        return [
            'prescription_id' => [
                'required',
                'integer',
                'exists:prescriptions,id',
            ],

            'warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
            ],

            'idempotency_key' => [
                'required',
                'string',
                'max:100',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.prescription_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:prescription_items,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.controlled_authorization_id' => ['nullable', 'integer', 'exists:controlled_medication_authorizations,id'],

            'observations' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'prescription_id.required' => 'Debes indicar la fórmula médica.',

            'prescription_id.exists' => 'La fórmula médica indicada no existe.',

            'warehouse_id.required' => 'Debes seleccionar una bodega.',

            'idempotency_key.required' => 'Debes enviar una clave única para la operación.',

            'items.required' => 'Debes incluir al menos un medicamento.',

            'items.min' => 'Debes incluir al menos un medicamento.',

            'items.*.prescription_item_id.exists' => 'Uno de los medicamentos no pertenece a una fórmula existente.',

            'items.*.quantity.min' => 'La cantidad por dispensar debe ser mayor que cero.',
        ];
    }
}
