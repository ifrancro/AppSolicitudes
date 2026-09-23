<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /solicitudes/{id}/estado (contrato §6.7). El comentario obligatorio al
 * cancelar es regla de negocio y lo comprueba CambioEstadoService.
 */
class CambiarEstadoRequest extends FormRequest
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
            'estado_id' => ['required', 'integer', 'exists:estados_solicitud,id'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ];
    }
}
