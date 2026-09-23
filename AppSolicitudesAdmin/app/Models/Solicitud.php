<?php

namespace App\Models;

use App\Enums\EstadoNombre;
use App\Observers\SolicitudObserver;
use Database\Factories\SolicitudFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[ObservedBy(SolicitudObserver::class)]
class Solicitud extends Model
{
    /** @use HasFactory<SolicitudFactory> */
    use HasFactory;

    protected $table = 'solicitudes';

    protected $fillable = [
        'estudiante_id', 'tipo_id', 'prioridad_id', 'estado_id',
        'titulo', 'descripcion', 'ubicacion',
    ];

    /**
     * Quién y por qué cambia el estado; lo lee el observer para escribir la
     * bitácora. Se rellena solo desde cambiarEstado().
     *
     * @var array{actor: User, comentario: ?string}|null
     */
    public ?array $contextoCambio = null;

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'tipo_id');
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'prioridad_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoSolicitud::class, 'estado_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(Adjunto::class, 'solicitud_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'solicitud_id');
    }

    public function asignacionActiva(): HasOne
    {
        return $this->hasOne(Asignacion::class, 'solicitud_id')->where('activo', true);
    }

    public function historialEstados(): HasMany
    {
        return $this->hasMany(HistorialEstado::class, 'solicitud_id');
    }

    public function acciones(): HasMany
    {
        return $this->hasMany(Accion::class, 'solicitud_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'solicitud_id');
    }

    /**
     * Único camino previsto para cambiar de estado: guarda el cambio y su
     * entrada en `historial_estados` en la misma transacción. El observer
     * cubre además cualquier `update` directo sobre `estado_id`.
     */
    public function cambiarEstado(EstadoNombre $estado, User $actor, ?string $comentario = null): void
    {
        DB::transaction(function () use ($estado, $actor, $comentario) {
            $this->contextoCambio = ['actor' => $actor, 'comentario' => $comentario];
            $this->estado_id = EstadoSolicitud::idDe($estado);
            $this->save();
            $this->contextoCambio = null;
        });
    }
}
