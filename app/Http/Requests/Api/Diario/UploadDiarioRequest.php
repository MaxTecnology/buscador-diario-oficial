<?php

namespace App\Http\Requests\Api\Diario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDiarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'manager', 'operator']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'arquivo' => [
                'required',
                'file',
                'mimes:pdf',
                'max:102400', // 100MB
            ],
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
            'data_diario' => 'required|date|before_or_equal:today',
            'observacoes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'arquivo.required' => 'O arquivo PDF é obrigatório.',
            'arquivo.file' => 'Deve ser um arquivo válido.',
            'arquivo.mimes' => 'O arquivo deve ser um PDF.',
            'arquivo.max' => 'O arquivo não pode ser maior que 100MB.',
            'estado.required' => 'O estado é obrigatório.',
            'estado.size' => 'O estado deve ter 2 caracteres (sigla).',
            'estado.in' => 'Estado inválido.',
            'data_diario.required' => 'A data do diário é obrigatória.',
            'data_diario.date' => 'A data do diário deve ser uma data válida.',
            'data_diario.before_or_equal' => 'A data do diário não pode ser futura.',
            'observacoes.max' => 'As observações devem ter no máximo 500 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Converter estado para maiúsculo
        if ($this->estado) {
            $this->merge([
                'estado' => strtoupper($this->estado)
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'arquivo' => 'arquivo PDF',
            'estado' => 'estado',
            'data_diario' => 'data do diário',
            'observacoes' => 'observações',
        ];
    }
}
