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
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->text('valor');
            $table->enum('tipo', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->text('descricao')->nullable();
            $table->timestamps();
            
            $table->index(['chave']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
