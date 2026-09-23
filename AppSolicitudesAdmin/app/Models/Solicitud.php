<?php

namespace App\Models;

use App\Enums\EstadoNombre;
use App\Observers\SolicitudObserver;
use Database\Factories\SolicitudFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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
     * Filtros y orden comunes de los listados (contrato §1.4 y filtros de §5.2 y §6.1).
     * Solo aplica las claves presentes en $filtros; el orden por defecto es -created_at.
     *
     * @param  Builder<Solicitud>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<Solicitud>
     */
    public function scopeFiltrar(Builder $query, array $filtros): Builder
    {
        foreach (['estado_id', 'tipo_id', 'prioridad_id', 'estudiante_id'] as $columna) {
            if (isset($filtros[$columna])) {
                $query->where($columna, $filtros[$columna]);
            }
        }

        if (isset($filtros['responsable_id'])) {
            $query->whereHas('asignaciones', fn (Builder $a) => $a
                ->where('activo', true)
                ->where('responsable_id', $filtros['responsable_id']));
        }

        if (! empty($filtros['sin_asignar'])) {
            $query->whereDoesntHave('asignaciones', fn (Builder $a) => $a->where('activo', true));
        }

        if (isset($filtros['q']) && $filtros['q'] !== '') {
            $query->where('titulo', 'ILIKE', '%'.addcslashes($filtros['q'], '%_\\').'%');
        }

        if (isset($filtros['desde'])) {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }

        if (isset($filtros['hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }

        $orden = $filtros['orden'] ?? '-created_at';
        $columna = ltrim($orden, '-');
        $direccion = str_starts_with($orden, '-') ? 'desc' : 'asc';

        // El id desempata: sin él la paginación puede repetir o saltar filas.
        return $query->orderBy($columna, $direccion)->orderBy('id', $direccion);
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
