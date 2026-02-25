<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diario_processamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', ['inicial', 'reprocessamento'])->default('inicial');
            $table->enum('modo', ['completo', 'somente_busca'])->default('completo');
            $table->enum('status', ['pendente', 'processando', 'concluido', 'erro'])->default('pendente');
            $table->string('motivo')->nullable();
            $table->boolean('notificar')->default(true);
            $table->boolean('limpar_ocorrencias_anteriores')->default(false);
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->text('erro_mensagem')->nullable();
            $table->unsignedInteger('total_ocorrencias')->default(0);
            $table->unsignedInteger('novas_ocorrencias')->default(0);
            $table->unsignedInteger('ocorrencias_desativadas')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['diario_id', 'created_at']);
            $table->index(['status', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diario_processamentos');
    }
};
