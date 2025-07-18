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
        DB::statement("ALTER TABLE ocorrencias MODIFY COLUMN tipo_match ENUM('cnpj', 'nome', 'termo_personalizado', 'inscricao_estadual', 'variante') DEFAULT 'nome'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ocorrencias MODIFY COLUMN tipo_match ENUM('cnpj', 'nome', 'termo_personalizado') DEFAULT 'nome'");
    }
};
