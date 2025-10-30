<?php

namespace Tests\Feature\Financeiro;

use App\Domain\Financeiro\Services\Reconciliation\ImportBankStatementService;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportBankStatementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_csv_and_creates_lines(): void
    {
        Storage::fake('local');

        $account = FinancialAccount::factory()->create();

        $csv = <<<CSV
Data,Descricao,Valor,Saldo
2025-01-01,Pagamento Cliente,1500.50,5000.00
2025-01-05,Pagamento Fornecedor,-300.00,4700.00
CSV;

        $file = UploadedFile::fake()->createWithContent('extrato.csv', $csv);

        /** @var ImportBankStatementService $service */
        $service = app(ImportBankStatementService::class);

        $statement = $service->handle($account->id, $file);

        $this->assertSame('importado', $statement->status);
        $this->assertCount(2, $statement->lines);
        $this->assertSame(1500.50, (float) $statement->lines[0]->amount);
        $this->assertSame(-300.00, (float) $statement->lines[1]->amount);
        $this->assertEquals(3499.50, round((float) ($statement->meta['opening_balance'] ?? 0), 2));
        $this->assertEquals(4700.00, round((float) ($statement->meta['closing_balance'] ?? 0), 2));
    }
}
