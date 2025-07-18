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
        Schema::create('configuracoes_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->text('valor')->nullable();
            $table->enum('tipo', ['string', 'integer', 'float', 'boolean', 'json'])->default('string');
            $table->text('descricao')->nullable();
            $table->string('categoria')->default('geral');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['chave', 'ativo']);
            $table->index(['categoria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes_sistema');
    }
};
