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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('professional_id')->constrained()->onDelete('restrict');
            $table->foreignId('service_id')->constrained()->onDelete('restrict');

            $table->string('client_name', 150);
            $table->string('client_phone', 20);

            $table->dateTime('scheduled_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');

            $table->enum('cancelled_by', ['client', 'admin'])->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('price_snapshot', 8, 2);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'scheduled_at']);
            $table->index(['professional_id', 'scheduled_at']);
            $table->index(['shop_id', 'status']);
            $table->unique(['professional_id', 'scheduled_at'], 'uk_professional_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
