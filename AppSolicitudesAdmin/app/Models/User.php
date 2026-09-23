<?php

namespace App\Models;

use App\Enums\RolNombre;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Único modelo autenticable. La tabla se llama `usuarios` y la contraseña
 * vive en `password_hash`; el resto de Laravel sigue viendo un usuario normal.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'usuarios';

    protected $fillable = ['nombre', 'email', 'password_hash', 'rol_id', 'activo'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** La tabla no tiene remember_token: la sesión web no usa "recordarme". */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'estudiante_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'responsable_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'usuario_id');
    }

    public function tieneRol(RolNombre ...$roles): bool
    {
        $actual = $this->rol?->nombre;

        foreach ($roles as $rol) {
            if ($rol->value === $actual) {
                return true;
            }
        }

        return false;
    }

    public function esEstudiante(): bool
    {
        return $this->tieneRol(RolNombre::Estudiante);
    }

    public function esAdministrador(): bool
    {
        return $this->tieneRol(RolNombre::Administrador);
    }

    public function esResponsable(): bool
    {
        return $this->tieneRol(RolNombre::Responsable);
    }

    /** Personal que atiende solicitudes desde el panel web. */
    public function esDeGestion(): bool
    {
        return $this->tieneRol(...RolNombre::gestion());
    }
}
