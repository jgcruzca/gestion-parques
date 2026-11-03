<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada cuenta tiene su propio espacio de datos: se añade `owner_id` a todas las
 * tablas de negocio y las restricciones únicas pasan a ser por propietario.
 */
return new class extends Migration
{
    private array $tables = [
        'parks',
        'workers',
        'maintenance_rules',
        'park_schedules',
        'maintenance_records',
        'maintenance_audits',
        'green_area_types',
        'maintenance_types',
        'institutional_settings',
        'dashboard_monthly_targets',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('owner_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
            });
        }

        Schema::table('parks', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['owner_id', 'code'], 'parks_owner_code_unique');
            $table->boolean('is_demo')->default(false);
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['owner_id', 'code'], 'workers_owner_code_unique');
            $table->boolean('is_demo')->default(false);
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropUnique(['maintenance_number']);
            $table->unique(['owner_id', 'maintenance_number'], 'mr_owner_number_unique');
        });

        Schema::table('green_area_types', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['owner_id', 'name'], 'green_area_types_owner_name_unique');
        });

        Schema::table('maintenance_types', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['owner_id', 'name'], 'maintenance_types_owner_name_unique');
        });

        Schema::table('maintenance_rules', function (Blueprint $table) {
            $table->dropUnique('maintenance_rules_area_maintenance_unique');
            $table->unique(['owner_id', 'green_area_type', 'maintenance_type'], 'maintenance_rules_owner_area_maintenance_unique');
        });

        Schema::table('dashboard_monthly_targets', function (Blueprint $table) {
            $table->dropUnique(['month']);
            $table->unique(['owner_id', 'month'], 'dashboard_targets_owner_month_unique');
        });
    }

    public function down(): void
    {
        // Migración sin reversa: el aislamiento por cuenta es parte del modelo de datos.
    }
};
