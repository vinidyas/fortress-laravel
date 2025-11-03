<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Banking\Bradesco;

use App\Services\Banking\Bradesco\Support\BradescoPayloadSanitizer;
use PHPUnit\Framework\TestCase;

class BradescoPayloadSanitizerTest extends TestCase
{
    public function testSanitizeMasksSensitiveDataRecursively(): void
    {
        $input = [
            'nuCpfcnpjPagador' => '12345678901',
            'nomePagador' => 'Joao da Silva',
            'endEletronicoPagador' => 'pagador@example.com',
            'dddFoneSacado' => '11',
            'foneSacado' => '(11) 98765-4321',
            'linhaDigitavel' => '23791.23456 12345.678901 12345.678901 1 12345678901234',
            'codigoBarras' => '23791234561234567890112345678901234567890123',
            'nested' => [
                'documentoSacador' => '10987654321',
                'nomeSacadorAvalista' => 'Empresa Teste Ltda',
                'emailSacador' => 'sacador@example.com',
            ],
        ];

        $sanitized = BradescoPayloadSanitizer::sanitize($input);

        $this->assertMatchesRegularExpression('/^123\*+\d{3}$/', $sanitized['nuCpfcnpjPagador']);
        $this->assertMatchesRegularExpression('/^J\*+a$/', $sanitized['nomePagador']);
        $this->assertMatchesRegularExpression('/^p\*+r@example\.com$/', $sanitized['endEletronicoPagador']);
        $this->assertMatchesRegularExpression('/^\*+$/', $sanitized['dddFoneSacado']);
        $this->assertMatchesRegularExpression('/^11\*+21$/', $sanitized['foneSacado']);
        $this->assertMatchesRegularExpression('/^2379\*+\d{4}$/', $sanitized['linhaDigitavel']);
        $this->assertMatchesRegularExpression('/^2379\*+\d{4}$/', $sanitized['codigoBarras']);
        $this->assertArrayHasKey('nested', $sanitized);
        $this->assertMatchesRegularExpression('/^109\*+321$/', $sanitized['nested']['documentoSacador']);
        $this->assertMatchesRegularExpression('/^E\*+a$/', $sanitized['nested']['nomeSacadorAvalista']);
        $this->assertMatchesRegularExpression('/^s\*+r@example\.com$/', $sanitized['nested']['emailSacador']);
    }
}
