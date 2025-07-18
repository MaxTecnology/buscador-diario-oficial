<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@diario.com'],
            [
                'name' => 'Administrador do Sistema',
                'email' => 'admin@diario.com',
                'password' => Hash::make('admin123'),
                'telefone' => '(11) 99999-9999',
                'telefone_whatsapp' => '11999999999',
                'aceita_whatsapp' => true,
                'pode_fazer_login' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        // Criar usuário manager de exemplo
        $manager = User::updateOrCreate(
            ['email' => 'manager@diario.com'],
            [
                'name' => 'Gerente do Sistema',
                'email' => 'manager@diario.com',
                'password' => Hash::make('manager123'),
                'telefone' => '(11) 88888-8888',
                'telefone_whatsapp' => '11888888888',
                'aceita_whatsapp' => true,
                'pode_fazer_login' => true,
                'created_by' => $admin->id,
                'email_verified_at' => now(),
            ]
        );

        $manager->assignRole('manager');

        // Criar usuário operator de exemplo
        $operator = User::updateOrCreate(
            ['email' => 'operator@diario.com'],
            [
                'name' => 'Operador do Sistema',
                'email' => 'operator@diario.com',
                'password' => Hash::make('operator123'),
                'telefone' => '(11) 77777-7777',
                'telefone_whatsapp' => '11777777777',
                'aceita_whatsapp' => true,
                'pode_fazer_login' => true,
                'created_by' => $admin->id,
                'email_verified_at' => now(),
            ]
        );

        $operator->assignRole('operator');
    }
}
