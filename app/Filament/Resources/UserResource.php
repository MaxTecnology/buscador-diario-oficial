<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Empresa;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use App\Services\NotificationService;
use Filament\Notifications\Notification;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    public static function roleOptions(): array
    {
        return [
            'admin' => 'Admin',
            'manager' => 'Gestor',
            'operator' => 'Usuário',
            'viewer' => 'Consulta',
            'notification_only' => 'Somente notificação',
        ];
    }

    public static function resolvePrimaryRole(?User $record): ?string
    {
        if (! $record) {
            return null;
        }

        $roleNames = $record->roles()->pluck('name');
        $priority = array_keys(static::roleOptions());

        foreach ($priority as $roleName) {
            if ($roleNames->contains($roleName)) {
                return $roleName;
            }
        }

        return $roleNames->first();
    }

    public static function shouldUseAllEmpresas(?User $record): bool
    {
        if (! $record) {
            return false;
        }

        $totalEmpresas = Empresa::query()->count();

        if ($totalEmpresas === 0) {
            return false;
        }

        return $record->empresas()->count() >= $totalEmpresas;
    }

    public static function allEmpresaIds(): array
    {
        return Empresa::query()->orderBy('nome')->pluck('id')->all();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Pessoais')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),
                        Forms\Components\TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('telefone_whatsapp')
                            ->label('Telefone WhatsApp')
                            ->tel()
                            ->maxLength(255)
                            ->helperText('Formato: 11999999999 (apenas números)'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Acesso e Permissões')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->revealable(),
                        Forms\Components\Toggle::make('pode_fazer_login')
                            ->label('Pode fazer login')
                            ->default(true),
                        Forms\Components\Select::make('role_name')
                            ->label('Perfil de Acesso')
                            ->options(static::roleOptions())
                            ->default('operator')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Defina um único perfil principal para o usuário.')
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record): void {
                                $component->state(static::resolvePrimaryRole($record) ?? 'operator');
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === 'notification_only') {
                                    $set('pode_fazer_login', false);
                                }
                            }),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Empresas Vinculadas')
                    ->schema([
                        Forms\Components\Radio::make('empresa_scope')
                            ->label('Escopo de Empresas')
                            ->options([
                                'manual' => 'Selecionar manualmente',
                                'all' => 'Vincular todas as empresas',
                            ])
                            ->default('manual')
                            ->inline()
                            ->inlineLabel(false)
                            ->live()
                            ->afterStateHydrated(function (Forms\Components\Radio $component, ?User $record): void {
                                $component->state(static::shouldUseAllEmpresas($record) ? 'all' : 'manual');
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === 'all') {
                                    $set('empresas', static::allEmpresaIds());
                                }
                            }),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('marcar_todas_empresas')
                                ->label('Marcar todas')
                                ->icon('heroicon-o-check-badge')
                                ->color('success')
                                ->action(function (Set $set): void {
                                    $set('empresas', static::allEmpresaIds());
                                }),
                            Forms\Components\Actions\Action::make('limpar_empresas')
                                ->label('Limpar seleção')
                                ->icon('heroicon-o-x-circle')
                                ->color('gray')
                                ->action(function (Set $set): void {
                                    $set('empresas', []);
                                }),
                        ])
                            ->visible(fn (Get $get): bool => $get('empresa_scope') === 'manual'),
                        Forms\Components\Select::make('empresas')
                            ->label('Empresas')
                            ->options(fn () => Empresa::query()->orderBy('nome')->pluck('nome', 'id')->all())
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Selecione as empresas que este usuário pode acessar.')
                            ->visible(fn (Get $get): bool => $get('empresa_scope') === 'manual')
                            ->required(fn (Get $get): bool => $get('empresa_scope') === 'manual')
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record): void {
                                $component->state($record?->empresas()->pluck('empresas.id')->all() ?? []);
                            }),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('telefone_whatsapp')
                    ->label('WhatsApp')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('aceita_whatsapp')
                    ->label('Aceita WhatsApp')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Papel')
                    ->badge()
                    ->colors([
                        'danger' => 'admin',
                        'warning' => 'manager',
                        'success' => 'operator',
                        'info' => 'notification_only',
                        'gray' => 'viewer',
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('pode_fazer_login')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('empresas_count')
                    ->label('Empresas')
                    ->counts('empresas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Último Login')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Papel')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('pode_fazer_login')
                    ->label('Pode fazer login')
                    ->boolean()
                    ->trueLabel('Sim')
                    ->falseLabel('Não')
                    ->native(false),
                Tables\Filters\Filter::make('created_at')
                    ->label('Criado em')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Criado a partir de'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Criado até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('test_whatsapp')
                    ->label('Testar WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn ($record) => $record->telefone_whatsapp && $record->aceita_whatsapp)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $notificationService = new NotificationService();
                        $result = $notificationService->testWhatsAppForUser($record);
                        
                        if ($result['success']) {
                            Notification::make()
                                ->title('WhatsApp Enviado!')
                                ->body("Mensagem de teste enviada para {$record->name}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Erro no WhatsApp')
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EmpresasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
