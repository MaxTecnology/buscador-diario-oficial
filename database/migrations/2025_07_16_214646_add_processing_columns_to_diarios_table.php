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
        Schema::table('diarios', function (Blueprint $table) {
            $table->longText('texto_completo')->nullable()->after('texto_extraido');
            $table->enum('status_processamento', ['pendente', 'processando', 'processado', 'erro'])->default('pendente')->after('status');
            $table->text('erro_processamento')->nullable()->after('erro_mensagem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diarios', function (Blueprint $table) {
            $table->dropColumn(['texto_completo', 'status_processamento', 'erro_processamento']);
        });
    }
};
