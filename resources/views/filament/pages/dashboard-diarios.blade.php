<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtros Rápidos de Período -->
        <div class="flex gap-2 mb-6">
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
        </div>

        <!-- Estatísticas Gerais -->
        @php $stats = $this->getEstatisticasGerais(); @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total de Diários -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Diários</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_diarios']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Taxa de sucesso: {{ $stats['taxa_sucesso'] }}%
                        </p>
                    </div>
                </div>
            </div>

            <!-- Diários Concluídos -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Processados</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['concluidos']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($stats['paginas_total']) }} páginas
                        </p>
                    </div>
                </div>
            </div>

            <!-- Ocorrências Encontradas -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Ocorrências</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_ocorrencias']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $stats['diarios_com_ocorrencias'] }} diários com resultados
                        </p>
                    </div>
                </div>
            </div>

            <!-- Volume Processado -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
                        <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Volume</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['tamanho_total_mb'] }} MB</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if($stats['com_erro'] > 0)
                                {{ $stats['com_erro'] }} erro(s)
                            @else
                                Tudo processado
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Estatísticas por Estado -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">📊 Por Estado</h3>
                </div>
                <div class="p-6">
                    @php $estadoStats = $this->getEstatisticasPorEstado(); @endphp
                    @forelse($estadoStats as $estado)
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center space-x-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $estado->estado }}
                                </span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ $estado->total }} diário(s)</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if($estado->concluidos > 0)
                                    <span class="text-xs text-green-600 dark:text-green-400">✓ {{ $estado->concluidos }}</span>
                                @endif
                                @if($estado->erros > 0)
                                    <span class="text-xs text-red-600 dark:text-red-400">✗ {{ $estado->erros }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            Nenhum diário encontrado para o período selecionado.
                        </p>
                    @endforelse
                </div>
            </div>

            <!-- Gráfico de Volume por Dia -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">📈 Últimos 7 Dias</h3>
                </div>
                <div class="p-6">
                    @php $grafico = $this->getGraficoPorDia(); @endphp
                    <div class="space-y-2">
                        @foreach($grafico['labels'] as $index => $label)
                            @php $valor = $grafico['dados'][$index]; @endphp
                            <div class="flex items-center space-x-3">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 w-12">{{ $label }}</span>
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    @php $maxVal = max($grafico['dados']) ?: 1; @endphp
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($valor / $maxVal) * 100 }}%"></div>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white w-8">{{ $valor }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Diários Recentes -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">📄 Diários Recentes</h3>
                    <a href="{{ route('filament.admin.resources.diarios.index') }}" 
                       class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                        Ver todos →
                    </a>
                </div>
            </div>
            <div class="p-6">
                @php $diariosRecentes = $this->getDiariosRecentes(); @endphp
                <div class="space-y-3">
                    @forelse($diariosRecentes as $diario)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    @php
                                        $statusColor = match($diario->status) {
                                            'concluido' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                            'erro' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                            'processando' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                        {{ $diario->estado }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $diario->nome_arquivo }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $diario->created_at->format('d/m/Y H:i') }} • 
                                        @if($diario->total_paginas)
                                            {{ number_format($diario->total_paginas) }} página(s) • 
                                        @endif
                                        {{ number_format(($diario->tamanho_arquivo ?? 0) / 1024 / 1024, 1) }} MB
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                @if($diario->total_ocorrencias > 0)
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $diario->total_ocorrencias }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">ocorrência(s)</div>
                                    </div>
                                @endif
                                
                                @if($diario->ocorrencias_nao_notificadas > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                        {{ $diario->ocorrencias_nao_notificadas }} pendente(s)
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            Nenhum diário encontrado para o período selecionado.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
