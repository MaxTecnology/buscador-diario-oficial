<?php

namespace App\Jobs;

use App\Models\Ocorrencia;
use App\Models\NotificationLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public Ocorrencia $ocorrencia,
        public string $phoneNumber,
        public ?string $message = null
    ) {}

    public function handle(WhatsAppService $whatsappService): void
    {
        Log::info('Iniciando envio de WhatsApp', [
            'ocorrencia_id' => $this->ocorrencia->id,
            'phone' => $this->phoneNumber
        ]);

        // Verificar se é horário comercial
        if (!$whatsappService->isBusinessHours()) {
            Log::info('Fora do horário comercial, reagendando', [
                'ocorrencia_id' => $this->ocorrencia->id
            ]);
            
            $this->release(3600); // Reagendar para 1 hora
            return;
        }

        // Verificar se já foi notificado
        if ($this->ocorrencia->notificado_whatsapp) {
            Log::info('WhatsApp já enviado para esta ocorrência', [
                'ocorrencia_id' => $this->ocorrencia->id
            ]);
            return;
        }

        // Usar mensagem personalizada ou gerar automaticamente
        if ($this->message) {
            $result = $whatsappService->sendTextMessage($this->phoneNumber, $this->message);
        } else {
            $result = $whatsappService->sendNotificationMessage(
                $this->phoneNumber,
                $this->ocorrencia->empresa->nome,
                $this->ocorrencia->diario->nome_arquivo,
                $this->ocorrencia->termo_encontrado,
                $this->ocorrencia->contexto_completo
            );
        }

        if ($result['success']) {
            // Marcar como notificado
            $this->ocorrencia->update([
                'notificado_whatsapp' => true,
                'notificado_em' => now()
            ]);
            
            // Registrar log de sucesso
            NotificationLog::logWhatsAppSent([
                'ocorrencia_id' => $this->ocorrencia->id,
                'empresa_id' => $this->ocorrencia->empresa_id,
                'diario_id' => $this->ocorrencia->diario_id,
                'recipient' => $this->phoneNumber,
                'recipient_name' => $this->ocorrencia->empresa->nome,
                'message' => $this->message ?: "Mensagem automática de ocorrência encontrada",
                'external_id' => $result['response']['id'] ?? null,
                'triggered_by' => 'automatic',
                'headers' => [
                    'api_response' => $result['response'],
                    'message_type' => 'text'
                ]
            ]);
            
            Log::info('WhatsApp enviado com sucesso', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'response' => $result['response']
            ]);
        } else {
            // Registrar log de falha
            NotificationLog::logWhatsAppFailed([
                'ocorrencia_id' => $this->ocorrencia->id,
                'empresa_id' => $this->ocorrencia->empresa_id,
                'diario_id' => $this->ocorrencia->diario_id,
                'recipient' => $this->phoneNumber,
                'recipient_name' => $this->ocorrencia->empresa->nome,
                'message' => $this->message ?: "Mensagem automática de ocorrência encontrada",
                'error_message' => $result['error'],
                'triggered_by' => 'automatic',
                'attempts' => $this->attempts()
            ]);
            
            Log::error('Erro ao enviar WhatsApp', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'error' => $result['error']
            ]);
            
            throw new \Exception('Erro ao enviar WhatsApp: ' . $result['error']);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job de WhatsApp falhou', [
            'ocorrencia_id' => $this->ocorrencia->id,
            'phone' => $this->phoneNumber,
            'error' => $exception->getMessage()
        ]);
    }
}