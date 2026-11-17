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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->foreignId('owner_id')->nullable(); // Foreign key to users, added nullable to break circular dependency initially
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->text('description')->nullable();
            
            $table->time('opening_time')->default('08:00:00');
            $table->time('closing_time')->default('19:00:00');
            $table->unsignedTinyInteger('slot_duration')->default(30);
            $table->json('working_days')->default(json_encode([1,2,3,4,5])); // seg-sex
            
            $table->enum('plan', ['free','starter','pro','premium'])->default('free');
            $table->timestamp('plan_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('slug');
            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
