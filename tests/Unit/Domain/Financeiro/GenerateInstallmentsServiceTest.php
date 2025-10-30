<?php

namespace Tests\Unit\Domain\Financeiro;

use App\Domain\Financeiro\Services\Installment\GenerateInstallmentsService;
use PHPUnit\Framework\TestCase;

class GenerateInstallmentsServiceTest extends TestCase
{
    public function test_generates_equal_installments(): void
    {
        $service = new GenerateInstallmentsService();

        $installments = $service->handle(
            amount: 1000,
            count: 4,
            firstDueDate: '2025-01-10',
        );

        $this->assertCount(4, $installments);
        $this->assertSame('2025-01-10', $installments[0]->dueDate);
        $this->assertSame('2025-02-10', $installments[1]->dueDate);
        $this->assertSame(250.0, $installments[0]->valorPrincipal);
        $this->assertSame(250.0, $installments[3]->valorPrincipal);
    }

    public function test_generates_installments_with_interest_and_discount(): void
    {
        $service = new GenerateInstallmentsService();

        $installments = $service->handle(
            amount: 900,
            count: 3,
            firstDueDate: '2025-01-01',
            interest: 30,
            discount: 15,
        );

        $this->assertSame(305.0, $installments[0]->valorTotal);
        $this->assertSame(305.0, $installments[1]->valorTotal);
        $this->assertSame(305.0, $installments[2]->valorTotal);
    }

    public function test_throws_on_invalid_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GenerateInstallmentsService())->handle(
            amount: 0,
            count: 2,
            firstDueDate: '2025-01-01'
        );
    }
}
