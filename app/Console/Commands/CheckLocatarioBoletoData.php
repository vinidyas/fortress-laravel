<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Pessoa;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckLocatarioBoletoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pessoas:check-locatario-boleto-data {--json : Retorna o resultado em JSON.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista locatários com campos obrigatórios ausentes ou inválidos para geração de boletos.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $records = $this->locatariosComProblemas();

        if ($records->isEmpty()) {
            $this->info('Todos os locatários possuem os dados obrigatórios preenchidos.');

            return Command::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($records->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $rows = $records->map(function (array $entry) {
            return [
                'ID' => Arr::get($entry, 'id'),
                'Nome' => Arr::get($entry, 'nome'),
                'Tipo pessoa' => Arr::get($entry, 'tipo_pessoa'),
                'Problemas' => implode(', ', Arr::get($entry, 'problemas', [])),
            ];
        })->all();

        $this->table(['ID', 'Nome', 'Tipo pessoa', 'Problemas'], $rows);

        $this->warn(sprintf('%d locatário(s) precisam ser corrigidos antes da emissão de boletos.', $records->count()));

        return Command::SUCCESS;
    }

    private function locatariosComProblemas(): Collection
    {
        $query = Pessoa::query()->select([
            'id',
            'nome_razao_social',
            'tipo_pessoa',
            'cpf_cnpj',
            'email',
            'telefone',
            'cep',
            'estado',
            'cidade',
            'bairro',
            'rua',
            'numero',
            'complemento',
            'papeis',
        ]);

        $this->aplicarFiltroLocatarios($query);

        return $query->orderBy('id')
            ->get()
            ->map(fn (Pessoa $pessoa) => $this->avaliarPessoa($pessoa))
            ->filter(fn (?array $item) => $item !== null)
            ->values();
    }

    private function aplicarFiltroLocatarios(Builder $query): void
    {
        try {
            $query->whereJsonContains('papeis', 'Locatario');
        } catch (\Throwable $exception) {
            $driver = DB::connection()->getDriverName();
            $this->warn(sprintf('whereJsonContains não suportado pelo driver "%s", aplicando fallback LIKE.', $driver));

            $query->where('papeis', 'like', '%"Locatario"%');
        }
    }

    /**
     * @return array{id:int,nome:string,tipo_pessoa:string,problemas:array<string>}|null
     */
    private function avaliarPessoa(Pessoa $pessoa): ?array
    {
        $issues = [];

        $cpfCnpj = $this->digits($pessoa->cpf_cnpj);
        if ($cpfCnpj === '') {
            $issues[] = 'CPF/CNPJ ausente';
        } elseif (! $this->cpfCnpjValido($pessoa->tipo_pessoa, $cpfCnpj)) {
            $issues[] = 'CPF/CNPJ inválido';
        }

        $telefone = $this->digits($pessoa->telefone);
        if ($telefone === '') {
            $issues[] = 'Telefone ausente';
        } elseif (strlen($telefone) < 10 || strlen($telefone) > 11) {
            $issues[] = 'Telefone deve conter 10 ou 11 dígitos';
        }

        if (! $pessoa->email) {
            $issues[] = 'E-mail ausente';
        } elseif (! filter_var($pessoa->email, FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'E-mail inválido';
        }

        $cep = $this->digits($pessoa->cep);
        if ($cep === '') {
            $issues[] = 'CEP ausente';
        } elseif (strlen($cep) !== 8) {
            $issues[] = 'CEP deve conter 8 dígitos';
        }

        $camposObrigatorios = [
            'estado' => 'Estado',
            'cidade' => 'Cidade',
            'bairro' => 'Bairro',
            'rua' => 'Logradouro',
            'numero' => 'Número',
        ];

        foreach ($camposObrigatorios as $atributo => $rotulo) {
            if (! $pessoa->{$atributo}) {
                $issues[] = sprintf('%s ausente', $rotulo);
            }
        }

        if ($issues === []) {
            return null;
        }

        return [
            'id' => $pessoa->id,
            'nome' => $pessoa->nome_razao_social,
            'tipo_pessoa' => $pessoa->tipo_pessoa,
            'problemas' => $issues,
        ];
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function cpfCnpjValido(string $tipoPessoa, string $digits): bool
    {
        if ($tipoPessoa === 'Fisica') {
            return strlen($digits) === 11;
        }

        return strlen($digits) === 14;
    }
}
