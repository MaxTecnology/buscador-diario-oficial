<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            
            // Informações do usuário
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name');
            $table->string('user_email');
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            
            // Ação realizada
            $table->string('action'); // created, updated, deleted, processed, etc.
            $table->string('entity_type'); // Diario, Empresa, Ocorrencia, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_name')->nullable(); // Nome amigável do item
            
            // Descrição visual da ação
            $table->text('description'); // "Diário 'documento.pdf' foi criado"
            $table->string('icon')->nullable(); // Ícone para interface
            $table->string('color')->default('gray'); // Cor para interface
            
            // Dados antes e depois (para auditoria completa)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            
            // Contexto adicional
            $table->json('context')->nullable(); // Dados extras específicos
            $table->string('source')->default('web'); // web, api, cli
            $table->string('session_id')->nullable();
            
            // Timestamp imutável
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            // Índices para performance
            $table->index(['user_id', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'occurred_at']);
            $table->index('occurred_at');
            
            // Chave estrangeira
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};