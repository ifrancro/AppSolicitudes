<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /solicitudes/{id}/asignaciones (contrato 6.4). Que el usuario sea un
 * responsable activo lo comprueba AsignacionService, no esta clase.
 */
class AsignarSolicitudRequest extends FormRequest
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
            'responsable_id' => ['required', 'integer', 'exists:usuarios,id'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ];
    }
}
