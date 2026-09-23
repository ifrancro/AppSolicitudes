<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->cascadeOnDelete();
            $table->string('mensaje');
            $table->boolean('leido')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['usuario_id', 'leido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
