<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtros -->
        <div class="flex flex-wrap gap-4 items-center justify-between">
            <!-- Filtros de Período -->
            <div class="flex gap-2">
                <x-filament::button 
                    wire:click="setPeriodo('hoje')" 
                    :color="$periodo === 'hoje' ? 'primary' : 'gray'"
                    size="sm"
                >
                    🗓️ Hoje
                </x-filament::button>
                
                <x-filament::button 
                    wire:click="setPeriodo('esta_semana')" 
                    :color="$periodo === 'esta_semana' ? 'primary' : 'gray'"
                    size="sm"
                >
                    📅 Esta Semana
                </x-filament::button>
                
                <x-filament::button 
                    wire:click="setPeriodo('este_mes')" 
                    :color="$periodo === 'este_mes' ? 'primary' : 'gray'"
                    size="sm"
                >
                    📆 Este Mês
                </x-filament::button>
                
                <x-filament::button 
                    wire:click="setPeriodo('todos')" 
                    :color="$periodo === 'todos' ? 'primary' : 'gray'"
                    size="sm"
                >
                    📋 Todos
                </x-filament::button>
            </div>

            <!-- Filtros Adicionais -->
            <div class="flex gap-2">
                <select wire:model.live="estado" 
                        class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-800 dark:text-white">
                    @foreach($this->getEstadosDisponiveis() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                
                <select wire:model.live="status" 
                        class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-800 dark:text-white">
                    <option value="">Todos os Status</option>
                    <option value="concluido">✅ Processados</option>
                    <option value="erro">❌ Com Erro</option>
                    <option value="processando">⏳ Processando</option>
                    <option value="pendente">⏸️ Pendentes</option>
                </select>
            </div>
        </div>

        <!-- Cards de Diários -->
        @php $diarios = $this->getDiariosCompactos(); @endphp
        
        @if($diarios->isEmpty())
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhum diário encontrado</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Não há diários para os filtros selecionados.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($diarios as $diario)
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <!-- Cabeçalho do Card -->
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate" title="{{ $diario->nome_arquivo }}">
                                        {{ $diario->nome_arquivo }}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $diario->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                
                                @php
                                    $statusColor = match($diario->status) {
                                        'concluido' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        'erro' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                        'processando' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        'pendente' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
                                    };
                                @endphp
                                
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ $diario->estado }}
                                </span>
                            </div>
                        </div>

                        <!-- Conteúdo do Card -->
                        <div class="p-4 space-y-3">
                            <!-- Informações Técnicas -->
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 dark:bg-gray-900 rounded">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        @if($diario->total_paginas)
                                            {{ number_format($diario->total_paginas) }}
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400">páginas</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 dark:bg-gray-900 rounded">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ number_format(($diario->tamanho_arquivo ?? 0) / 1024 / 1024, 1) }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400">MB</div>
                                </div>
                            </div>

                            <!-- Status de Processamento -->
                            <div class="flex items-center justify-center">
                                @if($diario->status === 'concluido')
                                    <div class="flex items-center space-x-1 text-green-600 dark:text-green-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Processado</span>
                                    </div>
                                @elseif($diario->status === 'erro')
                                    <div class="flex items-center space-x-1 text-red-600 dark:text-red-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Erro</span>
                                    </div>
                                @elseif($diario->status === 'processando')
                                    <div class="flex items-center space-x-1 text-yellow-600 dark:text-yellow-400">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Processando</span>
                                    </div>
                                @else
                                    <div class="flex items-center space-x-1 text-gray-600 dark:text-gray-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm7-1a1 1 0 11-2 0 1 1 0 012 0zm-.464 5.535a1 1 0 10-1.415-1.414 3 3 0 01-4.242 0 1 1 0 00-1.415 1.414 5 5 0 007.072 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Pendente</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Ocorrências -->
                            @if($diario->total_ocorrencias > 0)
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-blue-900 dark:text-blue-300">
                                                {{ $diario->total_ocorrencias }} ocorrência(s)
                                            </div>
                                            <div class="text-xs text-blue-700 dark:text-blue-400">
                                                {{ $diario->empresas_detectadas }} empresa(s)
                                            </div>
                                        </div>
                                        @if($diario->ocorrencias_nao_notificadas > 0)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                                {{ $diario->ocorrencias_nao_notificadas }} pendente(s)
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-2 text-xs text-gray-500 dark:text-gray-400">
                                    Nenhuma ocorrência encontrada
                                </div>
                            @endif
                        </div>

                        <!-- Ações -->
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <a href="{{ route('filament.admin.resources.diarios.edit', $diario->id) }}" 
                                   class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                    Ver detalhes
                                </a>
                                
                                <div class="flex items-center space-x-2">
                                    @if($diario->total_ocorrencias > 0)
                                        <a href="{{ route('filament.admin.resources.ocorrencias.index', ['tableFilters' => ['diario.nome_arquivo' => $diario->nome_arquivo]]) }}" 
                                           class="text-xs text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300">
                                            Ocorrências
                                        </a>
                                    @endif
                                    
                                    @if($diario->status === 'erro')
                                        <button wire:click="reprocessarDiario({{ $diario->id }})"
                                                class="text-xs text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-300">
                                            Reprocessar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($diarios->count() >= 50)
                <div class="text-center py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Mostrando os primeiros 50 resultados. Use os filtros para refinar a busca.
                    </p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
