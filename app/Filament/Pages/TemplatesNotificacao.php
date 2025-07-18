<?php

namespace App\Filament\Pages;

use App\Models\ConfiguracaoSistema;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;

class TemplatesNotificacao extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static ?string $navigationGroup = 'Sistema';
    
    protected static ?string $title = 'Templates de Notificação';
    
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.templates-notificacao';
    
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'template_email' => ConfiguracaoSistema::get('notificacoes_email_template', 'Nova ocorrência encontrada para a empresa {empresa} no diário {diario}. Score: {score}'),
            'template_whatsapp' => ConfiguracaoSistema::get('notificacoes_whatsapp_template', '🔔 *Nova Ocorrência Encontrada*

📋 Empresa: {empresa}
📄 Diário: {diario}
📊 Score: {score}
📅 Data: {data}'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Template Email')
                    ->description('Configure o template das notificações por email')
                    ->schema([
                        Textarea::make('template_email')
                            ->label('Template de Email')
                            ->rows(4)
                            ->helperText('**Variáveis disponíveis:**
• {empresa} - Nome da empresa
• {diario} - Nome do diário
• {score} - Score de confiança (%)
• {data} - Data e hora')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Template WhatsApp')
                    ->description('Configure o template das notificações por WhatsApp')
                    ->schema([
                        Textarea::make('template_whatsapp')
                            ->label('Template de WhatsApp')
                            ->rows(8)
                            ->helperText('**📝 Como usar:**

• **Quebrar linha:** Pressione Enter normalmente
• **Texto em negrito:** Use *texto* (será negrito no WhatsApp)
• **Emojis:** Use normalmente 🔔 📋 📄 📊 📅

**🔧 Variáveis disponíveis:**
• {empresa} - Nome da empresa
• {diario} - Nome do diário  
• {score} - Score de confiança
• {data} - Data e hora da ocorrência

**✅ Exemplo prático:**
🔔 *Nova Ocorrência*

📋 Empresa: {empresa}
📄 Arquivo: {diario}
📊 Confiança: {score}
📅 Data: {data}')
                            ->placeholder('🔔 *Nova Ocorrência Encontrada*

📋 Empresa: {empresa}
📄 Diário: {diario}
📊 Score: {score}
📅 Data: {data}')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        ConfiguracaoSistema::updateOrCreate(
            ['chave' => 'notificacoes_email_template'],
            ['valor' => $data['template_email'], 'tipo' => 'string', 'categoria' => 'notificacoes']
        );

        ConfiguracaoSistema::updateOrCreate(
            ['chave' => 'notificacoes_whatsapp_template'],
            ['valor' => $data['template_whatsapp'], 'tipo' => 'string', 'categoria' => 'notificacoes']
        );

        Notification::make()
            ->title('Templates Salvos!')
            ->body('Os templates de notificação foram salvos com sucesso.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar Templates')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('save'),
        ];
    }
}
