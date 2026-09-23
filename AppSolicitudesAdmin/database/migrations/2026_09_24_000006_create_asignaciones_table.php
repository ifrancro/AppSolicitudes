<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('asignado_por')->constrained('usuarios')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamp('fecha_fin')->nullable();

            $table->index(['solicitud_id', 'activo']);
            $table->index(['responsable_id', 'activo']);
        });

        // Como mucho una asignación activa por solicitud: las anteriores se
        // conservan con activo = false como historial de reasignaciones.
        DB::statement('CREATE UNIQUE INDEX asignaciones_una_activa_por_solicitud ON asignaciones (solicitud_id) WHERE activo');
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
