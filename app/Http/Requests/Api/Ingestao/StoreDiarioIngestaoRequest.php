<?php

namespace App\Http\Requests\Api\Ingestao;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiarioIngestaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $estado = strtoupper((string) $this->input('estado', ''));

        $this->merge([
            'estado' => $estado,
            'sha256' => strtolower((string) $this->input('sha256', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:120'],
            'external_id' => ['required', 'string', 'max:191'],
            'estado' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'data_diario' => ['required', 'date_format:Y-m-d'],
            'nome_arquivo' => ['required', 'string', 'max:255'],
            'bucket' => ['nullable', 'string', 'max:120'],
            'object_key' => ['required', 'string', 'max:1024'],
            'sha256' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'download_url_origem' => ['nullable', 'url', 'max:1024'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'Campo source é obrigatório.',
            'external_id.required' => 'Campo external_id é obrigatório.',
            'estado.regex' => 'Estado deve conter duas letras maiúsculas (UF).',
            'data_diario.date_format' => 'Data do diário deve estar no formato YYYY-MM-DD.',
            'sha256.size' => 'sha256 deve conter 64 caracteres hexadecimais.',
        ];
    }
}

