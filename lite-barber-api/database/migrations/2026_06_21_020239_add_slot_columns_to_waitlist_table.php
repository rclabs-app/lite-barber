<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('waitlist', function (Blueprint $table) {
            $table->date('desired_date')->nullable();
            $table->time('desired_time')->nullable();
            $table->timestamp('notified_at')->nullable();
            
            $table->dropColumn(['position', 'estimated_wait', 'called_at', 'started_at', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waitlist', function (Blueprint $table) {
            $table->dropColumn(['desired_date', 'desired_time', 'notified_at']);
            
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('estimated_wait')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }
};
