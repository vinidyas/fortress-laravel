<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $financePermissions = [
            'financeiro.view',
            'financeiro.create',
            'financeiro.update',
            'financeiro.delete',
            'financeiro.reconcile',
            'financeiro.export',
        ];

        $auditPermissions = [
            'auditoria.view',
            'auditoria.export',
        ];

        $reportPermissions = [
            'reports.view.financeiro',
            'reports.view.operacional',
            'reports.view.pessoas',
            'reports.export',
        ];

        $alertPermissions = [
            'alerts.view',
            'alerts.resolve',
        ];

        $basePermissions = [
            'imoveis.view', 'imoveis.create', 'imoveis.update', 'imoveis.delete',
            'pessoas.view', 'pessoas.create', 'pessoas.update', 'pessoas.delete',
            'condominios.view', 'condominios.create', 'condominios.update', 'condominios.delete',
            'contratos.view', 'contratos.create', 'contratos.update', 'contratos.delete',
            'faturas.view', 'faturas.create', 'faturas.update', 'faturas.delete', 'faturas.settle', 'faturas.cancel', 'faturas.email',
        ];

        $allPermissions = array_unique(array_merge($financePermissions, $auditPermissions, $reportPermissions, $alertPermissions, $basePermissions));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Permission::firstOrCreate([
            'name' => 'admin.access',
            'guard_name' => 'web',
        ]);

        $roles = [
            'admin' => [
                'label' => 'Administrador',
                'description' => 'Acesso completo ao sistema',
                'permissions' => array_values(array_unique(array_merge($allPermissions, ['admin.access']))),
            ],
            'operador' => [
                'label' => 'Operador',
                'description' => 'Operacao de cadastros e contratos',
                'permissions' => [
                    'imoveis.view', 'imoveis.create', 'imoveis.update',
                    'pessoas.view', 'pessoas.create', 'pessoas.update',
                    'condominios.view', 'condominios.create', 'condominios.update',
                    'contratos.view', 'contratos.create', 'contratos.update',
                    'reports.view.operacional', 'reports.view.pessoas',
                ],
            ],
            'financeiro' => [
                'label' => 'Financeiro',
                'description' => 'Gestao financeira e faturamento',
                'permissions' => [
                    'financeiro.view', 'financeiro.create', 'financeiro.update', 'financeiro.reconcile', 'financeiro.export',
                    'faturas.view', 'faturas.create', 'faturas.update', 'faturas.settle', 'faturas.cancel', 'faturas.email',
                    'reports.view.financeiro', 'reports.export',
                    'alerts.view',
                ],
            ],
            'auditor' => [
                'label' => 'Auditor',
                'description' => 'Revisao e auditoria de registros',
                'permissions' => [
                    'financeiro.view',
                    'auditoria.view', 'auditoria.export',
                    'reports.view.financeiro', 'reports.view.operacional',
                    'alerts.view', 'alerts.resolve',
                ],
            ],
        ];

        foreach ($roles as $slug => $data) {
            $role = Role::query()->where('slug', $slug)->first();

            if (! $role) {
                $role = Role::query()->forceCreate([
                    'name' => $data['label'],
                    'guard_name' => 'web',
                    'slug' => $slug,
                    'description' => $data['description'],
                    'is_system' => true,
                ]);
            } else {
                $role->forceFill([
                    'name' => $data['label'],
                    'slug' => $slug,
                    'description' => $data['description'],
                    'is_system' => true,
                ])->save();
            }

            $role->syncPermissions($data['permissions']);
        }
    }
}
