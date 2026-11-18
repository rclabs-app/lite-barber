<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE waitlist MODIFY COLUMN status ENUM('waiting','notified','booked','expired','cancelled') DEFAULT 'waiting'");

            return;
        }

        Schema::table('waitlist', function (Blueprint $table) {
            $table->string('status', 30)->default('waiting')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE waitlist MODIFY COLUMN status ENUM('waiting','called','in_service','completed','cancelled','no_show') DEFAULT 'waiting'");

            return;
        }

        Schema::table('waitlist', function (Blueprint $table) {
            $table->string('status', 30)->default('waiting')->change();
        });
    }
};
