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
                            <x-heroicon-o-building-office class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Empresas</p>
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
                        <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-users class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Com Usuários</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->getTableQuery()->has('users')->count() }}
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
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Média Ocorrências</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            @php
                                $media = $this->getTableQuery()->withCount('ocorrencias')->get()->avg('ocorrencias_count');
                            @endphp
                            {{ number_format($media ?? 0, 1) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Empresas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Top 10 Empresas por Ocorrências</h3>
            
            @php
                $topEmpresas = $this->getTableQuery()
                    ->withCount('ocorrencias')
                    ->orderBy('ocorrencias_count', 'desc')
                    ->limit(10)
                    ->get();
            @endphp
            
            <div class="space-y-3">
                @foreach($topEmpresas as $empresa)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                        <div class="flex-1">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $empresa->nome }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $empresa->cnpj }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                {{ $empresa->ocorrencias_count }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">ocorrências</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Gráfico de Criadores -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Empresas por Criador</h3>
            
            @php
                $criadorStats = \App\Models\Empresa::query()
                    ->join('users', 'empresas.created_by', '=', 'users.id')
                    ->groupBy('empresas.created_by', 'users.name')
                    ->selectRaw('users.name, COUNT(empresas.id) as total')
                    ->pluck('total', 'name')
                    ->toArray();
            @endphp
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($criadorStats as $criador => $total)
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $total }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $criador }}</div>
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