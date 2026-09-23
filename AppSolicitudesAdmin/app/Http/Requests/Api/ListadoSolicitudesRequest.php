<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parámetros comunes de todos los listados de solicitudes (contrato §1.3 y §1.4).
 * Cada endpoint decide cuáles filtros aplica; los demás se ignoran.
 */
class ListadoSolicitudesRequest extends FormRequest
{
    public const PER_PAGE_POR_DEFECTO = 15;

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
            'orden' => ['sometimes', 'string', 'in:created_at,-created_at,updated_at,-updated_at'],
            'estado_id' => ['sometimes', 'integer', 'exists:estados_solicitud,id'],
            'tipo_id' => ['sometimes', 'integer', 'exists:tipos_solicitud,id'],
            'prioridad_id' => ['sometimes', 'integer', 'exists:prioridades,id'],
            'responsable_id' => ['sometimes', 'integer', 'exists:usuarios,id'],
            'estudiante_id' => ['sometimes', 'integer', 'exists:usuarios,id'],
            'sin_asignar' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'max:100'],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', self::PER_PAGE_POR_DEFECTO);
    }

    /**
     * Filtros validados, listos para Solicitud::filtrar().
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return $this->safe()->except(['page', 'per_page']);
    }
}
