<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            // Ruta relativa en el disco privado, nunca una URL pública.
            $table->string('url_archivo');
            $table->string('tipo_archivo', 100);
            $table->foreignId('subido_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('solicitud_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjuntos');
    }
};
