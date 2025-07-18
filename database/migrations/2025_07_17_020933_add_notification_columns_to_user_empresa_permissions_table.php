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
            $table->boolean('notificacao_email')->default(true)->after('pode_receber_whatsapp');
            $table->boolean('notificacao_whatsapp')->default(false)->after('notificacao_email');
            $table->enum('nivel_prioridade', ['baixa', 'media', 'alta'])->default('media')->after('notificacao_whatsapp');
            $table->boolean('notificacao_imediata')->default(true)->after('nivel_prioridade');
            $table->boolean('resumo_diario')->default(false)->after('notificacao_imediata');
            $table->time('horario_resumo')->nullable()->after('resumo_diario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_empresa_permissions', function (Blueprint $table) {
            $table->dropColumn([
                'notificacao_email',
                'notificacao_whatsapp', 
                'nivel_prioridade',
                'notificacao_imediata',
                'resumo_diario',
                'horario_resumo'
            ]);
        });
    }
};
