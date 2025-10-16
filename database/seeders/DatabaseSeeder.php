<?php

namespace Database\Seeders;

use App\Models\Condominio;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        $adminPassword = env('SEED_ADMIN_PASSWORD', 'secret123');

        $allPermissions = Permission::pluck('name')->all();

        $adminRole = Role::query()->where('slug', 'admin')->first();

        $admin = User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'nome' => 'Administrador',
                'password' => Hash::make($adminPassword),
                'permissoes' => $allPermissions,
                'ativo' => true,
            ]
        );

        if ($adminRole) {
            $admin->syncRoles($adminRole);
        }

        $admin->syncPermissions($allPermissions);

        $seedSampleData = filter_var(env('SEED_SAMPLE_DATA', true), FILTER_VALIDATE_BOOLEAN);

        if (! $seedSampleData) {
            $this->command?->info('SEED_SAMPLE_DATA=false: dados de demonstracao nao foram gerados.');

            return;
        }

        $pessoas = Pessoa::factory()->count(20)->create();
        $condominios = Condominio::factory()->count(5)->create();

        Imovel::factory()->count(15)->state(function () use ($pessoas, $condominios) {
            return [
                'proprietario_id' => $pessoas->random()->id,
                'agenciador_id' => $pessoas->random()->id,
                'responsavel_id' => $pessoas->random()->id,
                'condominio_id' => $condominios->random()->id,
            ];
        })->create();

        $contratos = Contrato::factory()
            ->count(10)
            ->state(fn () => [
                'imovel_id' => Imovel::query()->inRandomOrder()->value('id'),
                'locador_id' => $pessoas->random()->id,
                'locatario_id' => $pessoas->random()->id,
            ])
            ->create();

        $contratos->each(function (Contrato $contrato) use ($pessoas) {
            $fiadorCount = rand(0, 2);
            if ($fiadorCount > 0) {
                $contrato->fiadores()->sync(
                    collect($pessoas->random($fiadorCount))->pluck('id')->all()
                );
            }

            for ($i = 0; $i < 3; $i++) {
                $competencia = Carbon::now()->subMonths($i)->startOfMonth();

                $fatura = Fatura::factory()
                    ->for($contrato)
                    ->state([
                        'competencia' => $competencia->toDateString(),
                        'vencimento' => $competencia->clone()->setDay(min(28, (int) ($contrato->dia_vencimento ?? 10)))->toDateString(),
                    ])
                    ->create();

                FaturaLancamento::factory()->count(2)->for($fatura, 'fatura')->create();
            }
        });
    }
}
