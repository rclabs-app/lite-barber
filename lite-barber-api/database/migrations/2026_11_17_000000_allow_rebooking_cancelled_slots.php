<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uk_professional_slot');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['professional_id', 'scheduled_at', 'status'], 'idx_professional_schedule_status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_professional_schedule_status');
            $table->unique(['professional_id', 'scheduled_at'], 'uk_professional_slot');
        });
    }
};
