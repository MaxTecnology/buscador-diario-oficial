<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // Configurações gerais
            [
                'chave' => 'app.name',
                'valor' => 'Diário Oficial',
                'tipo' => 'string',
                'descricao' => 'Nome da aplicação'
            ],
            [
                'chave' => 'app.description',
                'valor' => 'Sistema de Monitoramento de Diários Oficiais',
                'tipo' => 'string',
                'descricao' => 'Descrição da aplicação'
            ],
            [
                'chave' => 'app.logo',
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Logo da aplicação'
            ],
            [
                'chave' => 'app.timezone',
                'valor' => 'America/Sao_Paulo',
                'tipo' => 'string',
                'descricao' => 'Fuso horário da aplicação'
            ],
            
            // Configurações de processamento
            [
                'chave' => 'processing.enabled',
                'valor' => '1',
                'tipo' => 'boolean',
                'descricao' => 'Habilitar processamento automático de PDFs'
            ],
            [
                'chave' => 'processing.max_concurrent',
                'valor' => '5',
                'tipo' => 'number',
                'descricao' => 'Número máximo de PDFs processados simultaneamente'
            ],
            [
                'chave' => 'processing.timeout',
                'valor' => '300',
                'tipo' => 'number',
                'descricao' => 'Tempo limite para processamento de um PDF (segundos)'
            ],
            [
                'chave' => 'processing.retry_attempts',
                'valor' => '3',
                'tipo' => 'number',
                'descricao' => 'Número de tentativas em caso de falha no processamento'
            ],
            
            // Configurações de storage
            [
                'chave' => 'storage.max_file_size',
                'valor' => '10240',
                'tipo' => 'number',
                'descricao' => 'Tamanho máximo do arquivo PDF em KB'
            ],
        ];

        foreach ($configs as $config) {
            SystemConfig::updateOrCreate(
                ['chave' => $config['chave']],
                $config
            );
        }
    }
}