<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\PaymentSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
        {--dry-run : Apenas valida conexoes e exibe estatisticas}
        {--truncate : Limpa as tabelas de destino antes de importar}
        {--chunk=200 : Quantidade de registros por lote}
        {--financeiro : Importa dados financeiros}
        {--auditoria : Importa logs de auditoria}
        {--relatorios : Reservado para futuros dados analiticos}';

    protected $description = 'Importa dados do sistema legado fortressimob para o novo schema.';

    private ConnectionInterface $legacy;

    private bool $dryRun = false;

    /**
     * @var array<string, array<int, int>>
     */
    private array $crosswalk = [
        'usuarios' => [],
        'pessoas' => [],
        'condominios' => [],
        'imoveis' => [],
        'contratos' => [],
        'faturas' => [],
        'financial_accounts' => [],
        'cost_centers' => [],
    ];

    public function handle(): int
    {
        $connectionName = 'legacy';
        $config = config("database.connections.{$connectionName}");

        if (! $config) {
            $this->error('Conexao legacy nao configurada. Verifique as variaveis LEGACY_DB_* no .env.');

            return self::FAILURE;
        }

        try {
            $this->legacy = DB::connection($connectionName);
            $this->legacy->getPdo();
        } catch (Throwable $exception) {
            $this->error('Nao foi possivel conectar ao banco legado: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Conexao legacy OK (%s@%s/%s)',
            $config['username'] ?? '?',
            $config['host'] ?? 'localhost',
            $config['database'] ?? 'database'
        ));

        $this->dryRun = (bool) $this->option('dry-run');

        $this->renderResumoLegado();

        if ($this->dryRun) {
            $this->comment('Execucao interrompida em modo dry-run. Nenhum dado foi importado.');

            return self::SUCCESS;
        }

        if ($this->option('truncate')) {
            $this->truncateDestino();
        }

        $chunk = (int) $this->option('chunk');
        $modulesSelected = $this->option('financeiro') || $this->option('auditoria') || $this->option('relatorios');

        $summary = [
            'roles' => $this->importRoles(),
            'usuarios' => $this->importUsuarios($chunk),
            'pessoas' => $this->importPessoas($chunk),
            'condominios' => $this->importCondominios($chunk),
            'imoveis' => $this->importImoveis($chunk),
            'contratos' => $this->importContratos($chunk),
            'faturas' => $this->importFaturas($chunk),
            'fatura_lancamentos' => $this->importFaturaLancamentos($chunk),
        ];

        if ($this->option('financeiro') || ! $modulesSelected) {
            $summary['financial_accounts'] = $this->importContasFinanceiras($chunk);
            $summary['cost_centers'] = $this->importCentrosCusto($chunk);
            $summary['financial_transactions'] = $this->importLancamentosFinanceiros($chunk);
            $summary['payment_schedules'] = $this->importAgendamentosFinanceiros($chunk);
        }

        if ($this->option('auditoria') || ! $modulesSelected) {
            $summary['audit_logs'] = $this->importAuditLogs($chunk);
        }

        if ($this->option('relatorios')) {
            $summary['relatorios'] = 0;
        }

        $this->table(
            ['Entidade', 'Registros processados'],
            collect($summary)->map(fn ($count, $entity) => [Str::title(str_replace('_', ' ', $entity)), $count])
        );

        $this->info('Importacao concluida.');

        return self::SUCCESS;
    }

    private function renderResumoLegado(): void
    {
        $tabelas = [
            'roles',
            'usuarios',
            'pessoas',
            'condominios',
            'imoveis',
            'contratos',
            'faturas',
            'fatura_lancamentos',
            'contas_financeiras',
            'centros_custo',
            'lancamentos_financeiros',
            'agendamentos_pagamento',
            'audit_logs',
        ];

        $rows = [];
        foreach ($tabelas as $tabela) {
            try {
                $rows[] = [$tabela, $this->legacy->table($tabela)->count()];
            } catch (Throwable $exception) {
                $rows[] = [$tabela, 'erro: '.$exception->getMessage()];
            }
        }

        $this->table(['Tabela', 'Registros (legacy)'], $rows);
    }

    private function truncateDestino(): void
    {
        $this->warn('Truncando tabelas de destino...');

        $tables = [
            'role_has_permissions',
            'model_has_permissions',
            'model_has_roles',
            'permissions',
            'audit_logs',
            'financial_transactions',
            'payment_schedules',
            'cost_centers',
            'financial_accounts',
            'fatura_lancamentos',
            'faturas',
            'contratos',
            'imoveis',
            'condominios',
            'pessoas',
            'usuarios',
            'roles',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function importRoles(): int
    {
        $rows = $this->legacy->table('roles')->orderBy('id')->get();
        $count = 0;

        foreach ($rows as $row) {
            $payload = [
                'name' => $this->get($row, ['nome', 'name'], 'Cargo'),
                'slug' => $this->get($row, ['slug'], Str::slug($this->get($row, ['nome', 'name'], 'cargo'))),
                'description' => $this->get($row, ['descricao', 'description']),
                'is_system' => (bool) $this->get($row, ['is_system'], false),
                'guard_name' => 'web',
            ];

            $permissions = $this->decodeJson($this->get($row, ['permissoes', 'permissions'], '[]'));

            if ($this->dryRun) {
                $this->line('[dry-run] Role: '.json_encode($payload));
                $count++;

                continue;
            }

            $role = Role::query()->firstOrCreate(['slug' => $payload['slug']], $payload);
            $role->forceFill($payload)->save();

            if ($permissions !== []) {
                foreach ($permissions as $permission) {
                    Permission::firstOrCreate([
                        'name' => $permission,
                        'guard_name' => 'web',
                    ]);
                }
                $role->syncPermissions($permissions);
            }

            $count++;
        }

        return $count;
    }

    private function importUsuarios(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('usuarios')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'username' => (string) $this->get($row, ['username', 'login']),
                    'nome' => (string) $this->get($row, ['nome', 'name'], 'Usuario'),
                    'password' => Hash::make($this->get($row, ['senha', 'password'], Str::random(12))),
                    'permissoes' => $this->decodeJson($this->get($row, ['permissoes', 'permissions'], '[]')),
                    'ativo' => (bool) $this->get($row, ['ativo', 'is_active'], true),
                    'last_login_at' => $this->parseDateTime($this->get($row, ['ultimo_login', 'last_login_at'])),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] Usuario: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $user = User::query()->updateOrCreate(['username' => $payload['username']], $payload);
                $this->crosswalk['usuarios'][$row->id] = $user->id;

                if (! empty($payload['permissoes'])) {
                    $user->syncPermissions($payload['permissoes']);
                }

                $count++;
            }
        });

        return $count;
    }

    private function importPessoas(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('pessoas')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'nome_razao_social' => $this->get($row, ['nome', 'nome_razao_social'], 'Pessoa'),
                    'cpf_cnpj' => $this->normalizeCpf($this->get($row, ['cpf', 'cpf_cnpj'])),
                    'email' => $this->get($row, ['email']),
                    'telefone' => $this->get($row, ['telefone', 'phone']),
                    'tipo_pessoa' => $this->mapTipoPessoa($this->get($row, ['tipo', 'tipo_pessoa'])),
                    'papeis' => $this->decodeCsv($this->get($row, ['papeis', 'roles'])),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] Pessoa: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $pessoa = \App\Models\Pessoa::query()->updateOrCreate(
                    ['cpf_cnpj' => $payload['cpf_cnpj']],
                    $payload
                );

                $this->crosswalk['pessoas'][$row->id] = $pessoa->id;
                $count++;
            }
        });

        return $count;
    }

    private function importCondominios(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('condominios')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'nome' => $this->get($row, ['nome'], 'Condominio'),
                    'cnpj' => $this->normalizeCpf($this->get($row, ['cnpj'])),
                    'cep' => $this->get($row, ['cep']),
                    'estado' => $this->get($row, ['estado', 'uf']),
                    'cidade' => $this->get($row, ['cidade']),
                    'bairro' => $this->get($row, ['bairro']),
                    'rua' => $this->get($row, ['rua', 'logradouro']),
                    'numero' => $this->get($row, ['numero']),
                    'complemento' => $this->get($row, ['complemento']),
                    'telefone' => $this->get($row, ['telefone']),
                    'email' => $this->get($row, ['email']),
                    'observacoes' => $this->get($row, ['observacoes', 'obs']),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] Condominio: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $condominio = \App\Models\Condominio::query()->updateOrCreate(
                    ['nome' => $payload['nome']],
                    $payload
                );

                $this->crosswalk['condominios'][$row->id] = $condominio->id;
                $count++;
            }
        });

        return $count;
    }

    private function importImoveis(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('imoveis')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'codigo' => $this->get($row, ['codigo', 'id']) ?: 'IMV-'.$row->id,
                    'proprietario_id' => $this->crosswalk['pessoas'][$this->get($row, ['proprietario_id'], 0)] ?? null,
                    'agenciador_id' => $this->crosswalk['pessoas'][$this->get($row, ['agenciador_id'], 0)] ?? null,
                    'responsavel_id' => $this->crosswalk['pessoas'][$this->get($row, ['responsavel_id'], 0)] ?? null,
                    'tipo_imovel' => $this->get($row, ['tipo', 'tipo_imovel'], 'Nao informado'),
                    'finalidade' => $this->decodeCsv($this->get($row, ['finalidade'])),
                    'disponibilidade' => $this->mapDisponibilidade($this->get($row, ['disponibilidade', 'status'])),
                    'cep' => $this->get($row, ['cep']),
                    'estado' => $this->get($row, ['estado', 'uf']),
                    'cidade' => $this->get($row, ['cidade']),
                    'bairro' => $this->get($row, ['bairro']),
                    'rua' => $this->get($row, ['rua', 'logradouro']),
                    'condominio_id' => $this->crosswalk['condominios'][$this->get($row, ['condominio_id'], 0)] ?? null,
                    'logradouro' => $this->get($row, ['logradouro']),
                    'numero' => (string) $this->get($row, ['numero'], 's/n'),
                    'complemento' => $this->get($row, ['complemento']),
                    'valor_locacao' => $this->parseDecimal($this->get($row, ['valor_locacao'])),
                    'valor_condominio' => $this->parseDecimal($this->get($row, ['valor_condominio'])),
                    'condominio_isento' => (bool) $this->get($row, ['condominio_isento'], false),
                    'valor_iptu' => $this->parseDecimal($this->get($row, ['valor_iptu'])),
                    'iptu_isento' => (bool) $this->get($row, ['iptu_isento'], false),
                    'outros_valores' => $this->parseDecimal($this->get($row, ['outros_valores'])),
                    'outros_isento' => (bool) $this->get($row, ['outros_isento'], false),
                    'periodo_iptu' => $this->mapPeriodoIptu($this->get($row, ['periodo_iptu'], 'Mensal')),
                    'dormitorios' => $this->get($row, ['dormitorios']),
                    'suites' => $this->get($row, ['suites']),
                    'banheiros' => $this->get($row, ['banheiros']),
                    'vagas_garagem' => $this->get($row, ['vagas_garagem']),
                    'area_total' => $this->parseDecimal($this->get($row, ['area_total'])),
                    'area_construida' => $this->parseDecimal($this->get($row, ['area_construida'])),
                    'comodidades' => $this->decodeCsv($this->get($row, ['comodidades', 'amenities'])),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] Imovel: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $imovel = \App\Models\Imovel::query()->updateOrCreate(
                    ['codigo' => $payload['codigo']],
                    $payload
                );

                $this->crosswalk['imoveis'][$row->id] = $imovel->id;
                $count++;
            }
        });

        return $count;
    }

    private function importContratos(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('contratos')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'codigo_contrato' => $this->get($row, ['codigo_contrato', 'codigo'], 'CTR-'.$row->id),
                    'imovel_id' => $this->crosswalk['imoveis'][$this->get($row, ['imovel_id'], 0)] ?? null,
                    'locador_id' => $this->crosswalk['pessoas'][$this->get($row, ['locador_id'], 0)] ?? null,
                    'locatario_id' => $this->crosswalk['pessoas'][$this->get($row, ['locatario_id'], 0)] ?? null,
                    'data_inicio' => $this->parseDate($this->get($row, ['data_inicio'])),
                    'data_fim' => $this->parseDate($this->get($row, ['data_fim'])),
                    'dia_vencimento' => (int) $this->get($row, ['dia_vencimento'], 5),
                    'valor_aluguel' => $this->parseDecimal($this->get($row, ['valor_aluguel', 'valor'])),
                    'reajuste_indice' => $this->get($row, ['reajuste_indice'], 'IGPM'),
                    'data_proximo_reajuste' => $this->parseDate($this->get($row, ['proximo_reajuste', 'data_proximo_reajuste'])),
                    'garantia_tipo' => $this->mapGarantia($this->get($row, ['garantia_tipo', 'garantia'])),
                    'caucao_valor' => $this->parseDecimal($this->get($row, ['caucao_valor'])),
                    'taxa_adm_percentual' => $this->parseDecimal($this->get($row, ['taxa_adm_percentual'])),
                    'status' => $this->mapStatusContrato($this->get($row, ['status'])),
                    'observacoes' => $this->get($row, ['observacoes', 'obs']),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] Contrato: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $contrato = \App\Models\Contrato::query()->updateOrCreate(
                    ['codigo_contrato' => $payload['codigo_contrato']],
                    $payload
                );

                $fiadorId = $this->crosswalk['pessoas'][$this->get($row, ['fiador_id'], 0)] ?? null;

                if ($fiadorId) {
                    $contrato->fiadores()->syncWithoutDetaching([$fiadorId]);
                }

                $this->crosswalk['contratos'][$row->id] = $contrato->id;
                $count++;
            }
        });

        return $count;
    }

    private function importFaturas(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('faturas')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'contrato_id' => $this->crosswalk['contratos'][$this->get($row, ['contrato_id'], 0)] ?? null,
                    'competencia' => $this->parseDate($this->normalizeCompetencia($this->get($row, ['competencia']))),
                    'vencimento' => $this->parseDate($this->get($row, ['vencimento'])),
                    'status' => $this->mapStatusFatura($this->get($row, ['status'])),
                    'valor_total' => $this->parseDecimal($this->get($row, ['valor_total', 'valor'])),
                    'valor_pago' => $this->parseDecimal($this->get($row, ['valor_pago'])),
                    'pago_em' => $this->parseDate($this->get($row, ['pago_em', 'data_pagamento'])),
                    'metodo_pagamento' => $this->get($row, ['metodo_pagamento']),
                    'nosso_numero' => $this->get($row, ['nosso_numero']),
                    'boleto_url' => $this->get($row, ['boleto_url']),
                    'pix_qrcode' => $this->get($row, ['pix_qrcode']),
                    'observacoes' => $this->get($row, ['observacoes', 'obs']),
                    'created_at' => $this->parseDateTime($this->get($row, ['created_at', 'criado_em'])) ?? now(),
                    'updated_at' => $this->parseDateTime($this->get($row, ['updated_at', 'atualizado_em'])) ?? now(),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] Fatura: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $fatura = \App\Models\Fatura::query()->updateOrCreate(
                    ['id' => $row->id],
                    $payload
                );

                $this->crosswalk['faturas'][$row->id] = $fatura->id;
                $count++;
            }
        });

        return $count;
    }

    private function importFaturaLancamentos(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('fatura_lancamentos')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'id' => $row->id,
                    'fatura_id' => $this->crosswalk['faturas'][$this->get($row, ['fatura_id'], 0)] ?? null,
                    'categoria' => $this->get($row, ['categoria'], 'Outros'),
                    'descricao' => $this->get($row, ['descricao', 'de_para']),
                    'quantidade' => $this->parseDecimal($this->get($row, ['quantidade'], 1)) ?? 1,
                    'valor_unitario' => $this->parseDecimal($this->get($row, ['valor_unitario', 'valor'])),
                    'valor_total' => $this->parseDecimal($this->get($row, ['valor_total', 'valor'])),
                    'created_at' => $this->parseDateTime($this->get($row, ['created_at', 'criado_em'])) ?? now(),
                    'updated_at' => $this->parseDateTime($this->get($row, ['updated_at', 'atualizado_em'])) ?? now(),
                ];

                if (! $payload['fatura_id']) {
                    $this->warn('Lancamento ignorado por falta de fatura: '.$row->id);

                    continue;
                }

                if ($this->dryRun) {
                    $this->line('[dry-run] FaturaLancamento: '.json_encode($payload));
                    $count++;

                    continue;
                }

                DB::table('fatura_lancamentos')->updateOrInsert(['id' => $payload['id']], $payload);
                $count++;
            }
        });

        return $count;
    }

    private function importContasFinanceiras(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('contas_financeiras')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'nome' => $this->get($row, ['nome'], 'Conta'),
                    'tipo' => $this->mapTipoConta($this->get($row, ['tipo'], 'conta_corrente')),
                    'banco' => $this->get($row, ['banco']),
                    'agencia' => $this->get($row, ['agencia']),
                    'numero' => $this->get($row, ['numero']),
                    'saldo_inicial' => $this->parseDecimal($this->get($row, ['saldo_inicial', 'saldo'])),
                    'ativo' => (bool) $this->get($row, ['ativo'], true),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] ContaFinanceira: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $account = FinancialAccount::query()->updateOrCreate(
                    ['nome' => $payload['nome']],
                    $payload
                );

                $this->crosswalk['financial_accounts'][$row->id] = $account->id;
                $count++;
            }
        });

        return $count;
    }

    private function importCentrosCusto(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('centros_custo')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'nome' => $this->get($row, ['nome'], 'Centro'),
                    'descricao' => $this->get($row, ['descricao']),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] CentroCusto: '.json_encode($payload));
                    $count++;

                    continue;
                }

                $center = CostCenter::query()->updateOrCreate(['nome' => $payload['nome']], $payload);
                $this->crosswalk['cost_centers'][$row->id] = $center->id;
                $count++;
            }
        });

        return $count;
    }

    private function importLancamentosFinanceiros(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('lancamentos_financeiros')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $accountLegacy = $this->get($row, ['conta_id', 'account_id'], 0);
                $accountId = $this->crosswalk['financial_accounts'][$accountLegacy] ?? null;

                if (! $accountId) {
                    $this->warn('Lancamento financeiro ignorado sem conta: '.$row->id);

                    continue;
                }

                $payload = [
                    'account_id' => $accountId,
                    'cost_center_id' => $this->crosswalk['cost_centers'][$this->get($row, ['centro_custo_id'], 0)] ?? null,
                    'contrato_id' => $this->crosswalk['contratos'][$this->get($row, ['contrato_id'], 0)] ?? null,
                    'fatura_id' => $this->crosswalk['faturas'][$this->get($row, ['fatura_id'], 0)] ?? null,
                    'tipo' => $this->mapTipoLancamento($this->get($row, ['tipo'], 'debito')),
                    'valor' => $this->parseDecimal($this->get($row, ['valor'], 0)) ?? 0,
                    'data_ocorrencia' => $this->parseDate($this->get($row, ['data', 'data_ocorrencia'])) ?? now()->toDateString(),
                    'descricao' => $this->get($row, ['descricao', 'historico']),
                    'status' => $this->mapStatusLancamento($this->get($row, ['status'], 'pendente')),
                    'meta' => $this->decodeJson($this->get($row, ['meta'], '[]')),
                ];

                if ($payload['valor'] === 0) {
                    $this->warn('Lancamento financeiro ignorado (valor zero): '.$row->id);

                    continue;
                }

                if ($this->dryRun) {
                    $this->line('[dry-run] LancamentoFinanceiro: '.json_encode($payload));
                    $count++;

                    continue;
                }

                FinancialTransaction::query()->create($payload);
                $count++;
            }
        });

        return $count;
    }

    private function importAgendamentosFinanceiros(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('agendamentos_pagamento')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'titulo' => $this->get($row, ['titulo'], 'Agendamento'),
                    'valor_total' => $this->parseDecimal($this->get($row, ['valor_total', 'valor'])),
                    'parcela_atual' => (int) $this->get($row, ['parcela_atual'], 0),
                    'total_parcelas' => (int) $this->get($row, ['total_parcelas'], 1),
                    'vencimento' => $this->parseDate($this->get($row, ['vencimento'])),
                    'status' => $this->mapStatusAgendamento($this->get($row, ['status'], 'aberto')),
                    'meta' => $this->decodeJson($this->get($row, ['meta'], '[]')),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] AgendamentoFinanceiro: '.json_encode($payload));
                    $count++;

                    continue;
                }

                PaymentSchedule::query()->create($payload);
                $count++;
            }
        });

        return $count;
    }

    private function importAuditLogs(int $chunk): int
    {
        $count = 0;

        $this->legacy->table('audit_logs')->orderBy('id')->chunk($chunk, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $payload = [
                    'user_id' => $this->crosswalk['usuarios'][$this->get($row, ['user_id'], 0)] ?? null,
                    'action' => (string) $this->get($row, ['action'], 'unknown'),
                    'auditable_type' => $this->mapAuditableType($this->get($row, ['auditable_type'])),
                    'auditable_id' => $this->mapAuditableId($row),
                    'payload' => $this->decodeJson($this->get($row, ['payload'], '[]')),
                    'ip_address' => $this->get($row, ['ip_address', 'ip']),
                    'user_agent' => $this->get($row, ['user_agent']),
                    'created_at' => $this->parseDateTime($this->get($row, ['created_at', 'criado_em'])) ?? now(),
                    'updated_at' => $this->parseDateTime($this->get($row, ['updated_at', 'atualizado_em'])) ?? now(),
                ];

                if ($this->dryRun) {
                    $this->line('[dry-run] AuditLog: '.json_encode($payload));
                    $count++;

                    continue;
                }

                DB::table('audit_logs')->insert($payload);
                $count++;
            }
        });

        return $count;
    }

    private function mapAuditableType(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        return match ($type) {
            'usuarios', 'App\\Models\\Usuario' => User::class,
            'pessoas', 'App\\Models\\Pessoa' => \App\Models\Pessoa::class,
            'imoveis', 'App\\Models\\Imovel' => \App\Models\Imovel::class,
            'contratos', 'App\\Models\\Contrato' => \App\Models\Contrato::class,
            'faturas', 'App\\Models\\Fatura' => \App\Models\Fatura::class,
            default => $type,
        };
    }

    private function mapAuditableId(object $row): ?int
    {
        $legacyId = (int) $this->get($row, ['auditable_id'], 0);
        $type = $this->mapAuditableType($this->get($row, ['auditable_type']));

        return match ($type) {
            User::class => $this->crosswalk['usuarios'][$legacyId] ?? null,
            \App\Models\Pessoa::class => $this->crosswalk['pessoas'][$legacyId] ?? null,
            \App\Models\Imovel::class => $this->crosswalk['imoveis'][$legacyId] ?? null,
            \App\Models\Contrato::class => $this->crosswalk['contratos'][$legacyId] ?? null,
            \App\Models\Fatura::class => $this->crosswalk['faturas'][$legacyId] ?? null,
            default => $legacyId ?: null,
        };
    }

    private function normalizeCpf(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $value) ?: null;
    }

    private function mapTipoPessoa(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'juridica', 'pj' => 'Juridica',
            default => 'Fisica',
        };
    }

    private function mapDisponibilidade(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'indisponivel', 'ocupado', 'ocupada' => 'Indisponivel',
            default => 'Disponivel',
        };
    }

    private function mapPeriodoIptu(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'anual' => 'Anual',
            default => 'Mensal',
        };
    }

    private function mapGarantia(?string $value): string
    {
        $allowed = ['Fiador', 'Seguro', 'Caucao', 'SemGarantia'];
        $normalized = Str::of((string) $value)->trim()->ucfirst()->value();

        return in_array($normalized, $allowed, true) ? $normalized : 'SemGarantia';
    }

    private function mapStatusContrato(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'encerrado', 'encerrada', 'finalizado' => 'Encerrado',
            'suspenso', 'suspensa' => 'Suspenso',
            default => 'Ativo',
        };
    }

    private function mapStatusFatura(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'paga', 'pago', 'quitada' => 'Paga',
            'cancelada', 'cancelado' => 'Cancelada',
            default => 'Aberta',
        };
    }

    private function mapTipoConta(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'caixa' => 'caixa',
            'outro', 'outros' => 'outro',
            default => 'conta_corrente',
        };
    }

    private function mapTipoLancamento(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'credito', 'receita' => 'credito',
            default => 'debito',
        };
    }

    private function mapStatusLancamento(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'conciliado', 'conciliada', 'reconciliado' => 'conciliado',
            'cancelado', 'cancelada' => 'cancelado',
            default => 'pendente',
        };
    }

    private function mapStatusAgendamento(?string $value): string
    {
        return match (Str::lower((string) $value)) {
            'quitado', 'pago' => 'quitado',
            'atrasado', 'em_atraso' => 'em_atraso',
            'cancelado', 'cancelada' => 'cancelado',
            default => 'aberto',
        };
    }

    private function normalizeCompetencia(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        try {
            return Carbon::parse($value)->startOfMonth()->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => $item !== null && $item !== ''));
            }
        }

        return [];
    }

    private function decodeCsv(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value);
        $normalized = str_replace(['. ', ' '], '', $normalized);
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return $normalized === '' ? null : (float) $normalized;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    private function get(object $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (isset($row->$key)) {
                return $row->$key;
            }
        }

        return $default;
    }
}

