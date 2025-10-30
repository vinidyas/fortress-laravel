<?php

namespace App\Domain\Financeiro\Services\Reconciliation;

use App\Domain\Financeiro\Services\Reconciliation\Parsers\BankStatementParserFactory;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialAccount;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ImportBankStatementService
{
    public function __construct(
        private readonly BankStatementParserFactory $parserFactory,
        private readonly DatabaseManager $database,
        private readonly FilesystemFactory $filesystem,
    ) {
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    public function handle(int $financialAccountId, UploadedFile $file, array $meta = []): BankStatement
    {
        $account = FinancialAccount::query()->findOrFail($financialAccountId);
        $contents = $file->get();

        if ($contents === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        $hash = hash('sha256', $contents);

        $exists = BankStatement::query()
            ->where('financial_account_id', $account->id)
            ->where('hash', $hash)
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Este extrato já foi importado para a conta selecionada.');
        }

        $parser = $this->parserFactory->make($file);
        $parsed = $parser->parse($contents, $file->getClientOriginalName());

        return $this->database->transaction(function () use ($account, $file, $contents, $hash, $parsed, $meta) {
            $storedPath = $this->storeFile($file, $contents, $account->id);

            /** @var BankStatement $statement */
            $statement = BankStatement::query()->create([
                'financial_account_id' => $account->id,
                'reference' => $this->resolveReference($parsed->reference),
                'original_name' => $file->getClientOriginalName(),
                'imported_at' => now(),
                'imported_by' => auth()->user()?->getKey(),
                'hash' => $hash,
                'status' => 'importado',
                'meta' => array_merge($this->enrichMeta($parsed->meta, $parsed->lines), [
                    'storage_path' => $storedPath,
                ], $meta),
            ]);

            $lines = [];
            foreach ($parsed->lines as $line) {
                $lines[] = $this->makeLinePayload($statement, $line);
            }

            $chunks = array_chunk($lines, 500);
            foreach ($chunks as $chunk) {
                BankStatementLine::query()->insert($chunk);
            }

            return $statement->fresh('lines');
        });
    }

    /**
     * @param  array<string,mixed>  $line
     * @return array<string,mixed>
     */
    private function makeLinePayload(BankStatement $statement, array $line): array
    {
        $transactionDate = Arr::get($line, 'transaction_date');
        $description = Arr::get($line, 'description');
        $amount = Arr::get($line, 'amount');
        $balance = Arr::get($line, 'balance');

        return [
            'bank_statement_id' => $statement->id,
            'linha' => (int) Arr::get($line, 'linha', 0),
            'transaction_date' => $transactionDate,
            'description' => $description,
            'amount' => $amount,
            'balance' => $balance,
            'document_number' => Arr::get($line, 'document_number'),
            'fit_id' => Arr::get($line, 'fit_id'),
            'match_status' => 'nao_casado',
            'matched_installment_id' => null,
            'matched_by' => null,
            'match_meta' => Arr::get($line, 'match_meta'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function resolveReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return (string) Str::uuid();
        }

        return Str::limit($reference, 60, '');
    }

    private function storeFile(UploadedFile $file, string $contents, int $accountId): string
    {
        $disk = $this->filesystem->disk(config('filesystems.default'));
        $directory = sprintf('bank-statements/%d', $accountId);
        $filename = sprintf('%s-%s.%s', now()->format('Ymd_His'), Str::random(8), $file->getClientOriginalExtension());
        $path = trim($directory, '/').'/'.$filename;

        $disk->put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string,mixed>  $meta
     * @param  array<int,array<string,mixed>>  $lines
     * @return array<string,mixed>
     */
    private function enrichMeta(array $meta, array $lines): array
    {
        [$openingBalance, $closingBalance] = $this->resolveBalances($lines);

        return array_filter(
            array_merge($meta, [
                'opening_balance' => $meta['opening_balance'] ?? $openingBalance,
                'closing_balance' => $meta['closing_balance'] ?? $closingBalance,
            ]),
            fn ($value) => $value !== null,
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @return array{0: float|null,1: float|null}
     */
    private function resolveBalances(array $lines): array
    {
        if ($lines === []) {
            return [null, null];
        }

        $sorted = collect($lines)
            ->sortBy(fn ($line) => $line['transaction_date'] ?? '')
            ->values();

        $first = $sorted->first();
        $last = $sorted->last();

        $closing = isset($last['balance']) ? round((float) $last['balance'], 2) : null;

        $opening = null;
        if (isset($first['balance'])) {
            $opening = round((float) $first['balance'] - ((float) $first['amount']), 2);
        }

        return [$opening, $closing];
    }
}
