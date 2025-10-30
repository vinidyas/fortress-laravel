<?php

namespace App\Domain\Financeiro\Services\Installment;

use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class GenerateInstallmentsService
{
    /**
     * @param  float  $amount Valor total
     * @param  int  $count Número de parcelas (>=1)
     * @param  string  $firstDueDate Data da primeira parcela (Y-m-d)
     * @param  float|null  $interest Total de juros a distribuir
     * @param  float|null  $discount Total de descontos a distribuir
     * @param  bool  $useEqualValues Se true, gera parcelas com valores iguais (última ajustada).
     * @return Collection<int,InstallmentData>
     */
    public function handle(
        float $amount,
        int $count,
        string $firstDueDate,
        ?float $interest = null,
        ?float $discount = null,
        bool $useEqualValues = true,
    ): Collection {
        if ($count < 1) {
            throw new InvalidArgumentException('O número de parcelas deve ser >= 1.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Valor total deve ser positivo.');
        }

        $interest = $interest ?? 0;
        $discount = $discount ?? 0;

        $basePerInstallment = $useEqualValues ? round($amount / $count, 2) : null;
        $installments = collect();
        $dueDate = Carbon::parse($firstDueDate)->startOfDay();

        $remainingPrincipal = $amount;
        $remainingInterest = $interest;
        $remainingDiscount = $discount;

        for ($i = 1; $i <= $count; $i++) {
            if ($useEqualValues) {
                $principal = $i === $count ? round($remainingPrincipal, 2) : $basePerInstallment;
            } else {
                $principal = round($remainingPrincipal / ($count - $i + 1), 2);
            }

            $remainingPrincipal = round($remainingPrincipal - $principal, 2);

            $parcelaInterest = $i === $count ? round($remainingInterest, 2) : round($interest / $count, 2);
            $remainingInterest = round($remainingInterest - $parcelaInterest, 2);

            $parcelaDiscount = $i === $count ? round($remainingDiscount, 2) : round($discount / $count, 2);
            $remainingDiscount = round($remainingDiscount - $parcelaDiscount, 2);

            $valorTotal = $principal + $parcelaInterest - $parcelaDiscount;

            if ($valorTotal <= 0) {
                throw new InvalidArgumentException('Valor da parcela não pode ser zero ou negativo.');
            }

            $installments->push(new InstallmentData(
                numeroParcela: $i,
                movementDate: $dueDate->toDateString(),
                dueDate: $dueDate->toDateString(),
                paymentDate: null,
                valorPrincipal: $principal,
                valorJuros: $parcelaInterest,
                valorMulta: 0,
                valorDesconto: $parcelaDiscount,
                valorTotal: $valorTotal,
                status: JournalEntryStatus::Planejado,
                meta: null,
            ));

            $dueDate = $dueDate->copy()->addMonth();
        }

        return $installments;
    }
}
