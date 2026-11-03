<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_audits', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('reason');
            $table->string('document_name')->nullable()->after('document_path');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_audits', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name']);
        });
    }
};
