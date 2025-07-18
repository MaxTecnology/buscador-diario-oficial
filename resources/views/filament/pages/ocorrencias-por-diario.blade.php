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
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Ocorrências</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_ocorrencias']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Empresas Detectadas</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['empresas_unicas']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5-5-5h5v-5a7.5 7.5 0 103 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Não Notificadas</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['nao_notificadas']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900 rounded-lg">
                        <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Alta Confiança</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['alta_confianca']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Diários com Ocorrências -->
        <div class="space-y-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Diários Processados</h3>
            
            @forelse($this->getDiariosComOcorrencias() as $diario)
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Cabeçalho do Diário -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ $diario->nome_arquivo }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $diario->estado }} • {{ $diario->data_diario?->format('d/m/Y') }} • {{ $diario->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $diario->total_ocorrencias }} ocorrência(s)</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $diario->empresas_detectadas }} empresa(s)</div>
                                </div>
                                
                                @if($diario->nao_notificadas > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                        {{ $diario->nao_notificadas }} não notificada(s)
                                    </span>
                                @endif
                                
                                <div class="text-right">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Score Médio</div>
                                    <div class="text-sm font-medium">
                                        @php
                                            $scoreMedio = $diario->score_medio;
                                            $cor = $scoreMedio >= 0.95 ? 'text-green-600 dark:text-green-400' : 
                                                   ($scoreMedio >= 0.85 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400');
                                        @endphp
                                        <span class="{{ $cor }}">{{ number_format($scoreMedio * 100, 1) }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ocorrências do Diário -->
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($diario->ocorrencias->take(6) as $ocorrencia)
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {{ $ocorrencia->empresa->nome }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                CNPJ: {{ $ocorrencia->cnpj ? 
                                                    (function($cnpj) {
                                                        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
                                                        if (strlen($cnpj) === 14) {
                                                            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
                                                        }
                                                        return $cnpj;
                                                    })($ocorrencia->cnpj) : '-' }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ ucfirst(str_replace('_', ' ', $ocorrencia->tipo_match)) }}
                                            </p>
                                        </div>
                                        <div class="flex flex-col items-end space-y-1">
                                            @php
                                                $score = $ocorrencia->score_confianca;
                                                $corScore = $score >= 0.95 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 
                                                           ($score >= 0.85 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : 
                                                           'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300');
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $corScore }}">
                                                {{ number_format($score * 100, 1) }}%
                                            </span>
                                            
                                            <div class="flex space-x-1">
                                                @if($ocorrencia->notificado_email)
                                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                                    </svg>
                                                @endif
                                                
                                                @if($ocorrencia->notificado_whatsapp)
                                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                                                    </svg>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @if($diario->ocorrencias->count() > 6)
                            <div class="mt-4 text-center">
                                <a href="{{ route('filament.admin.resources.ocorrencias.index', ['tableFilters' => ['diario.nome_arquivo' => $diario->nome_arquivo]]) }}" 
                                   class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                    Ver todas as {{ $diario->ocorrencias->count() }} ocorrências →
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhum diário encontrado</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Não há diários com ocorrências para o período selecionado.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
