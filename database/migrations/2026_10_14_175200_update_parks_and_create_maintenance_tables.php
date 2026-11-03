<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Actualizar tabla parks
        Schema::table('parks', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
            $table->string('green_area_type')->default('Parque')->after('name');
            $table->date('registration_date')->nullable()->after('green_area_type');
            $table->string('zone')->nullable()->after('address');
            $table->string('reference')->nullable()->after('zone');

            // Superficie
            $table->decimal('public_use_area', 10, 2)->nullable()->after('longitude');
            $table->decimal('green_area', 10, 2)->nullable()->after('public_use_area');
            $table->decimal('total_area', 10, 2)->nullable()->after('green_area');

            // Punto de agua
            $table->boolean('has_water_point')->default(false)->after('total_area');
            $table->string('water_supply_type')->nullable()->after('has_water_point');
            $table->string('water_point_status')->nullable()->after('water_supply_type');
            $table->text('water_point_observations')->nullable()->after('water_point_status');

            // Información adicional
            $table->string('conservation_status')->default('regular')->after('water_point_observations');
            $table->string('photo_url')->nullable()->after('conservation_status');
            $table->text('general_observations')->nullable()->after('photo_url');
            $table->boolean('is_active')->default(true)->after('general_observations');
        });

        // Tabla de reglas de mantenimiento por tipo de área verde
        Schema::create('maintenance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('green_area_type'); // Óvalo, Parque, Alameda, Berma Central, Jardín, Plaza
            $table->string('maintenance_type'); // Riego, Limpieza, Poda, etc.
            $table->unsignedInteger('frequency_days'); // Cada X días
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabla de programaciones activas por parque
        Schema::create('park_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->constrained('parks')->cascadeOnDelete();
            $table->string('maintenance_type');
            $table->unsignedInteger('frequency_days');
            $table->date('last_executed_at')->nullable();
            $table->date('next_scheduled_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabla de historial de mantenimientos
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->constrained('parks')->cascadeOnDelete();
            $table->foreignId('park_schedule_id')->nullable()->constrained('park_schedules')->nullOnDelete();
            $table->string('maintenance_type');
            $table->boolean('is_extraordinary')->default(false);
            $table->date('scheduled_date')->nullable();
            $table->date('execution_date')->nullable();
            $table->string('responsible_worker')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status')->default('pendiente'); // completado, ejecutado, pendiente, en_proceso, cancelado
            $table->text('observations')->nullable();
            $table->string('document_name')->nullable();
            $table->string('document_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('park_schedules');
        Schema::dropIfExists('maintenance_rules');

        Schema::table('parks', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'green_area_type',
                'registration_date',
                'zone',
                'reference',
                'public_use_area',
                'green_area',
                'total_area',
                'has_water_point',
                'water_supply_type',
                'water_point_status',
                'water_point_observations',
                'conservation_status',
                'photo_url',
                'general_observations',
                'is_active',
            ]);
        });
    }
};
