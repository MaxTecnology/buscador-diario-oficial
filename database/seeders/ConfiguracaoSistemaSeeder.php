<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConfiguracaoSistemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configuracoes = [
            // Notificações Email
            [
                'chave' => 'notificacoes_email_ativo',
                'valor' => '1',
                'tipo' => 'boolean',
                'descricao' => 'Ativar/desativar notificações automáticas por email',
                'categoria' => 'notificacoes',
            ],
            [
                'chave' => 'notificacoes_email_template',
                'valor' => 'Nova ocorrência encontrada para a empresa {empresa} no diário {diario}. Score: {score}',
                'tipo' => 'string',
                'descricao' => 'Template padrão para emails de notificação',
                'categoria' => 'notificacoes',
            ],
            
            // Notificações WhatsApp
            [
                'chave' => 'notificacoes_whatsapp_ativo',
                'valor' => '1',
                'tipo' => 'boolean',
                'descricao' => 'Ativar/desativar notificações automáticas por WhatsApp',
                'categoria' => 'notificacoes',
            ],
            [
                'chave' => 'notificacoes_whatsapp_template',
                'valor' => '🔔 *Nova Ocorrência Encontrada*

📋 Empresa: {empresa}
📄 Diário: {diario}
📊 Score: {score}
📅 Data: {data}',
                'tipo' => 'string',
                'descricao' => 'Template padrão para mensagens WhatsApp',
                'categoria' => 'notificacoes',
            ],
            
            // Configurações de processamento
            [
                'chave' => 'processamento_automatico',
                'valor' => '1',
                'tipo' => 'boolean',
                'descricao' => 'Processar PDFs automaticamente após upload',
                'categoria' => 'processamento',
            ],
            [
                'chave' => 'notificacao_automatica_apos_processamento',
                'valor' => '0',
                'tipo' => 'boolean',
                'descricao' => 'Enviar notificações automaticamente após encontrar ocorrências',
                'categoria' => 'processamento',
            ],
            [
                'chave' => 'processamento_assincrono',
                'valor' => '0',
                'tipo' => 'boolean',
                'descricao' => 'Processar PDFs de forma assíncrona (em segundo plano)',
                'categoria' => 'processamento',
            ],
            
            // Configurações WhatsApp
            [
                'chave' => 'whatsapp_enabled',
                'valor' => '1',
                'tipo' => 'boolean',
                'descricao' => 'Ativar/desativar WhatsApp globalmente',
                'categoria' => 'whatsapp',
            ],
            [
                'chave' => 'whatsapp_server_url',
                'valor' => 'http://localhost:3000',
                'tipo' => 'string',
                'descricao' => 'URL do servidor WhatsApp API',
                'categoria' => 'whatsapp',
            ],
            [
                'chave' => 'whatsapp_instance',
                'valor' => 'instance1',
                'tipo' => 'string',
                'descricao' => 'Nome da instância WhatsApp',
                'categoria' => 'whatsapp',
            ],
            [
                'chave' => 'whatsapp_api_key',
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Chave da API WhatsApp',
                'categoria' => 'whatsapp',
            ],
        ];

        foreach ($configuracoes as $config) {
            \App\Models\ConfiguracaoSistema::updateOrCreate(
                ['chave' => $config['chave']],
                $config
            );
        }
    }
}
