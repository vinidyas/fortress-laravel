<?php

namespace App\Domain\Financeiro\Services\Reconciliation\Parsers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class CsvBankStatementParser implements BankStatementParser
{
    public function supports(string $extension, string $mimeType): bool
    {
        $extension = Str::lower($extension);
        $mimeType = Str::lower($mimeType);

        return in_array($extension, ['csv', 'txt'], true)
            || str_contains($mimeType, 'csv')
            || str_contains($mimeType, 'text/plain');
    }

    public function parse(string $contents, string $fileName): ParsedBankStatement
    {
        $contents = trim($contents);

        if ($contents === '') {
            throw new \RuntimeException('Arquivo CSV vazio.');
        }

        $rows = preg_split('/\r\n|\r|\n/', $contents);
        if (! $rows || count($rows) < 2) {
            throw new \RuntimeException('Arquivo CSV sem dados suficientes.');
        }

        $delimiter = $this->detectDelimiter($rows[0]);
        $headers = $this->parseRow($rows[0], $delimiter);
        $indexes = $this->mapHeaders($headers);

        $lines = [];
        foreach (array_slice($rows, 1) as $lineNumber => $row) {
            if (trim($row) === '') {
                continue;
            }

            $columns = $this->parseRow($row, $delimiter);
            if (count($columns) < count($headers)) {
                continue;
            }

            $transactionDate = $this->resolveDate($columns[$indexes['date']] ?? null);
            $description = trim((string) ($columns[$indexes['description']] ?? ''));
            $amount = $this->resolveAmount($columns[$indexes['amount']] ?? null);
            $balance = $indexes['balance'] !== null
                ? $this->resolveAmount($columns[$indexes['balance']] ?? null)
                : null;

            if (! $transactionDate || $amount === null) {
                continue;
            }

            $lines[] = [
                'linha' => $lineNumber + 1,
                'transaction_date' => $transactionDate,
                'description' => $description !== '' ? $description : 'Sem descrição',
                'amount' => $amount,
                'balance' => $balance,
                'document_number' => $indexes['document'] !== null ? trim((string) ($columns[$indexes['document']] ?? '')) : null,
                'fit_id' => null,
            ];
        }

        if ($lines === []) {
            throw new \RuntimeException('Não foi possível extrair movimentações do arquivo CSV.');
        }

        $reference = pathinfo($fileName, PATHINFO_FILENAME);

        return new ParsedBankStatement(
            reference: $reference,
            lines: $lines,
            meta: [
                'format' => 'csv',
                'delimiter' => $delimiter,
                'headers' => $headers,
            ],
        );
    }

    /**
     * @return array<string,int|null>
     */
    private function mapHeaders(array $headers): array
    {
        $normalized = array_map(function (string $header) {
            $header = Str::lower($header);
            $header = Str::ascii($header);

            return preg_replace('/[^a-z0-9_]/', '', $header);
        }, $headers);

        $find = function (array $options) use ($normalized) {
            foreach ($options as $option) {
                $index = array_search($option, $normalized, true);
                if ($index !== false) {
                    return $index;
                }
            }

            return null;
        };

        $dateIndex = $find(['data', 'date', 'transactiondate', 'datamovimento']);
        $descriptionIndex = $find(['descricao', 'description', 'historico', 'detalhe']);
        $amountIndex = $find(['valor', 'amount', 'montante', 'creditodebito']);

        if ($dateIndex === null || $descriptionIndex === null || $amountIndex === null) {
            throw new \RuntimeException('Arquivo CSV deve conter colunas de data, descrição e valor.');
        }

        return [
            'date' => $dateIndex,
            'description' => $descriptionIndex,
            'amount' => $amountIndex,
            'balance' => $find(['saldo', 'balance']),
            'document' => $find(['documento', 'document', 'numdocumento']),
        ];
    }

    private function detectDelimiter(string $header): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $bestCount = 0;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($header, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @return array<int,string>
     */
    private function parseRow(string $row, string $delimiter): array
    {
        return str_getcsv($row, $delimiter);
    }

    private function resolveDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
        foreach ($formats as $format) {
            $date = CarbonImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->toDateString();
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return CarbonImmutable::createFromTimestamp($timestamp)->toDateString();
        }

        return null;
    }

    private function resolveAmount(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(['R$', 'r$', ' '], '', trim($value));
        $normalized = str_replace('\u{A0}', '', $normalized);

        if (preg_match('/^-?\d+,\d{1,}$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }
}
