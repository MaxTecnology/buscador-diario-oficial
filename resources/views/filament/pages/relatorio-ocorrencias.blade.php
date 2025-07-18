<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <form wire:submit.prevent="$refresh">
                {{ $this->form }}
                
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-end space-x-2">
                        <x-filament::button
                            wire:click="$refresh"
                            icon="heroicon-o-arrow-path"
                            color="gray"
                        >
                            Atualizar
                        </x-filament::button>
                        
                        <x-filament::button
                            wire:click="resetFormData"
                            icon="heroicon-o-x-mark"
                            color="danger"
                            outlined
                        >
                            Limpar Filtros
                        </x-filament::button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Estatísticas Rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-document-magnifying-glass class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Ocorrências</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-building-office class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Empresas Envolvidas</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->distinct('empresa_id')->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-star class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Score Médio</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ number_format($this->getTableQuery()->avg('score_confianca') * 100, 1) }}%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-newspaper class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Diários Únicos</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->distinct('diario_id')->count() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>