<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('maintenance_number')->nullable()->unique()->after('id');
            $table->date('alert_date')->nullable()->after('scheduled_date');
            $table->unsignedInteger('advance_days')->default(1)->after('alert_date');
            $table->text('reason')->nullable()->after('is_extraordinary');
            $table->text('initial_observation')->nullable()->after('status');
            $table->text('work_description')->nullable()->after('initial_observation');
            $table->string('result')->nullable()->after('work_description');
            $table->string('photo_before_url')->nullable()->after('result');
            $table->string('photo_after_url')->nullable()->after('photo_before_url');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn([
                'maintenance_number',
                'alert_date',
                'advance_days',
                'reason',
                'initial_observation',
                'work_description',
                'result',
                'photo_before_url',
                'photo_after_url',
            ]);
        });
    }
};
