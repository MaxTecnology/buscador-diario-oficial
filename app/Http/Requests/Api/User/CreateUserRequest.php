<?php

namespace App\Http\Requests\Api\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'telefone' => 'nullable|string|max:20',
            'pode_fazer_login' => 'boolean',
            'role' => [
                'required',
                'string',
                Rule::in(['admin', 'manager', 'operator', 'viewer', 'notification_only'])
            ],
            'empresas' => 'array',
            'empresas.*.empresa_id' => 'required_with:empresas|exists:empresas,id',
            'empresas.*.pode_ver_ocorrencias' => 'boolean',
            'empresas.*.pode_receber_notificacoes' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'O email deve ter um formato válido.',
            'email.unique' => 'Este email já está em uso.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'role.required' => 'O papel (role) é obrigatório.',
            'role.in' => 'O papel selecionado é inválido.',
            'empresas.*.empresa_id.exists' => 'Uma das empresas selecionadas não existe.',
        ];
    }
}