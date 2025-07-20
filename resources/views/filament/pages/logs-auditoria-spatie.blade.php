<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">🔍 Filtros</h3>
            {{ $this->form }}
        </div>

        <!-- Estatísticas -->
        @php
            $stats = $this->getStats();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <span class="text-white font-semibold">📊</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['total']) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <span class="text-white font-semibold">📅</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Hoje</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['hoje'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                <span class="text-white font-semibold">📆</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Esta Semana</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['esta_semana'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                <span class="text-white font-semibold">📊</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Este Mês</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['este_mes'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Logs -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">📋 Logs de Auditoria do Sistema</h3>
            </div>
            <div class="overflow-hidden">
                @php
                    $logs = $this->getLogs();
                @endphp
                
                @if($logs->isEmpty())
                    <div class="text-center py-12">
                        <div class="mx-auto h-12 w-12 text-gray-400">
                            📝
                        </div>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum log encontrado</h3>
                        <p class="mt-1 text-sm text-gray-500">Não há logs de auditoria para exibir.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Objeto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($logs as $log)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $eventColors = [
                                                    'created' => 'bg-green-100 text-green-800',
                                                    'updated' => 'bg-blue-100 text-blue-800',
                                                    'deleted' => 'bg-red-100 text-red-800',
                                                ];
                                                $eventIcons = [
                                                    'created' => '➕',
                                                    'updated' => '✏️',
                                                    'deleted' => '🗑️',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $eventColors[$log->event] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $eventIcons[$log->event] ?? '📝' }} {{ ucfirst($log->event) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @if($log->causer)
                                                <div>{{ $log->causer->nome ?? $log->causer->name ?? 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">{{ $log->causer->email ?? '' }}</div>
                                            @else
                                                <span class="text-gray-500">Sistema</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            @if($log->subject_type)
                                                @php
                                                    $modelName = class_basename($log->subject_type);
                                                    $modelNames = [
                                                        'User' => 'Usuário',
                                                        'Empresa' => 'Empresa',
                                                        'Diario' => 'Diário',
                                                        'Ocorrencia' => 'Ocorrência'
                                                    ];
                                                @endphp
                                                <div>{{ $modelNames[$modelName] ?? $modelName }}</div>
                                                @if($log->subject_id)
                                                    <div class="text-xs text-gray-400">ID: {{ $log->subject_id }}</div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            @if($log->description)
                                                {{ $log->description }}
                                            @else
                                                @if($log->properties && isset($log->properties['attributes']))
                                                    <div class="text-xs">
                                                        @foreach($log->properties['attributes'] as $key => $value)
                                                            <div>{{ $key }}: {{ is_string($value) ? Str::limit($value, 30) : $value }}</div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>