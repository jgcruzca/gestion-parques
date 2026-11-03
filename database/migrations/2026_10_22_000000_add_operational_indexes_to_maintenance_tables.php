<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateSchedule = DB::table('park_schedules')
            ->select('park_id', 'maintenance_type')
            ->groupBy('park_id', 'maintenance_type')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateSchedule) {
            throw new RuntimeException(
                'No se puede crear el índice único de programaciones: existen tipos de mantenimiento duplicados para un parque.'
            );
        }

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->index(['status', 'scheduled_date'], 'mr_status_scheduled_idx');
            $table->index(['park_id', 'status', 'scheduled_date'], 'mr_park_status_scheduled_idx');
            $table->index(['park_id', 'execution_date'], 'mr_park_execution_idx');
            $table->index(['worker_id', 'status'], 'mr_worker_status_idx');
            $table->index(['status', 'alert_date'], 'mr_status_alert_idx');
            $table->index(['park_schedule_id', 'status'], 'mr_schedule_status_idx');
            $table->index(['status', 'execution_date'], 'mr_status_execution_idx');
        });

        Schema::table('park_schedules', function (Blueprint $table) {
            $table->unique(['park_id', 'maintenance_type'], 'ps_park_type_unique');
            $table->index(['is_active', 'next_scheduled_date'], 'ps_active_next_idx');
        });
    }

    public function down(): void
    {
        Schema::table('park_schedules', function (Blueprint $table) {
            $table->dropIndex('ps_active_next_idx');
            $table->dropUnique('ps_park_type_unique');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropIndex('mr_status_scheduled_idx');
            $table->dropIndex('mr_park_status_scheduled_idx');
            $table->dropIndex('mr_park_execution_idx');
            $table->dropIndex('mr_worker_status_idx');
            $table->dropIndex('mr_status_alert_idx');
            $table->dropIndex('mr_schedule_status_idx');
            $table->dropIndex('mr_status_execution_idx');
        });
    }
};
