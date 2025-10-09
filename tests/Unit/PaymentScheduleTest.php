<?php

namespace Tests\Unit;

use App\Models\PaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
    }

    public function test_nao_marca_em_atraso_no_dia_do_vencimento(): void
    {
        Carbon::setTestNow('2025-01-10 10:00:00');

        $schedule = PaymentSchedule::factory()->create([
            'vencimento' => Carbon::today(),
            'status' => 'aberto',
            'parcela_atual' => 1,
            'total_parcelas' => 2,
        ]);

        $this->assertSame('aberto', $schedule->fresh()->status);
    }

    public function test_marca_em_atraso_para_vencimento_passado(): void
    {
        Carbon::setTestNow('2025-01-10 10:00:00');

        $schedule = PaymentSchedule::factory()->create([
            'vencimento' => Carbon::yesterday(),
            'status' => 'aberto',
            'parcela_atual' => 1,
            'total_parcelas' => 2,
        ]);

        $this->assertSame('em_atraso', $schedule->fresh()->status);
    }
}
