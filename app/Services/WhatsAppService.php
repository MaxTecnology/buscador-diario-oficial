<?php

namespace App\Services;

use App\Models\ConfiguracaoSistema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $serverUrl;
    protected string $instance;
    protected string $apiKey;
    protected bool $enabled;

    public function __construct()
    {
        $this->serverUrl = ConfiguracaoSistema::get('whatsapp_server_url', '');
        $this->instance = ConfiguracaoSistema::get('whatsapp_instance', '');
        $this->apiKey = ConfiguracaoSistema::get('whatsapp_api_key', '');
        $this->enabled = ConfiguracaoSistema::get('whatsapp_enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->enabled && 
               !empty($this->serverUrl) && 
               !empty($this->instance) && 
               !empty($this->apiKey);
    }

    public function sendTextMessage(string $number, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'WhatsApp não configurado'
            ];
        }

        // Limpar número (remover caracteres especiais)
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // Adicionar código do país se não tiver
        if (!str_starts_with($number, '55')) {
            $number = '55' . $number;
        }

        $url = rtrim($this->serverUrl, '/') . '/message/sendText/' . $this->instance;

        $payload = [
            'number' => $number,
            'text' => $message
        ];

        // Adicionar opções se fornecidas
        if (!empty($options)) {
            $payload['options'] = array_merge([
                'delay' => 1000,
                'presence' => 'composing',
                'linkPreview' => true
            ], $options);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'apikey' => $this->apiKey
                ])
                ->post($url, $payload);

            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Erro na API: ' . $response->body(),
                    'status' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ];
        }
    }

    public function sendNotificationMessage(string $number, string $empresaNome, string $diarioNome, string $termo, string $contexto): array
    {
        $message = $this->buildNotificationMessage($empresaNome, $diarioNome, $termo, $contexto);
        
        return $this->sendTextMessage($number, $message);
    }

    protected function buildNotificationMessage(string $empresaNome, string $diarioNome, string $termo, string $contexto): string
    {
        $appName = ConfiguracaoSistema::get('system.app_name', 'Sistema de Diários Oficiais');
        
        return "🚨 *{$appName}* - Nova Ocorrência\n\n" .
               "🏢 *Empresa:* {$empresaNome}\n" .
               "📄 *Diário:* {$diarioNome}\n" .
               "🔍 *Termo encontrado:* {$termo}\n\n" .
               "📝 *Contexto:*\n" .
               "_{$contexto}_\n\n" .
               "📅 *Data:* " . now()->format('d/m/Y H:i') . "\n\n" .
               "Acesse o sistema para mais detalhes.";
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'WhatsApp não configurado'
            ];
        }

        return $this->sendTextMessage('5511999999999', '🧪 Teste de conexão WhatsApp API - ' . now()->format('d/m/Y H:i'));
    }

    public function isBusinessHours(): bool
    {
        $startTime = ConfiguracaoSistema::get('notifications.whatsapp_timeout_start', '08:00');
        $endTime = ConfiguracaoSistema::get('notifications.whatsapp_timeout_end', '22:00');
        
        $now = now()->format('H:i');
        
        return $now >= $startTime && $now <= $endTime;
    }
}