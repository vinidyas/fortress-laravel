<?php

namespace App\Console\Commands;

use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Services\CreateJournalEntryService;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\Imovel;
use App\Models\JournalEntry;
use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportMccLedger extends Command
{
    protected $signature = 'mcc:import {path : Caminho absoluto ou relativo para o CSV exportado do MCC}
        {--status=pago : Status padrão que os lançamentos importados receberão}
        {--dry-run : Processa o arquivo e apresenta o resumo sem gravar no banco}
        {--update-existing : Atualiza lançamentos já importados (origin=importado) em vez de criar novos}';

    protected $description = 'Importa lançamentos financeiros exportados do sistema MCC';

    private Collection $accounts;

    private Collection $costCenters;

    private Collection $people;

    private Collection $properties;

    private array $accountLookup = [];

    private array $costCenterLookup = [];

    private array $personLookup = [];

    private array $propertyLookupByCode = [];

    private array $propertyLookupByComplement = [];

    private array $propertyLookupByAddress = [];

    private int $processed = 0;

    private int $created = 0;

    private int $skipped = 0;

    private array $errors = [];

    private array $warnings = [];

    private bool $dryRun = false;

    private bool $updateExisting = false;

    private int $updated = 0;

    public function __construct(
        private readonly CreateJournalEntryService $createJournalEntry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! File::exists($path)) {
            $this->error(sprintf('Arquivo não encontrado: %s', $path));

            return self::FAILURE;
        }

        $status = JournalEntryStatus::tryFrom(strtolower((string) $this->option('status'))) ?? JournalEntryStatus::Pago;
        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = (bool) $this->option('update-existing');
        $this->dryRun = $dryRun;
        $this->updateExisting = $updateExisting;

        $this->bootstrapLookups();

        $rows = $this->readCsv($path);

        if ($rows->isEmpty()) {
            $this->warn('Arquivo não contém linhas para importar.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Processando %d linhas...', $rows->count()));

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $index => $row) {
            $this->processed++;

            try {
                $payload = $this->mapRowToDto($row, $index + 2, $status); // +2 para considerar header (linha 1) e base 1

                if ($payload === null) {
                    $this->skipped++;
                    $bar->advance();
                    continue;
                }

                $dto = $payload['dto'];
                $meta = $payload['meta'];

                if ($updateExisting) {
                    if ($this->updateExistingEntry($meta, $dto, $index + 2, $dryRun)) {
                        $this->updated++;
                    } else {
                        $this->skipped++;
                    }
                    $bar->advance();
                    continue;
                }

                if (! $dryRun) {
                    $this->createJournalEntry->handle($dto);
                }

                $this->created++;
            } catch (\Throwable $exception) {
                $this->errors[] = [
                    'linha' => $index + 2,
                    'descricao' => $row['DESCRIÇÃO'] ?? $row['DESCRI��O'] ?? '',
                    'erro' => $exception->getMessage(),
                ];
                $this->skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Processados', 'Criados', 'Atualizados', 'Ignorados'], [[
            $this->processed,
            $dryRun ? sprintf('%d (dry-run)', $this->created) : $this->created,
            $dryRun ? sprintf('%d (dry-run)', $this->updated) : $this->updated,
            $this->skipped,
        ]]);

        if (! empty($this->errors)) {
            $this->warn('Ocorreram erros nas seguintes linhas:');
            $this->table(['Linha', 'Descrição', 'Erro'], $this->errors);
        }

        if (! empty($this->warnings)) {
            $this->warn('Avisos gerados durante a importação:');
            $this->table(['Linha', 'Descrição', 'Aviso'], $this->warnings);
        }

        $this->info($dryRun
            ? 'Dry-run finalizado. Execute novamente sem --dry-run para gravar os lançamentos.'
            : 'Importação concluída com sucesso.'
        );

        return $dryRun && ! empty($this->errors) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int,array<string,string|null>>
     */
    private function readCsv(string $path): Collection
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException(sprintf('Não foi possível abrir o arquivo %s', $path));
        }

        $rows = [];
        $header = null;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($header === null) {
                $header = array_map([$this, 'normalizeEncoding'], $data);
                continue;
            }

            $data = array_map([$this, 'normalizeEncoding'], $data);

            if (count(array_filter($data, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = $this->combineRow($header, $data);
        }

        fclose($handle);

        return Collection::make($rows);
    }

    /**
     * @param  array<string,string|null>  $row
     */
    /**
     * @return array{dto: JournalEntryData, meta: array<string,mixed>}|null
     */
    private function mapRowToDto(array $row, int $lineNumber, JournalEntryStatus $status): ?array
    {
        $typeValue = strtolower($this->value($row, ['TIPO']) ?? '');
        $entryType = $this->resolveEntryType($typeValue);

        if (! $entryType) {
            $this->errors[] = [
                'linha' => $lineNumber,
                'descricao' => $this->value($row, ['DESCRIÇÃO', 'DESCRI��O']) ?? '',
                'erro' => sprintf('Tipo "%s" não suportado.', $row['TIPO'] ?? 'n/a'),
            ];

            return null;
        }

        $description = $this->value($row, ['DESCRIÇÃO', 'DESCRI��O']);
        $accountName = $this->value($row, ['CONTA']);
        $costCenterName = $this->value($row, ['CENTRO DE CUSTO']);
        $personName = $this->value($row, ['CLIENTE / FORNECEDOR']);
        $notes = $this->value($row, ['OBSERVAÇÃO', 'OBSERVA��O']);
        $document = $this->value($row, ['DOCUMENTO']);
        $amountRaw = $this->value($row, ['VALOR']);
        $movementDateRaw = $this->value($row, ['DATA']);
        $dueDateRaw = $this->value($row, ['VENCIMENTO']);

        $movementDate = $this->parseDate($movementDateRaw, $lineNumber, 'DATA');
        $dueDate = $dueDateRaw ? $this->parseDate($dueDateRaw, $lineNumber, 'VENCIMENTO') : $movementDate;

        if (! $movementDate) {
            throw new InvalidArgumentException('Data inválida ou ausente.');
        }

        if (! $dueDate) {
            $dueDate = $movementDate;
        }

        $amount = $this->parseAmount($amountRaw);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Valor não pôde ser interpretado.');
        }

        $accountId = $this->resolveAccountId($accountName, $lineNumber);

        if (! $accountId) {
            if (! $this->dryRun) {
                throw new InvalidArgumentException(sprintf('Conta não localizada: "%s"', $accountName));
            }

            $accountId = 0;
        }

        $costCenterId = $this->resolveCostCenterId($costCenterName);
        $propertyId = $this->resolvePropertyId($costCenterName, $lineNumber);
        $personId = $this->resolvePersonId($personName, $entryType, $lineNumber);

        if ($accountId === 0) {
            $accountId = null;
        }

        if ($personId === 0) {
            $personId = null;
        }

        $description = $description ?: sprintf('Lançamento MCC %s', $movementDate->format('d/m/Y'));

        $propertyLabel = trim((string) ($costCenterName ?? '')) ?: null;
        $propertyFallback = $propertyId ? null : $propertyLabel;

        $installmentStatus = $status;
        $paymentDate = $status === JournalEntryStatus::Pago ? $dueDate : null;
        $installmentAmount = abs($amount);

        $installmentMeta = [
            'origem' => 'mcc',
        ];

        if ($propertyFallback) {
            $installmentMeta['property_label'] = $propertyFallback;
        }

        $installment = new InstallmentData(
            numeroParcela: 1,
            movementDate: $movementDate->toDateString(),
            dueDate: $dueDate->toDateString(),
            paymentDate: $paymentDate?->toDateString(),
            valorPrincipal: $installmentAmount,
            valorJuros: 0.0,
            valorMulta: 0.0,
            valorDesconto: 0.0,
            valorTotal: $installmentAmount,
            status: $installmentStatus,
            meta: $installmentMeta
        );

        $dto = new JournalEntryData(
            type: $entryType,
            bankAccountId: $accountId,
            counterBankAccountId: null,
            costCenterId: $costCenterId,
            propertyId: $propertyId,
            personId: $personId,
            descriptionId: null,
            descriptionCustom: $description,
            notes: $notes ?: null,
            referenceCode: $document ?: null,
            origin: 'importado',
            cloneOfId: null,
            movementDate: $movementDate->toDateString(),
            dueDate: $dueDate?->toDateString(),
            paymentDate: $paymentDate?->toDateString(),
            currency: 'BRL',
            status: $status,
            amount: $amount,
            installments: Collection::make([$installment]),
            allocations: Collection::make(),
            createdBy: null,
            updatedBy: null,
        );

        return [
            'dto' => $dto,
            'meta' => [
                'bank_account_id' => $accountId,
                'amount' => $amount,
                'description' => $description,
                'movement_date' => $movementDate->toDateString(),
                'due_date' => $dueDate?->toDateString(),
                'person_id' => $personId,
            'cost_center_id' => $costCenterId,
            'property_id' => $propertyId,
            'notes' => $notes ?: null,
            'reference_code' => $document ?: null,
            'type' => $entryType->value,
            'property_label' => $propertyFallback,
        ],
    ];
    }

    private function bootstrapLookups(): void
    {
        $this->accounts = FinancialAccount::query()->get(['id', 'nome']);
        $this->costCenters = CostCenter::query()->get(['id', 'nome', 'codigo']);
        $this->people = Pessoa::query()->get(['id', 'nome_razao_social']);
        $this->properties = Imovel::query()->get(['id', 'codigo', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade']);

        $this->accountLookup = $this->accounts
            ->mapWithKeys(fn (FinancialAccount $account) => [$this->normalizeKey($account->nome) => $account->id])
            ->all();

        $this->costCenterLookup = $this->costCenters
            ->filter(fn (CostCenter $center) => $center->codigo)
            ->mapWithKeys(fn (CostCenter $center) => [$this->normalizeKey((string) $center->codigo) => $center->id])
            ->all();

        $this->personLookup = $this->people
            ->mapWithKeys(fn (Pessoa $pessoa) => [$this->normalizeKey($pessoa->nome_razao_social) => $pessoa->id])
            ->all();

        $this->propertyLookupByCode = $this->properties
            ->filter(fn (Imovel $imovel) => $imovel->codigo)
            ->mapWithKeys(fn (Imovel $imovel) => [$this->normalizeKey((string) $imovel->codigo) => $imovel->id])
            ->all();

        $this->propertyLookupByComplement = $this->properties
            ->filter(fn (Imovel $imovel) => $imovel->complemento)
            ->mapWithKeys(fn (Imovel $imovel) => [$this->normalizeKey((string) $imovel->complemento) => $imovel->id])
            ->all();

        $this->propertyLookupByAddress = $this->properties
            ->mapWithKeys(function (Imovel $imovel) {
                $parts = array_filter([
                    $imovel->logradouro,
                    $imovel->numero,
                    $imovel->bairro,
                    $imovel->cidade,
                ]);

                if (empty($parts)) {
                    return [];
                }

                return [$this->normalizeKey(implode(' ', $parts)) => $imovel->id];
            })
            ->all();
    }

    private function resolveAccountId(?string $raw, int $lineNumber): ?int
    {
        if (! $raw) {
            return null;
        }

        $normalized = $this->normalizeKey($raw);

        if (isset($this->accountLookup[$normalized])) {
            return $this->accountLookup[$normalized];
        }

        foreach ($this->accountLookup as $key => $id) {
            if (str_contains($key, $normalized) || str_contains($normalized, $key)) {
                return $id;
            }
        }

        if ($this->dryRun) {
            $this->warnings[] = [
                'linha' => $lineNumber,
                'descricao' => trim($raw),
                'aviso' => sprintf('Conta "%s" seria criada automaticamente.', $raw),
            ];

            $this->accountLookup[$normalized] = 0;

            return 0;
        }

        $account = FinancialAccount::create([
            'nome' => trim($raw),
            'tipo' => 'conta_corrente',
            'saldo_inicial' => 0,
            'saldo_atual' => 0,
            'moeda' => 'BRL',
            'categoria' => 'operacional',
            'permite_transf' => true,
            'padrao_recebimento' => false,
            'padrao_pagamento' => false,
            'ativo' => true,
        ]);

        $this->accounts->push($account);
        $this->accountLookup[$normalized] = $account->id;

        $this->warnings[] = [
            'linha' => $lineNumber,
            'descricao' => trim($raw),
            'aviso' => sprintf('Conta "%s" criada automaticamente (ID %d).', $raw, $account->id),
        ];

        return $account->id;
    }

    private function resolveCostCenterId(?string $raw): ?int
    {
        if (! $raw) {
            return null;
        }

        $parts = array_map('trim', explode('-', $raw, 2));
        $code = $parts[0] ?? null;
        $name = $parts[1] ?? $raw;

        if ($code) {
            $normalizedCode = $this->normalizeKey($code);
            if (isset($this->costCenterLookup[$normalizedCode])) {
                return $this->costCenterLookup[$normalizedCode];
            }
        }

        $normalizedName = $this->normalizeKey($name ?: $raw);
        foreach ($this->costCenters as $center) {
            if ($this->normalizeKey($center->nome) === $normalizedName) {
                return $center->id;
            }
        }

        foreach ($this->costCenters as $center) {
            if (str_contains($this->normalizeKey($center->nome), $normalizedName)) {
                return $center->id;
            }
        }

        return null;
    }

    private function resolvePropertyId(?string $raw, int $lineNumber): ?int
    {
        if (! $raw) {
            return null;
        }

        $parts = array_map('trim', explode('-', $raw, 2));
        $code = $parts[0] ?? null;
        $name = $parts[1] ?? null;

        if ($code) {
            $normalizedCode = $this->normalizeKey($code);
            if (isset($this->propertyLookupByCode[$normalizedCode])) {
                return $this->propertyLookupByCode[$normalizedCode];
            }
        }

        $complementCandidate = $parts[1] ?? null;
        $candidates = collect([$name, $raw, $complementCandidate])
            ->filter()
            ->map(fn ($value) => $this->normalizeKey((string) $value))
            ->unique()
            ->values();

        foreach ($candidates as $candidate) {
            if (isset($this->propertyLookupByComplement[$candidate])) {
                return $this->propertyLookupByComplement[$candidate];
            }

            if (isset($this->propertyLookupByAddress[$candidate])) {
                return $this->propertyLookupByAddress[$candidate];
            }
        }

        if ($code) {
            $normalizedCode = $this->normalizeKey($code);
            foreach ($this->properties as $property) {
                if ($property->codigo && Str::startsWith($this->normalizeKey($property->codigo), $normalizedCode)) {
                    return $property->id;
                }
            }
        }

        foreach ($this->properties as $property) {
            if (! $property->complemento) {
                continue;
            }

            $normalizedComplement = $this->normalizeKey((string) $property->complemento);
            if ($candidates->contains($normalizedComplement)) {
                return $property->id;
            }

            if ($code) {
                $normalizedCode = $this->normalizeKey($code);
                if (Str::startsWith($normalizedComplement, $normalizedCode)) {
                    return $property->id;
                }
            }
        }

        $this->warnings[] = [
            'linha' => $lineNumber,
            'descricao' => $raw,
            'aviso' => 'Imóvel não localizado para o centro de custo informado.',
        ];

        return null;
    }

    private function resolvePersonId(?string $raw, JournalEntryType $type, int $lineNumber): ?int
    {
        if (! $raw) {
            return null;
        }

        $normalized = trim($raw);
        $key = $this->normalizeKey($normalized);
        $role = $type === JournalEntryType::Receita ? 'Cliente' : 'Fornecedor';

        if (isset($this->personLookup[$key])) {
            return $this->personLookup[$key];
        }

        $person = Pessoa::query()
            ->whereRaw('LOWER(nome_razao_social) = ?', [mb_strtolower($normalized)])
            ->first();

        if ($person) {
            $this->personLookup[$key] = $person->id;

            $roles = $person->papeis ?? [];
            if (! in_array($role, $roles, true)) {
                if ($this->dryRun) {
                    $this->warnings[] = [
                        'linha' => $lineNumber,
                        'descricao' => $normalized,
                        'aviso' => sprintf('Pessoa "%s" receberia o papel "%s".', $normalized, $role),
                    ];
                } else {
                    $person->papeis = array_values(array_unique([...$roles, $role]));
                    $person->save();

                    $this->warnings[] = [
                        'linha' => $lineNumber,
                        'descricao' => $normalized,
                        'aviso' => sprintf('Papel "%s" atribuído a "%s" (ID %d).', $role, $normalized, $person->id),
                    ];
                }
            }

            return $person->id;
        }

        if ($this->dryRun) {
            $this->warnings[] = [
                'linha' => $lineNumber,
                'descricao' => $normalized,
                'aviso' => sprintf('Pessoa "%s" seria criada automaticamente como %s.', $normalized, mb_strtolower($role)),
            ];

            $this->personLookup[$key] = 0;

            return 0;
        }

        $person = Pessoa::create([
            'nome_razao_social' => $normalized,
            'tipo_pessoa' => 'Juridica',
            'papeis' => [$role],
        ]);

        $this->people->push($person);
        $this->personLookup[$key] = $person->id;

        $this->warnings[] = [
            'linha' => $lineNumber,
            'descricao' => $normalized,
            'aviso' => sprintf('Pessoa "%s" criada automaticamente como %s (ID %d).', $normalized, mb_strtolower($role), $person->id),
        ];

        return $person->id;
    }

    private function parseDate(?string $value, int $line, string $field): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        try {
            return Carbon::createFromFormat('d/m/Y', $value);
        } catch (\Exception $exception) {
            $this->errors[] = [
                'linha' => $line,
                'descricao' => $field,
                'erro' => sprintf('Data inválida "%s": %s', $value, $exception->getMessage()),
            ];

            return null;
        }
    }

    private function parseAmount(?string $value): float
    {
        if (! $value) {
            return 0.0;
        }

        $normalized = str_replace(['R$', ' ', '.', "\u{A0}", "\t"], '', $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = str_replace(['(', ')'], '', $normalized);
        $normalized = preg_replace('/[^0-9\\.-]/', '', $normalized) ?? '';

        return (float) $normalized;
    }

    private function normalizeEncoding(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = Str::slug($value, '_');

        return $value;
    }

    private function resolveEntryType(string $raw): ?JournalEntryType
    {
        $raw = trim($raw);

        if ($raw === '') {
            return JournalEntryType::Despesa;
        }

        return match (true) {
            str_starts_with($raw, 'desp') => JournalEntryType::Despesa,
            str_starts_with($raw, 'rec') => JournalEntryType::Receita,
            str_starts_with($raw, 'trans') => JournalEntryType::Transferencia,
            default => null,
        };
    }

    private function updateExistingEntry(array $meta, JournalEntryData $dto, int $lineNumber, bool $dryRun): bool
    {
        $accountId = $meta['bank_account_id'] ?? null;

        if (! $accountId) {
            $this->warnings[] = [
                'linha' => $lineNumber,
                'descricao' => $meta['description'] ?? 'Sem descrição',
                'aviso' => 'Conta não encontrada; lançamento não pôde ser localizado para atualização.',
            ];

            return false;
        }

        $amount = (float) $meta['amount'];
        $description = $meta['description'] ?? null;

        $normalizedDescription = $description ? mb_strtolower($description) : null;

        $query = JournalEntry::query()
            ->where('origin', 'importado')
            ->where('bank_account_id', $accountId)
            ->whereDate('movement_date', $meta['movement_date'])
            ->where('type', $meta['type'])
            ->whereRaw('ABS(amount - ?) < 0.01', [$amount]);

        if ($normalizedDescription) {
            $query->whereRaw('LOWER(description_custom) = ?', [$normalizedDescription]);
        }

        $existing = $query->first();

        if (! $existing) {
            if ($normalizedDescription) {
                $existing = JournalEntry::query()
                    ->where('origin', 'importado')
                    ->where('bank_account_id', $accountId)
                    ->whereDate('movement_date', $meta['movement_date'])
                    ->where('type', $meta['type'])
                    ->whereRaw('ABS(amount - ?) < 0.01', [$amount])
                    ->whereRaw('LOWER(description_custom) LIKE ?', [$normalizedDescription.'%'])
                    ->first();
            }
        }

        if (! $existing) {
            $this->warnings[] = [
                'linha' => $lineNumber,
                'descricao' => $description ?? 'Sem descrição',
                'aviso' => 'Nenhum lançamento existente encontrado para atualizar.',
            ];

            return false;
        }

        $propertyLabel = $meta['property_label'] ?? null;

        if ($dryRun) {
            $this->warnings[] = [
                'linha' => $lineNumber,
                'descricao' => $description ?? sprintf('Lançamento #%d', $existing->id),
                'aviso' => sprintf('Lançamento #%d seria atualizado (pessoa/centro de custo/imóvel).', $existing->id),
            ];

            return true;
        }

        if (! empty($meta['person_id'])) {
            $existing->person_id = $meta['person_id'];
        }

        if (! empty($meta['cost_center_id'])) {
            $existing->cost_center_id = $meta['cost_center_id'];
        }

        if (! empty($meta['property_id'])) {
            $existing->property_id = $meta['property_id'];
        } elseif ($propertyLabel) {
            $existing->property_id = null;
            $existing->installments()->limit(1)->get()->each(function ($installment) use ($propertyLabel) {
                $meta = (array) ($installment->meta ?? []);
                $meta['property_label'] = $propertyLabel;
                $installment->meta = $meta;
                $installment->save();
            });
        }

        $existing->notes = $meta['notes'] ?? $existing->notes;
        $existing->reference_code = $meta['reference_code'] ?? $existing->reference_code;

        if (! empty($meta['due_date'])) {
            $existing->due_date = $meta['due_date'];
        }

        $existing->save();

        $this->warnings[] = [
            'linha' => $lineNumber,
            'descricao' => $description ?? sprintf('Lançamento #%d', $existing->id),
            'aviso' => sprintf('Lançamento #%d atualizado com sucesso.', $existing->id),
        ];

        return true;
    }

    /**
     * @param  string[]  $candidates
     */
    private function value(array $row, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $value = trim((string) $row[$key]);
                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * @param  string[]  $header
     * @param  array<int,string|null>  $row
     * @return array<string,string|null>
     */
    private function combineRow(array $header, array $row): array
    {
        $values = [];

        foreach ($header as $index => $column) {
            $values[$column] = $row[$index] ?? null;
        }

        return $values;
    }
}
