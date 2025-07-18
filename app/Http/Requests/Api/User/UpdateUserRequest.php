<?php

namespace App\Http\Requests\Api\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');
        
        // Admin pode editar qualquer usuário
        if ($this->user()->hasRole('admin')) {
            return true;
        }
        
        // Manager pode editar usuários não-admin
        if ($this->user()->hasRole('manager') && !$user->hasRole('admin')) {
            return true;
        }
        
        // Usuário pode editar apenas seus próprios dados (exceto role)
        return $this->user()->id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->route('user');
        
        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'telefone' => 'sometimes|nullable|string|max:20',
            'pode_fazer_login' => 'sometimes|boolean',
        ];

        // Apenas admin e manager podem alterar role
        if ($this->user()->hasRole(['admin', 'manager'])) {
            $rules['role'] = [
                'sometimes',
                'string',
                Rule::in(['admin', 'manager', 'operator', 'viewer', 'notification_only'])
            ];
        }

        return $rules;
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
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'role.in' => 'O papel selecionado é inválido.',
        ];
    }
}
