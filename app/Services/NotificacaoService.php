<?php

namespace App\Services;

use App\Models\Ocorrencia;
use App\Models\ConfiguracaoSistema;
use App\Models\NotificationLog;
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
            if (! $this->deveNotificarOcorrencia($ocorrencia)) {
                Log::info('Notificação de ocorrência ignorada por regra de IE.', [
                    'ocorrencia_id' => $ocorrencia->id,
                    'tipo_match' => $ocorrencia->tipo_match,
                    'termo_encontrado' => $ocorrencia->termo_encontrado,
                ]);

                return $resultados;
            }

            // Verificar se deve enviar notificações
            $emailAtivo = ConfiguracaoSistema::get('notificacoes_email_ativo', true);
            $whatsappAtivo = ConfiguracaoSistema::get('notificacoes_whatsapp_ativo', true);
            $autoAtivo = ConfiguracaoSistema::get('notificacao_automatica_apos_processamento', true);

            // Se não forçar envio e notificação automática desabilitada, pular
            if (!$forcarEnvio && !$autoAtivo) {
                return $resultados;
            }

            // ABORDAGEM SIMPLIFICADA: usar apenas a relação Eloquent
            $usuarios = $ocorrencia->empresa->users()
                ->where('telefone_whatsapp', '!=', null)
                ->where('telefone_whatsapp', '!=', '')
                ->get();
            
            Log::info('Usuários encontrados (abordagem simplificada):', [
                'ocorrencia_id' => $ocorrencia->id,
                'empresa_id' => $ocorrencia->empresa_id,
                'usuarios_count' => $usuarios->count(),
                'usuarios' => $usuarios->map(fn($u) => [
                    'id' => $u->id,
                    'nome' => $u->nome,
                    'email' => $u->email,
                    'telefone_whatsapp' => $u->telefone_whatsapp,
                    'notificacao_email' => $u->pivot->notificacao_email ?? 'null',
                    'notificacao_whatsapp' => $u->pivot->notificacao_whatsapp ?? 'null',
                ])->toArray()
            ]);

            foreach ($usuarios as $usuario) {
                // LÓGICA SIMPLIFICADA: só verificar se tem WhatsApp habilitado no pivot
                $podeWhatsapp = (bool) ($usuario->pivot->notificacao_whatsapp ?? false);
                $podeEmail = (bool) ($usuario->pivot->notificacao_email ?? false);
                
                Log::info('Verificando usuário:', [
                    'usuario_id' => $usuario->id,
                    'nome' => $usuario->nome,
                    'email' => $usuario->email,
                    'telefone_whatsapp' => $usuario->telefone_whatsapp,
                    'pode_email' => $podeEmail,
                    'pode_whatsapp' => $podeWhatsapp,
                    'whatsapp_ativo' => $whatsappAtivo,
                    'forcar_envio' => $forcarEnvio,
                    'ja_notificado_whatsapp' => $ocorrencia->notificado_whatsapp,
                    'ja_notificado_email' => $ocorrencia->notificado_email
                ]);

                // Notificação por Email
                if (($emailAtivo || $forcarEnvio) && 
                    $podeEmail && 
                    !$ocorrencia->notificado_email &&
                    $usuario->email) {
                    
                    $emailEnviado = $this->enviarEmail($ocorrencia, $usuario);
                    if ($emailEnviado) {
                        $resultados['email'] = true;
                    }
                }

                // Notificação por WhatsApp
                if (($whatsappAtivo || $forcarEnvio) && 
                    $podeWhatsapp && 
                    !$ocorrencia->notificado_whatsapp &&
                    $usuario->telefone_whatsapp) {
                    
                    Log::info('Enviando WhatsApp para usuário', ['usuario_id' => $usuario->id]);
                    $whatsappEnviado = $this->enviarWhatsApp($ocorrencia, $usuario);
                    if ($whatsappEnviado) {
                        $resultados['whatsapp'] = true;
                    }
                } else {
                    Log::info('WhatsApp não enviado para usuário', [
                        'usuario_id' => $usuario->id,
                        'motivo' => 'Condições não atendidas',
                        'detalhes' => [
                            'whatsapp_ativo' => $whatsappAtivo,
                            'forcar_envio' => $forcarEnvio,
                            'pode_whatsapp' => $podeWhatsapp,
                            'ja_notificado' => $ocorrencia->notificado_whatsapp,
                            'tem_telefone' => !empty($usuario->telefone_whatsapp)
                        ]
                    ]);
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

    protected function deveNotificarOcorrencia(Ocorrencia $ocorrencia): bool
    {
        if ($ocorrencia->tipo_match !== 'inscricao_estadual') {
            return true;
        }

        $digitos = preg_replace('/\D+/', '', (string) $ocorrencia->termo_encontrado) ?? '';
        $ieEmpresa = preg_replace('/\D+/', '', (string) ($ocorrencia->empresa->inscricao_estadual ?? '')) ?? '';

        if (strlen($ieEmpresa) < 8) {
            return false;
        }

        $ieBase = substr($ieEmpresa, 0, 8);

        // Regra de negócio: para match por IE, notificar apenas quando o termo encontrado
        // for o corpo da inscrição (8 dígitos), ignorando o dígito verificador.
        return strlen($digitos) === 8 && $digitos === $ieBase;
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

            // Registrar log de sucesso
            NotificationLog::logEmailSent([
                'ocorrencia_id' => $ocorrencia->id,
                'empresa_id' => $ocorrencia->empresa_id,
                'diario_id' => $ocorrencia->diario_id,
                'recipient' => $usuario->email,
                'recipient_name' => $usuario->nome,
                'message' => $mensagem,
                'subject' => $assunto,
                'triggered_by' => 'manual'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email: ' . $e->getMessage());
            
            // Registrar log de falha
            NotificationLog::logEmailFailed([
                'ocorrencia_id' => $ocorrencia->id,
                'empresa_id' => $ocorrencia->empresa_id,
                'diario_id' => $ocorrencia->diario_id,
                'recipient' => $usuario->email,
                'recipient_name' => $usuario->nome,
                'message' => $mensagem ?? 'Erro ao gerar mensagem',
                'error_message' => $e->getMessage(),
                'triggered_by' => 'manual'
            ]);
            
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
            
            Log::info('Verificando sucesso do WhatsApp:', [
                'success_field' => $resultado['success'] ?? 'not_set',
                'is_success' => ($resultado['success'] ?? false) ? 'true' : 'false'
            ]);

            if ($resultado['success'] ?? false) {
                Log::info('WhatsApp enviado com sucesso, registrando log...');
                // Registrar log de sucesso
                NotificationLog::logWhatsAppSent([
                    'ocorrencia_id' => $ocorrencia->id,
                    'empresa_id' => $ocorrencia->empresa_id,
                    'diario_id' => $ocorrencia->diario_id,
                    'recipient' => $usuario->telefone_whatsapp,
                    'recipient_name' => $usuario->nome,
                    'message' => $mensagem,
                    'external_id' => $resultado['response']['id'] ?? null,
                    'triggered_by' => 'manual',
                    'headers' => [
                        'api_response' => $resultado['response'] ?? [],
                        'message_type' => 'text'
                    ]
                ]);
                
                Log::info('Log de sucesso registrado!');
                return true;
            } else {
                Log::warning('WhatsApp falhou, registrando log de falha...');
                // Registrar log de falha
                NotificationLog::logWhatsAppFailed([
                    'ocorrencia_id' => $ocorrencia->id,
                    'empresa_id' => $ocorrencia->empresa_id,
                    'diario_id' => $ocorrencia->diario_id,
                    'recipient' => $usuario->telefone_whatsapp,
                    'recipient_name' => $usuario->nome,
                    'message' => $mensagem,
                    'error_message' => $resultado['error'] ?? 'Erro desconhecido',
                    'triggered_by' => 'manual'
                ]);
                
                return false;
            }
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

    public function enviarEmailParaUsuario(Ocorrencia $ocorrencia, $usuario): bool
    {
        try {
            if (!$usuario->email) {
                Log::warning('Usuário sem email:', ['usuario_id' => $usuario->id]);
                return false;
            }

            return $this->enviarEmail($ocorrencia, $usuario);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email para usuário específico: ' . $e->getMessage(), [
                'usuario_id' => $usuario->id,
                'ocorrencia_id' => $ocorrencia->id
            ]);
            return false;
        }
    }

    public function enviarWhatsAppParaUsuario(Ocorrencia $ocorrencia, $usuario): bool
    {
        try {
            if (!$usuario->telefone_whatsapp) {
                Log::warning('Usuário sem telefone WhatsApp:', ['usuario_id' => $usuario->id]);
                return false;
            }

            return $this->enviarWhatsApp($ocorrencia, $usuario);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar WhatsApp para usuário específico: ' . $e->getMessage(), [
                'usuario_id' => $usuario->id,
                'ocorrencia_id' => $ocorrencia->id
            ]);
            return false;
        }
    }
}
