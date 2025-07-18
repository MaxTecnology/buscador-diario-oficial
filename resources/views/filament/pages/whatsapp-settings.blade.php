<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Informações da API -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Informações da API</h3>
            </div>
            <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                <p>Configure sua API WhatsApp seguindo o padrão:</p>
                <ul class="mt-1 list-disc list-inside space-y-1">
                    <li><strong>URL:</strong> https://seu-servidor.com/message/sendText/sua-instancia</li>
                    <li><strong>Header:</strong> apikey: sua-chave-api</li>
                    <li><strong>Método:</strong> POST</li>
                </ul>
            </div>
        </div>

        <!-- Exemplo de payload -->
        <div class="bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-code-bracket class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                <h3 class="text-sm font-medium text-gray-800 dark:text-gray-200">Exemplo de Payload</h3>
            </div>
            <div class="mt-2">
                <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded-md overflow-x-auto"><code>{
  "number": "5511999999999",
  "text": "Sua mensagem aqui",
  "options": {
    "delay": 1000,
    "presence": "composing",
    "linkPreview": true
  }
}</code></pre>
            </div>
        </div>

        <!-- Formulário -->
        <form wire:submit.prevent="save">
            {{ $this->form }}
        </form>

        <!-- Instruções de teste -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Como Testar</h3>
            </div>
            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <ol class="list-decimal list-inside space-y-1">
                    <li>Configure a URL do servidor, instância e API key acima</li>
                    <li>Salve as configurações clicando em "Salvar"</li>
                    <li>Na seção "Teste de Conexão", digite um número (ex: 11999999999)</li>
                    <li>Personalize a mensagem de teste se desejar</li>
                    <li>Clique em "Enviar Teste" para enviar a mensagem</li>
                </ol>
            </div>
        </div>

        <!-- Status da configuração -->
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Status da Configuração</h3>
            </div>
            <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                <p>Após configurar, você pode testar o envio de mensagens na seção "Teste de Conexão" abaixo.</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>