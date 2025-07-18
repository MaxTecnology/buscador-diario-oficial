<?php

namespace Database\Seeders;

use App\Models\Diario;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@diario.com')->first();
        $operator = User::where('email', 'operator@diario.com')->first();
        
        $estados = ['SP', 'RJ', 'MG', 'RS', 'PR', 'SC', 'BA', 'GO', 'ES', 'DF'];
        $status = ['pendente', 'processando', 'concluido', 'erro'];
        
        for ($i = 1; $i <= 30; $i++) {
            $estado = $estados[array_rand($estados)];
            $statusAtual = $status[array_rand($status)];
            $data = now()->subDays(rand(0, 30));
            
            Diario::create([
                'nome_arquivo' => "diario_oficial_{$estado}_" . $data->format('Y_m_d') . ".pdf",
                'estado' => $estado,
                'data_diario' => $data,
                'hash_sha256' => hash('sha256', "diario_{$i}_{$estado}_{$data->format('Y-m-d')}"),
                'caminho_arquivo' => "diarios/diario_oficial_{$estado}_" . $data->format('Y_m_d') . ".pdf",
                'tamanho_arquivo' => rand(1024 * 1024, 50 * 1024 * 1024), // 1MB a 50MB
                'total_paginas' => rand(50, 500),
                'texto_extraido' => $statusAtual === 'concluido' ? 'Texto extraído do diário oficial...' : null,
                'status' => $statusAtual,
                'erro_mensagem' => $statusAtual === 'erro' ? 'Erro ao processar PDF: arquivo corrompido' : null,
                'processado_em' => $statusAtual === 'concluido' ? $data->addHours(rand(1, 6)) : null,
                'tentativas' => $statusAtual === 'erro' ? rand(1, 3) : ($statusAtual === 'concluido' ? 1 : 0),
                'usuario_upload_id' => rand(0, 1) ? $admin->id : $operator->id,
                'created_at' => $data,
                'updated_at' => $data,
            ]);
        }
    }
}