<?php

namespace App\Domain\Financeiro\Services\Reconciliation\Parsers;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class BankStatementParserFactory
{
    /**
     * @var array<int,BankStatementParser>
     */
    private array $parsers;

    /**
     * @param  array<int,BankStatementParser>|null  $parsers
     */
    public function __construct(?array $parsers = null)
    {
        $this->parsers = $parsers ?? [
            new CsvBankStatementParser(),
            new OfxBankStatementParser(),
        ];
    }

    /**
     * @throws \RuntimeException
     */
    public function make(UploadedFile $file): BankStatementParser
    {
        $extension = (string) $file->getClientOriginalExtension();
        $mimeType = (string) $file->getClientMimeType();

        foreach ($this->parsers as $parser) {
            if ($parser->supports($extension, $mimeType)) {
                return $parser;
            }
        }

        throw new \RuntimeException('Formato de extrato não suportado.');
    }
}
