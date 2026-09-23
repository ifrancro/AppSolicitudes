<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('estado_id')->constrained('estados_solicitud')->restrictOnDelete();
            $table->foreignId('cambiado_por')->constrained('usuarios')->restrictOnDelete();
            $table->text('comentario')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['solicitud_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados');
    }
};
