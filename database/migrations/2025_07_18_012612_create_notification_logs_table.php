<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->unsignedBigInteger('ocorrencia_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('diario_id')->nullable();
            
            // Tipo de notificação
            $table->string('type'); // email, whatsapp
            $table->string('status'); // sent, failed, pending
            
            // Destinatário
            $table->string('recipient'); // email ou telefone
            $table->string('recipient_name')->nullable();
            
            // Conteúdo da mensagem (para auditoria)
            $table->text('subject')->nullable(); // Assunto do email
            $table->text('message'); // Conteúdo enviado
            
            // Dados técnicos
            $table->json('headers')->nullable(); // Headers do email ou dados da API WhatsApp
            $table->text('external_id')->nullable(); // ID retornado pelo provedor
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(1);
            
            // Informações contextuais
            $table->string('triggered_by'); // manual, automatic
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            
            // Timestamps imutáveis
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index(['type', 'status', 'created_at']);
            $table->index(['recipient', 'created_at']);
            $table->index(['ocorrencia_id', 'type']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            
            // Chaves estrangeiras
            $table->foreign('ocorrencia_id')->references('id')->on('ocorrencias')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('set null');
            $table->foreign('diario_id')->references('id')->on('diarios')->onDelete('set null');
            $table->foreign('triggered_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};