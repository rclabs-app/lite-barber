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
        Schema::create('waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('professional_id')->nullable()->constrained()->onDelete('set null');

            $table->string('client_name', 150);
            $table->string('client_phone', 20);
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');

            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('estimated_wait')->nullable();
            $table->enum('status', ['waiting', 'called', 'in_service', 'completed', 'cancelled', 'no_show'])->default('waiting');

            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist');
    }
};
