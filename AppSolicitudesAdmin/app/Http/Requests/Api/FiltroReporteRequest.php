<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros de fecha de los reportes agregados (contrato §6.12 y §6.13).
 */
class FiltroReporteRequest extends FormRequest
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
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    /**
     * @return array{desde?: string, hasta?: string}
     */
    public function filtros(): array
    {
        return $this->validated();
    }
}
