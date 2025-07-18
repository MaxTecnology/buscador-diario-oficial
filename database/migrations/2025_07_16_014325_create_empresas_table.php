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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cnpj')->unique()->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->json('termos_personalizados')->nullable();
            $table->json('variantes_busca')->nullable();
            $table->enum('prioridade', ['alta', 'media', 'baixa'])->default('media');
            $table->decimal('score_minimo', 3, 2)->default(0.85);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            
            $table->index(['nome']);
            $table->index(['cnpj']);
            $table->index(['ativo']);
            $table->index(['prioridade']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
