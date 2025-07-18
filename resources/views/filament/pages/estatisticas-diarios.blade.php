<div class="space-y-6">
    <!-- Estatísticas Gerais -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ $stats['total_diarios'] }}
            </div>
            <div class="text-sm text-blue-500 dark:text-blue-300">
                Total de Diários
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ $stats['com_ocorrencias'] }}
            </div>
            <div class="text-sm text-green-500 dark:text-green-300">
                Com Ocorrências
            </div>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                {{ $stats['sem_ocorrencias'] }}
            </div>
            <div class="text-sm text-yellow-500 dark:text-yellow-300">
                Sem Ocorrências
            </div>
        </div>

        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{ number_format($stats['media_ocorrencias'], 1) }}
            </div>
            <div class="text-sm text-purple-500 dark:text-purple-300">
                Média de Ocorrências
            </div>
        </div>
    </div>

    <!-- Distribuição por Estado -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Distribuição por Estado
        </h4>
        
        <div class="space-y-2">
            @foreach($stats['por_estado'] as $estado => $total)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $estado }}</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" 
                                 style="width: {{ ($total / $stats['total_diarios']) * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $total }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Distribuição por Status -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Distribuição por Status
        </h4>
        
        <div class="space-y-2">
            @foreach($stats['por_status'] as $status => $total)
                @php
                    $color = match($status) {
                        'concluido' => 'green',
                        'pendente' => 'yellow',
                        'processando' => 'blue',
                        'erro' => 'red',
                        default => 'gray'
                    };
                @endphp
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400 capitalize">{{ $status }}</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-{{ $color }}-500 h-2 rounded-full" 
                                 style="width: {{ ($total / $stats['total_diarios']) * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $total }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Arquivos PDF -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Arquivos PDF
            </h4>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Com PDF</span>
                    <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                        {{ $stats['com_pdf'] }}
                    </span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Sem PDF</span>
                    <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                        {{ $stats['sem_pdf'] }}
                    </span>
                </div>
                
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Taxa de Upload</span>
                        <span class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                            {{ $stats['total_diarios'] > 0 ? number_format(($stats['com_pdf'] / $stats['total_diarios']) * 100, 1) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Performance
            </h4>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Taxa de Sucesso</span>
                    <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                        {{ $stats['total_diarios'] > 0 ? number_format((($stats['por_status']['concluido'] ?? 0) / $stats['total_diarios']) * 100, 1) : 0 }}%
                    </span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Taxa de Erro</span>
                    <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                        {{ $stats['total_diarios'] > 0 ? number_format((($stats['por_status']['erro'] ?? 0) / $stats['total_diarios']) * 100, 1) : 0 }}%
                    </span>
                </div>
                
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Eficiência</span>
                        <span class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                            {{ $stats['total_diarios'] > 0 ? number_format(($stats['com_ocorrencias'] / $stats['total_diarios']) * 100, 1) : 0 }}%
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Diários que geraram ocorrências
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>