<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestao_diario_logs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->string('source', 120);
            $table->string('external_id', 191)->nullable();
            $table->enum('status', ['recebido', 'enfileirado', 'duplicado', 'rejeitado', 'erro'])->default('recebido');
            $table->foreignId('diario_id')->nullable()->constrained('diarios')->nullOnDelete();
            $table->string('estado', 2)->nullable();
            $table->date('data_diario')->nullable();
            $table->string('nome_arquivo')->nullable();
            $table->string('bucket', 120)->nullable();
            $table->string('object_key', 1024)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('mensagem')->nullable();
            $table->ipAddress('request_ip')->nullable();
            $table->json('metadata')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source', 'external_id']);
            $table->index(['diario_id']);
            $table->index(['sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestao_diario_logs');
    }
};

