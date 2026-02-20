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
        Schema::table('ocorrencias', function (Blueprint $table) {
            $table->enum('confiabilidade', ['alta', 'suspeito'])
                ->default('alta')
                ->after('score_confianca');
            $table->enum('status_revisao', ['pendente', 'aprovado', 'falso_positivo'])
                ->default('pendente')
                ->after('confiabilidade');

            $table->index(['confiabilidade']);
            $table->index(['status_revisao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ocorrencias', function (Blueprint $table) {
            $table->dropIndex(['confiabilidade']);
            $table->dropIndex(['status_revisao']);
            $table->dropColumn(['confiabilidade', 'status_revisao']);
        });
    }
};
