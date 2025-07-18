<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class TestWhatsApp extends Command
{
    protected $signature = 'whatsapp:test {number : Número para teste (ex: 11999999999)}';
    protected $description = 'Testa o envio de mensagem via WhatsApp';

    public function handle()
    {
        $number = $this->argument('number');
        $whatsappService = new WhatsAppService();

        $this->info('Testando configuração do WhatsApp...');

        if (!$whatsappService->isConfigured()) {
            $this->error('WhatsApp não está configurado. Configure as seguintes variáveis:');
            $this->line('- notifications.whatsapp_server_url');
            $this->line('- notifications.whatsapp_instance');
            $this->line('- notifications.whatsapp_api_key');
            $this->line('- notifications.whatsapp_enabled');
            return 1;
        }

        $this->info('Enviando mensagem de teste para: ' . $number);

        $result = $whatsappService->sendTextMessage($number, 'Teste de conexão WhatsApp API - ' . now()->format('d/m/Y H:i'));

        if ($result['success']) {
            $this->info('✅ Mensagem enviada com sucesso!');
            $this->line('Resposta: ' . json_encode($result['response'], JSON_PRETTY_PRINT));
        } else {
            $this->error('❌ Erro ao enviar mensagem: ' . $result['error']);
            if (isset($result['status'])) {
                $this->line('Status HTTP: ' . $result['status']);
            }
        }

        return $result['success'] ? 0 : 1;
    }
}