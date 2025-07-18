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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-newspaper class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Diários</p>
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
                            <x-heroicon-o-check-circle class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Processados</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->where('status', 'concluido')->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-clock class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pendentes</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->where('status', 'pendente')->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-document-magnifying-glass class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Com Ocorrências</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->has('ocorrencias')->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-x-circle class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Com Erro</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->where('status', 'erro')->count() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Estados -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Distribuição por Estado</h3>
            
            @php
                $estadoStats = App\Models\Diario::select('estado')
                    ->selectRaw('COUNT(*) as total')
                    ->groupBy('estado')
                    ->pluck('total', 'estado')
                    ->toArray();
            @endphp
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach($estadoStats as $estado => $total)
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $total }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $estado }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Tabela -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>