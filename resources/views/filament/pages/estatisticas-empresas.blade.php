<div class="space-y-6">
    <!-- Estatísticas Gerais -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ $stats['total_empresas'] }}
            </div>
            <div class="text-sm text-blue-500 dark:text-blue-300">
                Total de Empresas
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

        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{ $stats['com_usuarios'] }}
            </div>
            <div class="text-sm text-purple-500 dark:text-purple-300">
                Com Usuários
            </div>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                {{ number_format($stats['media_ocorrencias'], 1) }}
            </div>
            <div class="text-sm text-yellow-500 dark:text-yellow-300">
                Média de Ocorrências
            </div>
        </div>
    </div>

    <!-- Distribuição por Criador -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Distribuição por Criador
        </h4>
        
        <div class="space-y-2">
            @foreach($stats['por_criador'] as $criador => $total)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $criador }}</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" 
                                 style="width: {{ ($total / $stats['total_empresas']) * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $total }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Distribuição por Estado -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Distribuição por Estado
        </h4>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($stats['por_estado'] as $estado => $total)
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $total }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $estado }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Análise de Atividade -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Análise de Ocorrências
            </h4>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Com Ocorrências</span>
                    <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                        {{ $stats['com_ocorrencias'] }}
                    </span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Sem Ocorrências</span>
                    <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                        {{ $stats['sem_ocorrencias'] }}
                    </span>
                </div>
                
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Taxa de Atividade</span>
                        <span class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                            {{ $stats['total_empresas'] > 0 ? number_format(($stats['com_ocorrencias'] / $stats['total_empresas']) * 100, 1) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Análise de Usuários
            </h4>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Com Usuários</span>
                    <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                        {{ $stats['com_usuarios'] }}
                    </span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Sem Usuários</span>
                    <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                        {{ $stats['sem_usuarios'] }}
                    </span>
                </div>
                
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Média de Usuários</span>
                        <span class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                            {{ number_format($stats['media_usuarios'], 1) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status das Empresas -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Status das Empresas
        </h4>
        
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Empresas Ativas</span>
                <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                    {{ $stats['ativas'] }}
                </span>
            </div>
            
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Empresas Inativas</span>
                <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                    {{ $stats['inativas'] }}
                </span>
            </div>
            
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Taxa de Atividade</span>
                    <span class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                        {{ $stats['total_empresas'] > 0 ? number_format(($stats['ativas'] / $stats['total_empresas']) * 100, 1) : 0 }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>