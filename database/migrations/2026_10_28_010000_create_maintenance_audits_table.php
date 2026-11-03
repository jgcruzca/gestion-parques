<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_record_id')->constrained('maintenance_records')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80)->index();
            $table->text('reason');
            $table->string('previous_status', 50)->nullable();
            $table->date('scheduled_date')->nullable();
            $table->unsignedInteger('overdue_days')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['maintenance_record_id', 'created_at'], 'maintenance_audit_record_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_audits');
    }
};
