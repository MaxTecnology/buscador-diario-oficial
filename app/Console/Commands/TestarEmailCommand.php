<?php

namespace App\Console\Commands;

use App\Mail\OcorrenciaEncontradaMail;
use App\Models\Empresa;
use App\Models\Diario;
use App\Models\Ocorrencia;
use App\Models\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestarEmailCommand extends Command
{
    protected $signature = 'email:testar {email} {--empresa-id=1} {--diario-id=1}';
    
    protected $description = 'Testar envio de email de ocorrência encontrada';

    public function handle(): int
    {
        $email = $this->argument('email');
        $empresaId = $this->option('empresa-id');
        $diarioId = $this->option('diario-id');

        $this->info("📧 Preparando teste de email...");

        // Buscar ou criar dados de teste
        $empresa = Empresa::find($empresaId);
        if (!$empresa) {
            // Tentar buscar por CNPJ antes de criar
            $empresa = Empresa::where('cnpj', '12345678000199')->first();
            
            if (!$empresa) {
                $this->info("🏢 Criando empresa de teste...");
                $empresa = Empresa::create([
                    'nome' => 'EMPRESA TESTE DE EMAIL LTDA',
                    'cnpj' => '12345678000199',
                    'inscricao_estadual' => '123456789',
                    'email' => $email,
                    'telefone' => '(11) 99999-9999',
                    'endereco' => 'Rua de Teste, 123',
                    'cidade' => 'São Paulo',
                    'estado' => 'SP',
                    'cep' => '01234-567',
                    'created_by' => 1,
                ]);
            } else {
                $this->info("🏢 Usando empresa de teste existente...");
            }
        }

        $diario = Diario::find($diarioId);
        if (!$diario) {
            $this->info("📄 Criando diário de teste...");
            $diario = Diario::create([
                'nome_arquivo' => 'diario-teste-email.pdf',
                'estado' => 'SP',
                'data_diario' => now(),
                'status' => 'concluido',
                'caminho_arquivo' => 'test/diario-teste-email.pdf',
                'hash_sha256' => hash('sha256', 'teste-email-' . time()),
                'tamanho_arquivo' => 1024000, // 1MB
                'total_paginas' => 50,
                'usuario_upload_id' => 1,
                'processado_em' => now(),
            ]);
        }

        // Buscar uma ocorrência existente ou usar dados simulados
        $ocorrencia = Ocorrencia::with(['empresa', 'diario'])->first();

        if (!$ocorrencia) {
            $this->warn("⚠️ Nenhuma ocorrência encontrada no sistema.");
            $this->info("🔍 Usando dados simulados para o teste...");
            
            // Criar um objeto simulado para o teste
            $ocorrencia = new \stdClass();
            $ocorrencia->id = 999;
            $ocorrencia->empresa_id = $empresa->id;
            $ocorrencia->diario_id = $diario->id;
            $ocorrencia->tipo_match = 'cnpj';
            $ocorrencia->score = 95.5;
            $ocorrencia->texto_match = 'EMPRESA TESTE DE EMAIL LTDA, inscrita no CNPJ sob o nº 12.345.678/0001-99, com sede na Rua de Teste, 123, São Paulo/SP.';
            $ocorrencia->pagina = 15;
        } else {
            $this->info("🔍 Usando ocorrência existente para o teste...");
            // Usar a empresa e diário da ocorrência existente
            $empresa = $ocorrencia->empresa;
            $diario = $ocorrencia->diario;
        }

        $this->info("📋 Dados preparados:");
        $this->line("   Empresa: {$empresa->nome}");
        $this->line("   CNPJ: {$empresa->cnpj}");
        $this->line("   Diário: {$diario->nome_arquivo}");
        $this->line("   Email destino: {$email}");
        $this->line("   Score: {$ocorrencia->score}%");

        $this->info("📤 Enviando email...");

        try {
            // Enviar email
            Mail::to($email)->send(new OcorrenciaEncontradaMail($empresa, $diario, $ocorrencia));

            // Registrar log de notificação
            NotificationLog::create([
                'ocorrencia_id' => is_object($ocorrencia) && isset($ocorrencia->id) ? $ocorrencia->id : null,
                'empresa_id' => $empresa->id,
                'diario_id' => $diario->id,
                'type' => 'email',
                'status' => 'sent',
                'recipient' => $email,
                'recipient_name' => 'Teste Manual',
                'subject' => "🚨 {$empresa->nome} encontrada em Diário Oficial - {$diario->estado}",
                'message' => "Email de teste enviado para {$email}",
                'headers' => [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'X-Test' => 'true',
                    'X-Empresa-ID' => $empresa->id,
                    'X-Diario-ID' => $diario->id,
                ],
                'triggered_by' => 'manual',
                'triggered_by_user_id' => 1,
                'ip_address' => '127.0.0.1',
                'sent_at' => now(),
            ]);

            $this->info("✅ Email enviado com sucesso!");
            $this->line("");
            $this->info("🌐 Para visualizar o email:");
            $this->line("   1. Acesse: http://localhost:8025");
            $this->line("   2. Você verá o email na interface do MailHog");
            $this->line("");
            $this->info("📊 O log foi registrado no sistema:");
            $this->line("   - Acesse Admin > Sistema > Timeline de Atividades");
            $this->line("   - Filtre por tipo 'Notificação' para ver o registro");

            return self::SUCCESS;

        } catch (\Exception $e) {
            // Registrar log de falha
            NotificationLog::create([
                'ocorrencia_id' => is_object($ocorrencia) && isset($ocorrencia->id) ? $ocorrencia->id : null,
                'empresa_id' => $empresa->id,
                'diario_id' => $diario->id,
                'type' => 'email',
                'status' => 'failed',
                'recipient' => $email,
                'recipient_name' => 'Teste Manual',
                'subject' => "🚨 {$empresa->nome} encontrada em Diário Oficial - {$diario->estado}",
                'message' => "Tentativa de email de teste para {$email}",
                'error_message' => $e->getMessage(),
                'triggered_by' => 'manual',
                'triggered_by_user_id' => 1,
                'ip_address' => '127.0.0.1',
                'failed_at' => now(),
            ]);

            $this->error("❌ Erro ao enviar email: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}