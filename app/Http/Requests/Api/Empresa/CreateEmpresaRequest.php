<?php

namespace App\Http\Requests\Api\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'manager']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'cnpj' => 'nullable|string|size:14|unique:empresas,cnpj',
            'razao_social' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'estado' => [
                'required',
                'string',
                'size:2',
                Rule::in([
                    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 
                    'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 
                    'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
                ])
            ],
            'cidade' => 'nullable|string|max:100',
            'email_notificacao' => 'nullable|email|max:255',
            'telefone_notificacao' => 'nullable|string|max:20',
            'webhook_url' => 'nullable|url|max:500',
            'termos_busca' => 'nullable|array',
            'termos_busca.*' => 'string|max:100',
            'ativo' => 'boolean',
            'observacoes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da empresa é obrigatório.',
            'cnpj.size' => 'O CNPJ deve ter exatamente 14 dígitos.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'estado.required' => 'O estado é obrigatório.',
            'estado.size' => 'O estado deve ter 2 caracteres (sigla).',
            'estado.in' => 'Estado inválido.',
            'email_notificacao.email' => 'O email de notificação deve ter um formato válido.',
            'webhook_url.url' => 'A URL do webhook deve ser válida.',
            'termos_busca.array' => 'Os termos de busca devem ser uma lista.',
            'termos_busca.*.string' => 'Cada termo de busca deve ser texto.',
            'termos_busca.*.max' => 'Cada termo de busca deve ter no máximo 100 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpar CNPJ removendo caracteres especiais
        if ($this->cnpj) {
            $this->merge([
                'cnpj' => preg_replace('/[^0-9]/', '', $this->cnpj)
            ]);
        }

        // Garantir que ativo seja boolean
        if ($this->has('ativo')) {
            $this->merge([
                'ativo' => $this->boolean('ativo')
            ]);
        }

        // Converter estado para maiúsculo
        if ($this->estado) {
            $this->merge([
                'estado' => strtoupper($this->estado)
            ]);
        }
    }
}