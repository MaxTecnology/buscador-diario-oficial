<?php

namespace App\Filament\Pages;

use App\Models\SystemConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class ConfiguracoesSistema extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.configuracoes-sistema';

    protected static ?string $navigationGroup = 'Configurações';
    
    // Desativar esta página duplicada
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Configurações do Sistema';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Configurações gerais
            'app_name' => SystemConfig::getValue('app.name', 'Diário Oficial'),
            'app_description' => SystemConfig::getValue('app.description', 'Sistema de Monitoramento de Diários Oficiais'),
            'app_logo' => SystemConfig::getValue('app.logo', ''),
            'app_timezone' => SystemConfig::getValue('app.timezone', 'America/Sao_Paulo'),
            
            // Configurações de processamento
            'processing_enabled' => SystemConfig::getValue('processing.enabled', true),
            'processing_max_concurrent' => SystemConfig::getValue('processing.max_concurrent', 5),
            'processing_timeout' => SystemConfig::getValue('processing.timeout', 300),
            'processing_retry_attempts' => SystemConfig::getValue('processing.retry_attempts', 3),
            
            // Configurações de storage
            'storage_max_file_size' => SystemConfig::getValue('storage.max_file_size', 10240),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configurações Gerais')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('app_name')
                                    ->label('Nome da Aplicação')
                                    ->required()
                                    ->maxLength(255),
                                
                                TextInput::make('app_timezone')
                                    ->label('Fuso Horário')
                                    ->required()
                                    ->default('America/Sao_Paulo'),
                            ]),
                        
                        Textarea::make('app_description')
                            ->label('Descrição da Aplicação')
                            ->rows(3)
                            ->maxLength(500),
                        
                        FileUpload::make('app_logo')
                            ->label('Logo da Aplicação')
                            ->image()
                            ->maxSize(2048)
                            ->directory('system')
                            ->visibility('public'),
                    ])->columns(1),
                
                Section::make('Configurações de Processamento')
                    ->schema([
                        Toggle::make('processing_enabled')
                            ->label('Processamento Habilitado')
                            ->helperText('Habilita o processamento automático de PDFs'),
                        
                        Grid::make(3)
                            ->schema([
                                TextInput::make('processing_max_concurrent')
                                    ->label('Processos Simultâneos')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->required()
                                    ->helperText('Máximo de PDFs processados ao mesmo tempo'),
                                
                                TextInput::make('processing_timeout')
                                    ->label('Timeout (segundos)')
                                    ->numeric()
                                    ->minValue(60)
                                    ->maxValue(3600)
                                    ->required()
                                    ->helperText('Tempo limite para processar um PDF'),
                                
                                TextInput::make('processing_retry_attempts')
                                    ->label('Tentativas de Retry')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->required()
                                    ->helperText('Tentativas em caso de falha'),
                            ]),
                    ])->columns(1),
                
                Section::make('Configurações de Arquivos')
                    ->schema([
                        TextInput::make('storage_max_file_size')
                            ->label('Tamanho Máximo de Arquivo (KB)')
                            ->numeric()
                            ->minValue(1024)
                            ->maxValue(102400)
                            ->required()
                            ->helperText('Tamanho máximo para upload de PDFs'),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Salvar apenas as configurações essenciais
        $configMap = [
            'app_name' => 'app.name',
            'app_description' => 'app.description',
            'app_logo' => 'app.logo',
            'app_timezone' => 'app.timezone',
            'processing_enabled' => 'processing.enabled',
            'processing_max_concurrent' => 'processing.max_concurrent',
            'processing_timeout' => 'processing.timeout',
            'processing_retry_attempts' => 'processing.retry_attempts',
            'storage_max_file_size' => 'storage.max_file_size',
        ];

        foreach ($configMap as $formKey => $configKey) {
            if (isset($data[$formKey])) {
                SystemConfig::setValue($configKey, $data[$formKey]);
            }
        }

        // Limpar cache de configurações
        Cache::tags(['system_config'])->flush();

        Notification::make()
            ->title('Configurações salvas!')
            ->body('As configurações do sistema foram salvas com sucesso.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('limpar_cache')
                ->label('Limpar Cache')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->action('limparCache')
                ->requiresConfirmation(),
            
            Action::make('otimizar_sistema')
                ->label('Otimizar Sistema')
                ->icon('heroicon-o-rocket-launch')
                ->color('info')
                ->action('otimizarSistema')
                ->requiresConfirmation(),
            
            Action::make('salvar')
                ->label('Salvar')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('save'),
        ];
    }

    public function limparCache(): void
    {
        Cache::flush();
        
        Notification::make()
            ->title('Cache limpo!')
            ->body('O cache do sistema foi limpo com sucesso.')
            ->success()
            ->send();
    }

    public function otimizarSistema(): void
    {
        Artisan::call('optimize:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        
        Notification::make()
            ->title('Sistema otimizado!')
            ->body('O sistema foi otimizado com sucesso.')
            ->success()
            ->send();
    }
}