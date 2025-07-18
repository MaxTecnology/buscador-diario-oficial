<?php

namespace Database\Seeders;

use App\Models\Diario;
use App\Models\Empresa;
use App\Models\Ocorrencia;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OcorrenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $diarios = Diario::all();
        $empresas = Empresa::all();
        
        $tiposMatch = ['cnpj', 'nome', 'termo_personalizado'];
        $termosExemplo = [
            'Petrobras', 'Vale', 'Banco do Brasil', 'Ambev', 'JBS',
            'Odebrecht', 'Embraer', 'Gerdau', 'Marfrig', 'Localiza',
            'licitação', 'contrato', 'processo', 'multa', 'penalidade',
            'suspensão', 'impedimento', 'inabilitação', 'rescisão'
        ];
        
        foreach ($diarios as $diario) {
            $numOcorrencias = rand(0, 5); // 0 a 5 ocorrências por diário
            
            for ($i = 0; $i < $numOcorrencias; $i++) {
                $empresa = $empresas->random();
                $termo = $termosExemplo[array_rand($termosExemplo)];
                $tipo = $tiposMatch[array_rand($tiposMatch)];
                $score = match($tipo) {
                    'cnpj' => rand(95, 100) / 100,
                    'nome' => rand(85, 95) / 100,
                    'termo_personalizado' => rand(70, 90) / 100,
                };
                
                $notificadoEmail = rand(0, 1) === 1;
                $notificadoWhatsapp = rand(0, 1) === 1;
                $data = $diario->processado_em ?? $diario->created_at;
                
                Ocorrencia::create([
                    'diario_id' => $diario->id,
                    'empresa_id' => $empresa->id,
                    'tipo_match' => $tipo,
                    'termo_encontrado' => $termo,
                    'contexto_completo' => "...texto do diário oficial onde foi encontrado o termo '{$termo}' relacionado à empresa {$empresa->nome} no processo número 123456...",
                    'posicao_inicio' => rand(100, 10000),
                    'posicao_fim' => rand(10001, 20000),
                    'score_confianca' => $score,
                    'pagina' => rand(1, ($diario->total_paginas ?? 100)),
                    'notificado_email' => $notificadoEmail,
                    'notificado_whatsapp' => $notificadoWhatsapp,
                    'notificado_em' => ($notificadoEmail || $notificadoWhatsapp) ? now()->subDays(rand(0, 30)) : null,
                    'created_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }
    }
}