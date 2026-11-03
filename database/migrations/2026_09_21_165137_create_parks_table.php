<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parks', function (Blueprint $table) {

            $table->id();

            // Información general del parque
            $table->string('name');
            $table->string('address')->nullable();
            $table->text('description')->nullable();

            // Ubicación geográfica
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Estado de conservación
            $table->enum('status', [
                'bueno',
                'regular',
                'malo'
            ])->default('regular');

            // Superficie en metros cuadrados
            $table->decimal('area', 10, 2)->nullable();

            // Observaciones adicionales
            $table->text('observations')->nullable();

            // Fecha de creación y actualización
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parks');
    }
};