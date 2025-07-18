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
        Schema::create('ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diario_id')->constrained()->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained()->onDelete('cascade');
            $table->enum('tipo_match', ['cnpj', 'nome', 'termo_personalizado']);
            $table->string('termo_encontrado');
            $table->text('contexto_completo');
            $table->integer('posicao_inicio');
            $table->integer('posicao_fim');
            $table->decimal('score_confianca', 3, 2);
            $table->integer('pagina')->nullable();
            $table->boolean('notificado_email')->default(false);
            $table->boolean('notificado_whatsapp')->default(false);
            $table->timestamp('notificado_em')->nullable();
            $table->timestamps();
            
            $table->index(['diario_id']);
            $table->index(['empresa_id']);
            $table->index(['tipo_match']);
            $table->index(['score_confianca']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocorrencias');
    }
};
