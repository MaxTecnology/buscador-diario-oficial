<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">💡 Dica sobre quebras de linha</h3>
                    <div class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        <p><strong>Para WhatsApp:</strong> Pressione Enter normalmente para quebrar linha. Não use \n - isso não funciona com a Evolution API.</p>
                        <p><strong>Para Email:</strong> Use quebras de linha normais ou HTML se necessário.</p>
                    </div>
                </div>
            </div>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>