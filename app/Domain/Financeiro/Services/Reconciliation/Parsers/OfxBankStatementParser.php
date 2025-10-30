<?php

namespace App\Domain\Financeiro\Services\Reconciliation\Parsers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use SimpleXMLElement;

class OfxBankStatementParser implements BankStatementParser
{
    public function supports(string $extension, string $mimeType): bool
    {
        $extension = Str::lower($extension);
        $mimeType = Str::lower($mimeType);

        return in_array($extension, ['ofx', 'qfx'], true)
            || str_contains($mimeType, 'ofx')
            || str_contains($mimeType, 'application/x-ofx');
    }

    public function parse(string $contents, string $fileName): ParsedBankStatement
    {
        $contents = trim($contents);

        if ($contents === '') {
            throw new \RuntimeException('Arquivo OFX vazio.');
        }

        $xmlString = $this->sanitizeOfx($contents);
        $xml = $this->loadXml($xmlString);

        $transactions = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/STMTRS/BANKTRANLIST/STMTTRN');
        if (! $transactions) {
            $transactions = $xml->xpath('//CREDITCARDMSGSRSV1/CCSTMTTRNRS/CCSTMTRS/BANKTRANLIST/STMTTRN');
        }

        if (! $transactions) {
            throw new \RuntimeException('Arquivo OFX sem transações.');
        }

        $lines = [];
        foreach ($transactions as $index => $transaction) {
            $amount = $this->resolveAmount((string) ($transaction->TRNAMT ?? '0'));
            $description = trim((string) ($transaction->MEMO ?? $transaction->NAME ?? ''));
            $datePosted = $this->resolveDate((string) ($transaction->DTPOSTED ?? $transaction->DTUSER ?? ''));
            $fitId = trim((string) ($transaction->FITID ?? ''));

            if (! $datePosted || $amount === null) {
                continue;
            }

            $lines[] = [
                'linha' => $index + 1,
                'transaction_date' => $datePosted,
                'description' => $description !== '' ? $description : 'Sem descrição',
                'amount' => $amount,
                'balance' => null,
                'document_number' => null,
                'fit_id' => $fitId !== '' ? $fitId : null,
            ];
        }

        if ($lines === []) {
            throw new \RuntimeException('Nenhuma movimentação válida encontrada no arquivo OFX.');
        }

        $statementId = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/TRNUID');
        $accountId = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/STMTRS/BANKACCTFROM/ACCTID');
        $routingNumber = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/STMTRS/BANKACCTFROM/BANKID');
        $ledgerBalance = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/STMTRS/LEDGERBAL/BALAMT');
        $ledgerDate = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/STMTRS/LEDGERBAL/DTASOF');
        $tranList = $xml->xpath('//BANKMSGSRSV1/STMTTRNRS/STMTRS/BANKTRANLIST');

        $rangeStart = null;
        $rangeEnd = null;
        if ($tranList && isset($tranList[0])) {
            $list = $tranList[0];
            $rangeStart = $this->resolveDate((string) ($list->DTSTART ?? ''));
            $rangeEnd = $this->resolveDate((string) ($list->DTEND ?? ''));
        }

        $reference = $statementId ? (string) $statementId[0] : pathinfo($fileName, PATHINFO_FILENAME);

        return new ParsedBankStatement(
            reference: $reference,
            lines: $lines,
            meta: array_filter([
                'format' => 'ofx',
                'account_id' => $accountId ? (string) $accountId[0] : null,
                'routing_number' => $routingNumber ? (string) $routingNumber[0] : null,
                'closing_balance' => $ledgerBalance ? (float) $ledgerBalance[0] : null,
                'closing_balance_date' => $ledgerDate ? $this->resolveDate((string) $ledgerDate[0]) : null,
                'period_start' => $rangeStart,
                'period_end' => $rangeEnd,
            ], fn ($value) => $value !== null),
        );
    }

    private function sanitizeOfx(string $contents): string
    {
        $start = stripos($contents, '<OFX>');
        if ($start === false) {
            throw new \RuntimeException('Conteúdo OFX inválido.');
        }

        $xmlBody = substr($contents, $start);
        $xmlBody = preg_replace('/<(\w+?)>([^<>\r\n]+)\s*\r?\n/', '<$1>$2</$1>'."\n", $xmlBody);
        $xmlBody = preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $xmlBody);

        if (! str_starts_with($xmlBody, '<OFX>')) {
            $xmlBody = '<OFX>'.$xmlBody;
        }

        return $xmlBody;
    }

    private function loadXml(string $xmlString): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if (! $xml) {
            $errors = collect(libxml_get_errors())->map(fn ($error) => trim($error->message))->implode('; ');
            libxml_clear_errors();
            throw new \RuntimeException('Falha ao interpretar OFX: '.$errors);
        }

        return $xml;
    }

    private function resolveAmount(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function resolveDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('Ymd', $value)->toDateString();
        }

        if (preg_match('/^\d{14}(\.\d+)?$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('YmdHis', substr($value, 0, 14))->toDateString();
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return CarbonImmutable::createFromTimestamp($timestamp)->toDateString();
        }

        return null;
    }
}
