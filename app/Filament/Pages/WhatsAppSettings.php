<?php

namespace App\Filament\Pages;

use App\Models\ConfiguracaoSistema;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatsAppSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.whatsapp-settings';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $title = 'Configurações do WhatsApp';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'whatsapp_enabled' => ConfiguracaoSistema::get('whatsapp_enabled', false),
            'whatsapp_server_url' => ConfiguracaoSistema::get('whatsapp_server_url', ''),
            'whatsapp_instance' => ConfiguracaoSistema::get('whatsapp_instance', ''),
            'whatsapp_api_key' => ConfiguracaoSistema::get('whatsapp_api_key', ''),
            'whatsapp_timeout_start' => ConfiguracaoSistema::get('whatsapp_timeout_start', '08:00'),
            'whatsapp_timeout_end' => ConfiguracaoSistema::get('whatsapp_timeout_end', '22:00'),
            'whatsapp_retry_attempts' => ConfiguracaoSistema::get('whatsapp_retry_attempts', 3),
            'test_phone' => '',
            'test_message' => 'Teste de conexão WhatsApp API

Data: ' . now()->format('d/m/Y H:i'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configurações da API')
                    ->schema([
                        Toggle::make('whatsapp_enabled')
                            ->label('Habilitar WhatsApp')
                            ->helperText('Ative para enviar notificações via WhatsApp'),
                        
                        TextInput::make('whatsapp_server_url')
                            ->label('URL do Servidor')
                            ->placeholder('https://seu-servidor.com')
                            ->required()
                            ->url()
                            ->helperText('URL base do servidor WhatsApp'),
                        
                        TextInput::make('whatsapp_instance')
                            ->label('Nome da Instância')
                            ->required()
                            ->helperText('Nome da instância configurada no servidor'),
                        
                        TextInput::make('whatsapp_api_key')
                            ->label('API Key')
                            ->required()
                            ->password()
                            ->revealable()
                            ->helperText('Chave de API para autenticação'),
                    ])->columns(2),
                
                Section::make('Configurações de Envio')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('whatsapp_timeout_start')
                                    ->label('Horário de Início')
                                    ->type('time')
                                    ->required()
                                    ->helperText('Horário mínimo para envio'),
                                
                                TextInput::make('whatsapp_timeout_end')
                                    ->label('Horário de Fim')
                                    ->type('time')
                                    ->required()
                                    ->helperText('Horário máximo para envio'),
                            ]),
                        
                        TextInput::make('whatsapp_retry_attempts')
                            ->label('Tentativas de Retry')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->helperText('Número de tentativas em caso de falha'),
                    ]),
                
                Section::make('Teste de Conexão')
                    ->schema([
                        TextInput::make('test_phone')
                            ->label('Número para Teste')
                            ->placeholder('82996211554')
                            ->helperText('Digite apenas números com DDD (ex: 82996211554). Certifique-se que o número tem WhatsApp ativo!')
                            ->mask('99999999999')
                            ->maxLength(11)
                            ->required(),
                        
                        Textarea::make('test_message')
                            ->label('Mensagem de Teste')
                            ->rows(3)
                            ->helperText('Mensagem que será enviada no teste')
                            ->placeholder('Digite sua mensagem de teste aqui...'),
                            
                        Grid::make(1)
                            ->schema([
                                \Filament\Forms\Components\Actions::make([
                                    FormAction::make('send_test')
                                        ->label('Enviar Teste')
                                        ->icon('heroicon-o-paper-airplane')
                                        ->color('success')
                                        ->action('sendTestMessage'),
                                ])->alignCenter(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Salvar configurações usando updateOrCreate para cada configuração
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_enabled'], ['valor' => $data['whatsapp_enabled'] ? '1' : '0', 'tipo' => 'boolean']);
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_server_url'], ['valor' => $data['whatsapp_server_url'], 'tipo' => 'string']);
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_instance'], ['valor' => $data['whatsapp_instance'], 'tipo' => 'string']);
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_api_key'], ['valor' => $data['whatsapp_api_key'], 'tipo' => 'string']);
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_timeout_start'], ['valor' => $data['whatsapp_timeout_start'], 'tipo' => 'string']);
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_timeout_end'], ['valor' => $data['whatsapp_timeout_end'], 'tipo' => 'string']);
        ConfiguracaoSistema::updateOrCreate(['chave' => 'whatsapp_retry_attempts'], ['valor' => (string)$data['whatsapp_retry_attempts'], 'tipo' => 'integer']);

        Notification::make()
            ->title('Configurações salvas!')
            ->body('As configurações do WhatsApp foram salvas com sucesso.')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        $whatsappService = new WhatsAppService();

        if (!$whatsappService->isConfigured()) {
            Notification::make()
                ->title('Configuração Incompleta')
                ->body('Preencha todas as configurações antes de testar.')
                ->warning()
                ->send();
            return;
        }

        $result = $whatsappService->testConnection();

        if ($result['success']) {
            Notification::make()
                ->title('Teste realizado!')
                ->body('Mensagem de teste enviada com sucesso!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Erro no teste')
                ->body($result['error'])
                ->danger()
                ->send();
        }
    }

    public function sendTestMessage(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['test_phone'])) {
            Notification::make()
                ->title('Número Obrigatório')
                ->body('Digite um número para enviar o teste.')
                ->warning()
                ->send();
            return;
        }

        if (empty($data['test_message'])) {
            Notification::make()
                ->title('Mensagem Obrigatória')
                ->body('Digite uma mensagem para enviar no teste.')
                ->warning()
                ->send();
            return;
        }

        $whatsappService = new WhatsAppService();

        if (!$whatsappService->isConfigured()) {
            Notification::make()
                ->title('Configuração Incompleta')
                ->body('Configure o WhatsApp antes de enviar teste.')
                ->warning()
                ->send();
            return;
        }

        $result = $whatsappService->sendTextMessage($data['test_phone'], $data['test_message']);

        if ($result['success']) {
            Notification::make()
                ->title('Mensagem Enviada!')
                ->body("Teste enviado com sucesso para {$data['test_phone']}")
                ->success()
                ->send();
        } else {
            $errorMessage = $result['error'];
            
            // Verificar se é erro de número não existente
            if (strpos($errorMessage, '"exists":false') !== false) {
                $errorMessage = "O número {$data['test_phone']} não está registrado no WhatsApp ou não existe. Verifique se o número está correto e tem WhatsApp ativo.";
            }
            
            Notification::make()
                ->title('Erro no Envio')
                ->body($errorMessage)
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Testar Conexão')
                ->icon('heroicon-o-play')
                ->color('info')
                ->action('testConnection'),
            
            Action::make('save')
                ->label('Salvar')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('save'),
        ];
    }
}