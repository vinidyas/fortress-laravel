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

        $basePermissions = [
            'imoveis.view', 'imoveis.create', 'imoveis.update', 'imoveis.delete',
            'pessoas.view', 'pessoas.create', 'pessoas.update', 'pessoas.delete',
            'contratos.view', 'contratos.create', 'contratos.update', 'contratos.delete',
            'faturas.view', 'faturas.create', 'faturas.update', 'faturas.delete', 'faturas.settle', 'faturas.cancel',
        ];

        $allPermissions = array_unique(array_merge($financePermissions, $auditPermissions, $reportPermissions, $basePermissions));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'admin' => [
                'label' => 'Administrador',
                'description' => 'Acesso completo ao sistema',
                'permissions' => $allPermissions,
            ],
            'operador' => [
                'label' => 'Operador',
                'description' => 'Operacao de cadastros e contratos',
                'permissions' => [
                    'imoveis.view', 'imoveis.create', 'imoveis.update',
                    'pessoas.view', 'pessoas.create', 'pessoas.update',
                    'contratos.view', 'contratos.create', 'contratos.update',
                    'reports.view.operacional', 'reports.view.pessoas',
                ],
            ],
            'financeiro' => [
                'label' => 'Financeiro',
                'description' => 'Gestao financeira e faturamento',
                'permissions' => [
                    'financeiro.view', 'financeiro.create', 'financeiro.update', 'financeiro.reconcile', 'financeiro.export',
                    'faturas.view', 'faturas.create', 'faturas.update', 'faturas.settle', 'faturas.cancel',
                    'reports.view.financeiro', 'reports.export',
                ],
            ],
            'auditor' => [
                'label' => 'Auditor',
                'description' => 'Revisao e auditoria de registros',
                'permissions' => [
                    'financeiro.view',
                    'auditoria.view', 'auditoria.export',
                    'reports.view.financeiro', 'reports.view.operacional',
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