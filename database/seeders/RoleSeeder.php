<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar permissões
        $permissions = [
            // Usuários
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            
            // Empresas
            'empresas.view',
            'empresas.create',
            'empresas.update',
            'empresas.delete',
            'empresas.import',
            
            // Diários
            'diarios.view',
            'diarios.upload',
            'diarios.download',
            'diarios.delete',
            'diarios.reprocess',
            
            // Ocorrências
            'ocorrencias.view',
            'ocorrencias.export',
            
            // Configurações
            'configs.view',
            'configs.update',
            
            // Relatórios
            'relatorios.view',
            'relatorios.export',
            
            // Sistema
            'sistema.monitoring',
            'sistema.logs',
            'sistema.health-check',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Criar roles conforme especificação
        
        // Admin: acesso total
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Manager: gerenciamento sem configurações críticas  
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'users.view', 'users.create', 'users.update',
            'empresas.view', 'empresas.create', 'empresas.update', 'empresas.import',
            'diarios.view', 'diarios.upload', 'diarios.download', 'diarios.reprocess',
            'ocorrencias.view', 'ocorrencias.export',
            'relatorios.view', 'relatorios.export',
            'sistema.monitoring', 'sistema.logs',
        ]);

        // Operator: operações do dia a dia
        $operator = Role::firstOrCreate(['name' => 'operator']);
        $operator->syncPermissions([
            'users.view',
            'empresas.view',
            'diarios.view', 'diarios.upload', 'diarios.download',
            'ocorrencias.view',
            'relatorios.view',
        ]);

        // Viewer: apenas visualização
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions([
            'users.view',
            'empresas.view',
            'diarios.view',
            'ocorrencias.view',
            'relatorios.view',
        ]);

        // Notification Only: apenas para receber notificações
        $notificationOnly = Role::firstOrCreate(['name' => 'notification_only']);
        // Sem permissões, apenas para vinculação com empresas para notificações
    }
}
