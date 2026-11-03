<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('green_area_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('institutional_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->string('unit_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('management_year', 20)->nullable();
            $table->text('document_footer')->nullable();
            $table->timestamps();
        });

        Schema::table('maintenance_rules', function (Blueprint $table) {
            $table->unsignedInteger('advance_days')->default(1)->after('frequency_days');
            $table->unique(['green_area_type', 'maintenance_type'], 'maintenance_rules_area_maintenance_unique');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_rules', function (Blueprint $table) {
            $table->dropUnique('maintenance_rules_area_maintenance_unique');
            $table->dropColumn('advance_days');
        });

        Schema::dropIfExists('institutional_settings');
        Schema::dropIfExists('maintenance_types');
        Schema::dropIfExists('green_area_types');
    }
};
