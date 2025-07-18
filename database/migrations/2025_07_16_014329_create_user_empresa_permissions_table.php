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
        Schema::create('user_empresa_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained()->onDelete('cascade');
            $table->boolean('pode_visualizar')->default(true);
            $table->boolean('pode_receber_email')->default(false);
            $table->boolean('pode_receber_whatsapp')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'empresa_id']);
            $table->index(['user_id']);
            $table->index(['empresa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_empresa_permissions');
    }
};
