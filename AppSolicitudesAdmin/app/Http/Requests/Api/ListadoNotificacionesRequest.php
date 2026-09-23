<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /notificaciones: paginación (contrato §1.3) y filtro `leido` (§5.7).
 */
class ListadoNotificacionesRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'leido' => ['sometimes', 'boolean'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', ListadoSolicitudesRequest::PER_PAGE_POR_DEFECTO);
    }
}
