<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('tipo_id')->constrained('tipos_solicitud')->restrictOnDelete();
            $table->foreignId('prioridad_id')->constrained('prioridades')->restrictOnDelete();
            $table->foreignId('estado_id')->constrained('estados_solicitud')->restrictOnDelete();
            $table->string('titulo');
            $table->text('descripcion');
            $table->string('ubicacion')->nullable();
            $table->timestamps();

            // Listados por dueño, por estado y por tipo (HU-01, HU-05, HU-10).
            $table->index('estudiante_id');
            $table->index('estado_id');
            $table->index('tipo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
