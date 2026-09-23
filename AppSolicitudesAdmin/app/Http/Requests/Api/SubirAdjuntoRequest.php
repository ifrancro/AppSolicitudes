<?php

namespace App\Http\Requests\Api;

use App\Enums\EstadoNombre;
use App\Models\Adjunto;
use App\Models\Solicitud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /solicitudes/{solicitud}/adjuntos (contrato §5.4).
 * authorize() corre antes que las reglas: un ajeno recibe 403 aunque envíe basura.
 */
class SubirAdjuntoRequest extends FormRequest
{
    public const MAX_KB = 5120;

    public const MAX_POR_SOLICITUD = 5;

    public const ESTADOS_ADMITIDOS = [
        EstadoNombre::Pendiente,
        EstadoNombre::Asignada,
        EstadoNombre::EnProceso,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('uploadAttachment', $this->solicitud());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // El tipo se valida por el contenido del archivo, no por su extensión.
        return [
            'archivo' => [
                'required', 'file',
                'mimetypes:'.implode(',', array_keys(Adjunto::EXTENSIONES)),
                'mimes:jpg,jpeg,png,pdf',
                'max:'.self::MAX_KB,
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $solicitud = $this->solicitud();
            $estado = $solicitud->estado->nombre;

            if (! in_array(EstadoNombre::from($estado), self::ESTADOS_ADMITIDOS, true)) {
                $validator->errors()->add('archivo', 'No se pueden adjuntar archivos a una solicitud '.$estado.'.');
            } elseif ($solicitud->adjuntos()->count() >= self::MAX_POR_SOLICITUD) {
                $validator->errors()->add('archivo', 'La solicitud ya tiene el máximo de '.self::MAX_POR_SOLICITUD.' adjuntos.');
            }
        }];
    }

    private function solicitud(): Solicitud
    {
        return $this->route('solicitud');
    }
}
