<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Informações do Sistema -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Informações do Sistema</h3>
            </div>
            <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <strong>Versão do Laravel:</strong> {{ app()->version() }}
                    </div>
                    <div>
                        <strong>Versão do PHP:</strong> {{ PHP_VERSION }}
                    </div>
                    <div>
                        <strong>Ambiente:</strong> {{ app()->environment() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Status do Sistema -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status do Sistema</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">Ativo</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-cpu-chip class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Processamento</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $data['processing_enabled'] ? 'Habilitado' : 'Desabilitado' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-document-text class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Limite de Arquivo</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ number_format($data['storage_max_file_size'] / 1024, 1) }} MB
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário de Configurações -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <form wire:submit.prevent="save">
                {{ $this->form }}
            </form>
        </div>

        <!-- Ações Rápidas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ações Rápidas</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <x-heroicon-o-trash class="w-6 h-6 text-yellow-500" />
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Limpar Cache</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Remove todos os arquivos de cache</p>
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <x-heroicon-o-rocket-launch class="w-6 h-6 text-blue-500" />
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Otimizar Sistema</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Otimiza rotas, configurações e views</p>
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <x-heroicon-o-arrow-path class="w-6 h-6 text-green-500" />
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Reiniciar Filas</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Reinicia os workers das filas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>