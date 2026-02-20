<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Instruções -->
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">📋 Formato do CSV</h3>
                    <div class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        <p><strong>Formato:</strong> CNPJ;RAZAO_SOCIAL;NOME_FANTASIA;INSCRICAO_ESTADUAL;OBSERVACOES</p>
                        <p><strong>Separador:</strong> Ponto e vírgula (;)</p>
                        <p><strong>Obrigatório:</strong> CNPJ/CPF e Razão Social</p>
                        <p><strong>CNPJ/CPF:</strong> Apenas números (11 ou 14 dígitos)</p>
                        <p><strong>Nome Fantasia:</strong> Será usado como termo de busca nos diários (recomendado preencher)</p>
                        <p><strong>Inscrição Estadual:</strong> Use "0" ou vazio se não houver</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exemplo -->
        <div class="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">💡 Exemplo de CSV:</h4>
            <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded border text-gray-700 dark:text-gray-300">00000000000123;EMPRESA FICTICIA UM;EMPRESA FICTICIA UM;0;;
12345678000199;SUPERMERCADO EXEMPLO LTDA;SUPERMERCADO EXEMPLO;100200300;;
98765432000111;LOJA DEMO LTDA;LOJA DEMO;200300400;;
</pre>
        </div>

        <!-- Formulário -->
        {{ $this->form }}

        <!-- Dicas -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-700">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">⚠️ Observações Importantes</h3>
                    <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                        <ul class="list-disc list-inside space-y-1">
                            <li>CNPJ/CPF duplicado será atualizado (não cria registro novo)</li>
                            <li><strong>Nome Fantasia será adicionado como "Termo de Busca Personalizado"</strong></li>
                            <li>Variações do nome fantasia (sem LTDA, ME, etc.) também serão adicionadas automaticamente</li>
                            <li>Inscrição Estadual com "0" ou vazia é tratada como nula</li>
                            <li>Todas as empresas serão criadas com prioridade "média" e score mínimo 85%</li>
                            <li>Máximo 10MB por arquivo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
