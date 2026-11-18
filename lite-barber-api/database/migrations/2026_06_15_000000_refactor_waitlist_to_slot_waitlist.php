<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // As colunas desired_date, desired_time e notified_at já existem na tabela.
        // Apenas atualizar o enum de status para refletir o novo conceito de lista de espera por horário.
        DB::statement("ALTER TABLE waitlist MODIFY COLUMN status ENUM('waiting','notified','booked','expired','cancelled') DEFAULT 'waiting'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE waitlist MODIFY COLUMN status ENUM('waiting','called','in_service','completed','cancelled','no_show') DEFAULT 'waiting'");
    }
};
