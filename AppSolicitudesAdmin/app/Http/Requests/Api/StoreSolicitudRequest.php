<?php

namespace App\Http\Requests\Api;

use App\Models\Solicitud;
use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudRequest extends FormRequest
{
    /** La política corre antes de validar: un rol ajeno recibe 403 aunque el cuerpo sea inválido. */
    public function authorize(): bool
    {
        return $this->user()->can('create', Solicitud::class);
    }

    /**
     * Prioridad y estado no se validan: el estudiante no los elige y, si llegan, se ignoran.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tipo_id' => ['required', 'integer', 'exists:tipos_solicitud,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
