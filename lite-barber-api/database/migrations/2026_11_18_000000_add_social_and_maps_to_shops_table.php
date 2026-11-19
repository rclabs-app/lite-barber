<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('whatsapp_phone', 30)->nullable()->after('phone');
            $table->string('instagram_url', 255)->nullable()->after('whatsapp_phone');
            $table->string('maps_url', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_phone', 'instagram_url', 'maps_url']);
        });
    }
};
