<?php

namespace App\Services;

use App\Models\Ocorrencia;
use App\Models\ConfiguracaoSistema;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificacaoService
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function notificarOcorrencia(Ocorrencia $ocorrencia, bool $forcarEnvio = false): array
    {
        $resultados = [
            'email' => false,
            'whatsapp' => false,
            'erros' => []
        ];

        try {
            // Verificar se deve enviar notificações
            $emailAtivo = ConfiguracaoSistema::get('notificacoes_email_ativo', true);
            $whatsappAtivo = ConfiguracaoSistema::get('notificacoes_whatsapp_ativo', true);
            $autoAtivo = ConfiguracaoSistema::get('notificacao_automatica_apos_processamento', true);

            // Se não forçar envio e notificação automática desabilitada, pular
            if (!$forcarEnvio && !$autoAtivo) {
                return $resultados;
            }

            // Obter usuários da empresa para notificar com suas configurações
            $usuarios = $ocorrencia->empresa->users()
                ->wherePivot('notificacao_email', true)
                ->orWherePivot('notificacao_whatsapp', true)
                ->get();
                
            Log::info('Usuários encontrados para notificação:', [
                'ocorrencia_id' => $ocorrencia->id,
                'empresa_id' => $ocorrencia->empresa_id,
                'usuarios_count' => $usuarios->count(),
                'usuarios' => $usuarios->map(fn($u) => [
                    'id' => $u->id,
                    'nome' => $u->nome,
                    'telefone_whatsapp' => $u->telefone_whatsapp,
                    'notificacao_email' => $u->pivot->notificacao_email ?? 'null',
                    'notificacao_whatsapp' => $u->pivot->notificacao_whatsapp ?? 'null',
                ])->toArray()
            ]);

            foreach ($usuarios as $usuario) {
                // Verificar se o usuário deve receber notificação baseado no nível de prioridade
                $nivelPrioridade = $usuario->pivot->nivel_prioridade ?? 'media';
                $scoreMinimo = match($nivelPrioridade) {
                    'baixa' => 0.9,  // 90% - apenas scores muito altos
                    'media' => $ocorrencia->empresa->score_minimo, // Score padrão da empresa
                    'alta' => 0.5,   // 50% - todas as ocorrências válidas
                    default => $ocorrencia->empresa->score_minimo
                };

                // Se o score da ocorrência for menor que o mínimo do usuário, pular
                if ($ocorrencia->score_confianca < $scoreMinimo) {
                    continue;
                }

                // Verificar se deve enviar notificação imediata
                $notificacaoImediata = $usuario->pivot->notificacao_imediata ?? true;
                if (!$notificacaoImediata && !$forcarEnvio) {
                    continue; // Usuário prefere apenas resumo diário
                }

                // Notificação por Email
                if (($emailAtivo || $forcarEnvio) && 
                    ($usuario->pivot->notificacao_email ?? false) && 
                    !$ocorrencia->notificado_email) {
                    
                    $emailEnviado = $this->enviarEmail($ocorrencia, $usuario);
                    if ($emailEnviado) {
                        $resultados['email'] = true;
                    }
                }

                // Notificação por WhatsApp
                if (($whatsappAtivo || $forcarEnvio) && 
                    ($usuario->pivot->notificacao_whatsapp ?? false) && 
                    !$ocorrencia->notificado_whatsapp &&
                    $usuario->telefone_whatsapp) {
                    
                    $whatsappEnviado = $this->enviarWhatsApp($ocorrencia, $usuario);
                    if ($whatsappEnviado) {
                        $resultados['whatsapp'] = true;
                    }
                }
            }

            // Marcar como notificado se pelo menos um envio foi bem-sucedido
            if ($resultados['email']) {
                $ocorrencia->marcarComoNotificadoPorEmail();
            }
            if ($resultados['whatsapp']) {
                $ocorrencia->marcarComoNotificadoPorWhatsapp();
            }

        } catch (\Exception $e) {
            Log::error('Erro ao enviar notificações: ' . $e->getMessage(), [
                'ocorrencia_id' => $ocorrencia->id,
                'empresa_id' => $ocorrencia->empresa_id
            ]);
            
            $resultados['erros'][] = $e->getMessage();
        }

        return $resultados;
    }

    protected function enviarEmail(Ocorrencia $ocorrencia, $usuario): bool
    {
        try {
            $template = ConfiguracaoSistema::get('notificacoes_email_template', 
                'Nova ocorrência encontrada para a empresa {empresa} no diário {diario}. Score: {score}');

            $assunto = 'Nova Ocorrência Encontrada - ' . $ocorrencia->empresa->nome;
            $mensagem = $this->processarTemplate($template, $ocorrencia);

            Mail::raw($mensagem, function ($message) use ($usuario, $assunto) {
                $message->to($usuario->email)
                        ->subject($assunto);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email: ' . $e->getMessage());
            return false;
        }
    }

    protected function enviarWhatsApp(Ocorrencia $ocorrencia, $usuario): bool
    {
        try {
            Log::info('Tentando enviar WhatsApp:', [
                'usuario_id' => $usuario->id,
                'telefone_whatsapp' => $usuario->telefone_whatsapp,
                'ocorrencia_id' => $ocorrencia->id
            ]);

            if (empty($usuario->telefone_whatsapp)) {
                Log::warning('Usuário sem telefone WhatsApp:', ['usuario_id' => $usuario->id]);
                return false;
            }

            $template = ConfiguracaoSistema::get('notificacoes_whatsapp_template', 
                '🔔 *Nova Ocorrência Encontrada*

📋 Empresa: {empresa}
📄 Diário: {diario}
📊 Score: {score}
📅 Data: {data}');

            $mensagem = $this->processarTemplate($template, $ocorrencia);

            Log::info('Mensagem processada:', ['mensagem' => $mensagem]);

            $resultado = $this->whatsappService->sendTextMessage($usuario->telefone_whatsapp, $mensagem);
            
            Log::info('Resultado do envio WhatsApp:', $resultado);
            
            return $resultado['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('Erro ao enviar WhatsApp: ' . $e->getMessage(), [
                'usuario_id' => $usuario->id,
                'telefone' => $usuario->telefone_whatsapp ?? 'null'
            ]);
            return false;
        }
    }

    protected function processarTemplate(string $template, Ocorrencia $ocorrencia): string
    {
        $substituicoes = [
            '{empresa}' => $ocorrencia->empresa->nome,
            '{diario}' => $ocorrencia->diario->nome_arquivo,
            '{score}' => number_format($ocorrencia->score_confianca * 100, 1) . '%',
            '{data}' => $ocorrencia->created_at->format('d/m/Y H:i'),
            '{termo}' => $ocorrencia->termo_encontrado,
            '{tipo}' => match($ocorrencia->tipo_match) {
                'cnpj' => 'CNPJ',
                'inscricao_estadual' => 'Inscrição Estadual',
                'nome' => 'Nome da Empresa',
                'variante' => 'Variante do Nome',
                'termo_personalizado' => 'Termo Personalizado',
                default => 'Outro',
            },
            '{contexto}' => mb_substr($ocorrencia->contexto_completo, 0, 200) . '...',
        ];

        return str_replace(array_keys($substituicoes), array_values($substituicoes), $template);
    }

    public function notificarMultiplasOcorrencias(array $ocorrenciaIds, bool $forcarEnvio = false): array
    {
        $resultados = [];
        
        foreach ($ocorrenciaIds as $id) {
            $ocorrencia = Ocorrencia::find($id);
            if ($ocorrencia) {
                $resultados[$id] = $this->notificarOcorrencia($ocorrencia, $forcarEnvio);
            }
        }

        return $resultados;
    }
}