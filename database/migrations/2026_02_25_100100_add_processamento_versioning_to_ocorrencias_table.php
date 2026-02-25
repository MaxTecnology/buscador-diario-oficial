<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocorrencias', function (Blueprint $table) {
            $table->foreignId('diario_processamento_id')
                ->nullable()
                ->after('diario_id')
                ->constrained('diario_processamentos')
                ->nullOnDelete();

            $table->boolean('ativo')
                ->default(true)
                ->after('diario_processamento_id');

            $table->index(['diario_id', 'ativo']);
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('ocorrencias', function (Blueprint $table) {
            $table->dropIndex(['diario_id', 'ativo']);
            $table->dropIndex(['ativo']);
            $table->dropConstrainedForeignId('diario_processamento_id');
            $table->dropColumn('ativo');
        });
    }
};
