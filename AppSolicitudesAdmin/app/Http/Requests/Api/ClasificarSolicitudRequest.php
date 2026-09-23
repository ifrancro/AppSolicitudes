<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /solicitudes/{id}/clasificacion (contrato 6.2). La autorizacion la
 * hace el middleware `can:classify` de la ruta, antes de validar.
 */
class ClasificarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tipo_id' => ['required_without:prioridad_id', 'nullable', 'integer', 'exists:tipos_solicitud,id'],
            'prioridad_id' => ['required_without:tipo_id', 'nullable', 'integer', 'exists:prioridades,id'],
        ];
    }
}
