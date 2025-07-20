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
        Schema::table('user_empresa_permissions', function (Blueprint $table) {
            // Remover campos duplicados e desnecessários
            $table->dropColumn([
                'pode_receber_email',      // Duplicado com notificacao_email
                'pode_receber_whatsapp',   // Duplicado com notificacao_whatsapp
                'nivel_prioridade',        // Não usado atualmente
                'notificacao_imediata',    // Não usado atualmente
                'resumo_diario',           // Não usado atualmente
                'horario_resumo',          // Não usado atualmente
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_empresa_permissions', function (Blueprint $table) {
            // Restaurar campos removidos
            $table->boolean('pode_receber_email')->default(true);
            $table->boolean('pode_receber_whatsapp')->default(false);
            $table->enum('nivel_prioridade', ['baixa', 'media', 'alta'])->default('media');
            $table->boolean('notificacao_imediata')->default(true);
            $table->boolean('resumo_diario')->default(false);
            $table->time('horario_resumo')->default('08:00:00');
        });
    }
};
