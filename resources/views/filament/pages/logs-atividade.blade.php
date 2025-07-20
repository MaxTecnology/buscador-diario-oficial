<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Estatísticas Rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Atividades</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $estatisticas['atividades']['total'] ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Últimos {{ $estatisticas['periodo']['dias'] ?? 0 }} dias
                        </p>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-full">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Notificações Enviadas</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ $estatisticas['notificacoes']['total'] ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Email + WhatsApp
                        </p>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-full">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Diários Processados</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {{ $estatisticas['atividades']['por_acao']['processed'] ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            PDFs analisados
                        </p>
                    </div>
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/20 rounded-full">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Usuários Ativos</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                            {{ count($estatisticas['atividades']['por_usuario'] ?? []) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Com atividade
                        </p>
                    </div>
                    <div class="p-3 bg-orange-100 dark:bg-orange-900/20 rounded-full">
                        <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">🔍 Filtros</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Usuário:</label>
                    <select wire:model.live="filtroUsuario" class="mt-1 w-full border-gray-300 dark:border-gray-600 rounded-md text-sm">
                        @foreach($this->getUsuariosOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Ação:</label>
                    <select wire:model.live="filtroAcao" class="mt-1 w-full border-gray-300 dark:border-gray-600 rounded-md text-sm">
                        @foreach($this->getAcoesOptions() as $value => $label)
                            <option value="{{ $value }}">{{ ucfirst($label) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Entidade:</label>
                    <select wire:model.live="filtroEntidade" class="mt-1 w-full border-gray-300 dark:border-gray-600 rounded-md text-sm">
                        @foreach($this->getEntidadesOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Data Início:</label>
                    <input type="date" wire:model.live="filtroDataInicio" class="mt-1 w-full border-gray-300 dark:border-gray-600 rounded-md text-sm">
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Data Fim:</label>
                    <input type="date" wire:model.live="filtroDataFim" class="mt-1 w-full border-gray-300 dark:border-gray-600 rounded-md text-sm">
                </div>
            </div>
            
            <div class="mt-4 flex gap-2">
                <button wire:click="filtrar" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                    🔍 Aplicar Filtros
                </button>
                <button wire:click="exportarRelatorio" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm">
                    📊 Exportar CSV
                </button>
            </div>
        </div>

        <!-- Timeline de Atividades -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">📋 Timeline de Atividades</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Mostrando {{ count($atividades) }} atividades mais recentes
                </p>
            </div>
            
            <div class="max-h-96 overflow-y-auto">
                @if($atividades->isEmpty())
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhuma atividade encontrada</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Não há atividades para os filtros selecionados.</p>
                    </div>
                @else
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            @foreach($atividades as $index => $atividade)
                                <li>
                                    <div class="relative pb-8">
                                        @if(!$loop->last)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-600" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-800 {{ 
                                                    $atividade->color === 'green' ? 'bg-green-500' : 
                                                    ($atividade->color === 'red' ? 'bg-red-500' : 
                                                    ($atividade->color === 'blue' ? 'bg-blue-500' : 
                                                    ($atividade->color === 'purple' ? 'bg-purple-500' : 
                                                    ($atividade->color === 'orange' ? 'bg-orange-500' : 'bg-gray-500'))))
                                                }}">
                                                    @if($atividade->icon)
                                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @switch($atividade->icon)
                                                                @case('heroicon-o-document-plus')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                    @break
                                                                @case('heroicon-o-trash')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                    @break
                                                                @case('heroicon-o-cog-6-tooth')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                                    @break
                                                                @default
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            @endswitch
                                                        </svg>
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-900 dark:text-white font-medium">
                                                        {{ $atividade->description }}
                                                    </p>
                                                    <div class="mt-1 flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                                        <span class="font-medium">{{ $atividade->user_name }}</span>
                                                        <span>•</span>
                                                        <span>{{ $atividade->ip_address }}</span>
                                                        <span>•</span>
                                                        <span class="uppercase">{{ $atividade->action }}</span>
                                                        @if($atividade->entity_type)
                                                            <span>•</span>
                                                            <span>{{ $atividade->entity_type }}</span>
                                                        @endif
                                                    </div>
                                                    
                                                    @if($atividade->context || $atividade->old_values || $atividade->new_values)
                                                        <div class="mt-2">
                                                            <details class="text-xs">
                                                                <summary class="cursor-pointer text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                                                    Ver detalhes
                                                                </summary>
                                                                <div class="mt-1 bg-gray-50 dark:bg-gray-900 p-2 rounded text-gray-700 dark:text-gray-300">
                                                                    @if($atividade->context)
                                                                        <div class="mb-2">
                                                                            <strong>Contexto:</strong>
                                                                            @foreach($atividade->context as $key => $value)
                                                                                <div>{{ $key }}: {{ is_array($value) ? json_encode($value) : $value }}</div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                    
                                                                    @if($atividade->old_values)
                                                                        <div class="mb-2">
                                                                            <strong>Valores anteriores:</strong>
                                                                            @foreach($atividade->old_values as $key => $value)
                                                                                <div>{{ $key }}: {{ is_array($value) ? json_encode($value) : $value }}</div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                    
                                                                    @if($atividade->new_values)
                                                                        <div>
                                                                            <strong>Novos valores:</strong>
                                                                            @foreach($atividade->new_values as $key => $value)
                                                                                <div>{{ $key }}: {{ is_array($value) ? json_encode($value) : $value }}</div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </details>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    <div>{{ $atividade->tempo_relativo }}</div>
                                                    <div class="text-xs">{{ $atividade->data_formatada }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <!-- Atividades por Usuário -->
        @if(!empty($estatisticas['atividades']['por_usuario']))
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">👥 Atividades por Usuário</h3>
                </div>
                
                <div class="p-4">
                    <div class="space-y-3">
                        @foreach($estatisticas['atividades']['por_usuario'] as $usuario => $total)
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $usuario }}</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($total / max($estatisticas['atividades']['por_usuario'])) * 100 }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400 w-8 text-right">{{ $total }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>