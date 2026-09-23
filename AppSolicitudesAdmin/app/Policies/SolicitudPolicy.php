<?php

namespace App\Policies;

use App\Enums\EstadoNombre;
use App\Enums\RolNombre;
use App\Models\Solicitud;
use App\Models\User;

/**
 * Reglas de acceso a solicitudes por rol. Los controladores solo llaman a
 * authorize()/can(); ninguna regla de rol vive fuera de las políticas.
 */
class SolicitudPolicy
{
    /** POST /solicitudes: solo el estudiante registra solicitudes. */
    public function create(User $user): bool
    {
        return $user->tieneRol(RolNombre::Estudiante);
    }

    /** GET /solicitudes (listado global) y reportes por solicitud. */
    public function viewAny(User $user): bool
    {
        return $user->tieneRol(RolNombre::PersonalAdministrativo, RolNombre::Administrador);
    }

    /** GET /solicitudes-asignadas. */
    public function viewAssigned(User $user): bool
    {
        return $user->tieneRol(RolNombre::Responsable);
    }

    public function view(User $user, Solicitud $solicitud): bool
    {
        if ($user->tieneRol(RolNombre::Estudiante)) {
            return $solicitud->estudiante_id === $user->id;
        }

        if ($user->tieneRol(RolNombre::Responsable)) {
            return $this->estaAsignadaA($user, $solicitud);
        }

        return $this->viewAny($user);
    }

    /** PATCH /solicitudes/{id}/clasificacion */
    public function classify(User $user, Solicitud $solicitud): bool
    {
        return $this->viewAny($user);
    }

    /** POST /solicitudes/{id}/asignaciones */
    public function assign(User $user, Solicitud $solicitud): bool
    {
        return $this->viewAny($user);
    }

    /** GET /solicitudes/{id}/asignaciones */
    public function viewAssignments(User $user, Solicitud $solicitud): bool
    {
        return $this->viewAny($user)
            || ($user->tieneRol(RolNombre::Responsable) && $this->estaAsignadaA($user, $solicitud));
    }

    /** PATCH /solicitudes/{id}/estado */
    public function changeState(User $user, Solicitud $solicitud): bool
    {
        return $this->viewAssignments($user, $solicitud);
    }

    /**
     * PATCH /solicitudes/{id}/estado hacia un destino concreto (contrato §6.7).
     * Personal y administrador piden en_proceso, cerrada o cancelada; el
     * responsable asignado solo en_proceso y cerrada (cancelar no le toca).
     */
    public function changeStateTo(User $user, Solicitud $solicitud, EstadoNombre $destino): bool
    {
        if (! $this->changeState($user, $solicitud)) {
            return false;
        }

        $permitidos = $this->viewAny($user)
            ? [EstadoNombre::EnProceso, EstadoNombre::Cerrada, EstadoNombre::Cancelada]
            : [EstadoNombre::EnProceso, EstadoNombre::Cerrada];

        return in_array($destino, $permitidos, true);
    }

    /** POST /solicitudes/{id}/acciones: solo quien la tiene asignada. */
    public function createAction(User $user, Solicitud $solicitud): bool
    {
        return $user->tieneRol(RolNombre::Responsable) && $this->estaAsignadaA($user, $solicitud);
    }

    /** GET /solicitudes/{id}/acciones */
    public function viewActions(User $user, Solicitud $solicitud): bool
    {
        return $this->viewAssignments($user, $solicitud);
    }

    /** POST /solicitudes/{id}/adjuntos: el estudiante dueño. */
    public function uploadAttachment(User $user, Solicitud $solicitud): bool
    {
        return $user->tieneRol(RolNombre::Estudiante) && $solicitud->estudiante_id === $user->id;
    }

    private function estaAsignadaA(User $user, Solicitud $solicitud): bool
    {
        return $solicitud->asignaciones()
            ->where('activo', true)
            ->where('responsable_id', $user->id)
            ->exists();
    }
}
