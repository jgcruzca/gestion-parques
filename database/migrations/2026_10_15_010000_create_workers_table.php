<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('document_number', 20)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position'); // Cargo o función
            $table->string('phone', 30)->nullable();
            $table->string('crew')->nullable(); // Cuadrilla
            $table->text('observations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('worker_id')->nullable()->after('park_id')->constrained('workers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['worker_id']);
            $table->dropColumn('worker_id');
        });

        Schema::dropIfExists('workers');
    }
};
