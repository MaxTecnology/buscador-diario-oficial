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
        Schema::create('diarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome_arquivo');
            $table->string('estado', 2);
            $table->date('data_diario');
            $table->string('hash_sha256')->unique();
            $table->string('caminho_arquivo');
            $table->integer('tamanho_arquivo');
            $table->integer('total_paginas')->nullable();
            $table->longText('texto_extraido')->nullable();
            $table->enum('status', ['pendente', 'processando', 'concluido', 'erro'])->default('pendente');
            $table->text('erro_mensagem')->nullable();
            $table->timestamp('processado_em')->nullable();
            $table->integer('tentativas')->default(0);
            $table->foreignId('usuario_upload_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['estado']);
            $table->index(['data_diario']);
            $table->index(['status']);
            $table->index(['usuario_upload_id']);
            $table->fullText(['texto_extraido']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diarios');
    }
};
