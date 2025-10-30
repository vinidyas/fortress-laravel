<?php

namespace App\Domain\Financeiro\Services;

use App\Events\Financeiro\AccountBalancesShouldRefresh;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

class JournalEntryStateService
{
    public function sync(JournalEntry $entry, ?JournalEntryStatus $previousStatus = null): JournalEntry
    {
        $entry->loadMissing(['installments', 'bankAccount']);

        $previousStatus ??= $entry->getOriginal('status')
            ? JournalEntryStatus::from((string) $entry->getOriginal('status'))
            : JournalEntryStatus::Planejado;

        $originalAmount = (float) ($entry->getOriginal('amount') ?? 0);

        $this->normalizeInstallments($entry);

        $newStatus = $this->determineStatus($entry);

        $paidInstallments = $entry->installments->filter(fn ($installment) => $installment->status === JournalEntryStatus::Pago->value);
        $paymentDate = $paidInstallments
            ->filter(fn ($installment) => $installment->payment_date !== null)
            ->map(fn ($installment) => Carbon::parse($installment->payment_date))
            ->sort()
            ->last();

        $entry->forceFill([
            'installments_count' => $entry->installments->count(),
            'paid_installments' => $paidInstallments->count(),
            'payment_date' => $paymentDate?->toDateString(),
            'status' => $newStatus->value,
        ])->save();

        $this->applyBalanceTransition($previousStatus, $newStatus, $entry->fresh(), $originalAmount);

        $refreshed = $entry->refresh(['installments', 'allocations', 'attachments', 'receipts']);

        event(new AccountBalancesShouldRefresh(array_filter([
            $refreshed->bank_account_id,
            $refreshed->counter_bank_account_id,
        ])));

        return $refreshed;
    }

    private function normalizeInstallments(JournalEntry $entry): void
    {
        $today = now()->startOfDay();

        $entry->installments->each(function ($installment) use ($today): void {
            if (
                in_array($installment->status, [JournalEntryStatus::Planejado->value, JournalEntryStatus::Pendente->value], true)
                && $installment->due_date
                && $installment->due_date->startOfDay()->lt($today)
            ) {
                $installment->status = JournalEntryStatus::Atrasado->value;
                $installment->save();
            }
        });
    }

    private function determineStatus(JournalEntry $entry): JournalEntryStatus
    {
        if ($entry->status === JournalEntryStatus::Cancelado->value) {
            return JournalEntryStatus::Cancelado;
        }

        if ($entry->installments->isEmpty()) {
            return JournalEntryStatus::Planejado;
        }

        if ($entry->installments->every(fn ($installment) => $installment->status === JournalEntryStatus::Cancelado->value)) {
            return JournalEntryStatus::Cancelado;
        }

        if ($entry->installments->every(fn ($installment) => $installment->status === JournalEntryStatus::Pago->value)) {
            return JournalEntryStatus::Pago;
        }

        if ($entry->installments->contains(fn ($installment) => $installment->status === JournalEntryStatus::Atrasado->value)) {
            return JournalEntryStatus::Atrasado;
        }

        return JournalEntryStatus::Pendente;
    }

    private function applyBalanceTransition(
        ?JournalEntryStatus $from,
        JournalEntryStatus $to,
        JournalEntry $entry,
        float $originalAmount
    ): void {
        if ($entry->origin === 'parcelado' && $entry->clone_of_id === null && $entry->installments_count > 1) {
            return;
        }

        if ($entry->type === 'transferencia') {
            $this->applyTransferBalanceTransition($from, $to, $entry, $originalAmount);
            return;
        }

        $account = $entry->bankAccount()->lockForUpdate()->first();

        if (! $account) {
            return;
        }

        $multiplier = match ($entry->type) {
            'receita' => 1,
            'despesa' => -1,
            default => 0,
        };

        if ($multiplier === 0) {
            return;
        }

        $currentAmount = (float) $entry->amount;

        if ($from === $to) {
            if ($to === JournalEntryStatus::Pago && abs($currentAmount - $originalAmount) > 0.00001) {
                $difference = ($currentAmount - $originalAmount) * $multiplier;
                $account->saldo_atual = ($account->saldo_atual ?? 0) + $difference;
                $account->save();
            }

            return;
        }

        if ($from !== JournalEntryStatus::Pago && $to === JournalEntryStatus::Pago) {
            $account->saldo_atual = ($account->saldo_atual ?? 0) + ($currentAmount * $multiplier);
            $account->save();

            return;
        }

        if ($from === JournalEntryStatus::Pago && $to !== JournalEntryStatus::Pago) {
            $account->saldo_atual = ($account->saldo_atual ?? 0) - ($originalAmount * $multiplier);
            $account->save();
        }
    }

    private function applyTransferBalanceTransition(
        ?JournalEntryStatus $from,
        JournalEntryStatus $to,
        JournalEntry $entry,
        float $originalAmount
    ): void {
        if ($entry->origin === 'parcelado' && $entry->clone_of_id === null && $entry->installments_count > 1) {
            return;
        }

        $originAccount = $entry->bankAccount()->lockForUpdate()->first();
        $destinationAccount = $entry->counterBankAccount()->lockForUpdate()->first();

        if (! $originAccount && ! $destinationAccount) {
            return;
        }

        $currentAmount = (float) $entry->amount;

        if ($from === $to) {
            if ($to === JournalEntryStatus::Pago && abs($currentAmount - $originalAmount) > 0.00001) {
                $difference = $currentAmount - $originalAmount;

                if ($originAccount) {
                    $originAccount->saldo_atual = ($originAccount->saldo_atual ?? 0) - $difference;
                    $originAccount->save();
                }

                if ($destinationAccount) {
                    $destinationAccount->saldo_atual = ($destinationAccount->saldo_atual ?? 0) + $difference;
                    $destinationAccount->save();
                }
            }

            return;
        }

        if ($from !== JournalEntryStatus::Pago && $to === JournalEntryStatus::Pago) {
            if ($originAccount) {
                $originAccount->saldo_atual = ($originAccount->saldo_atual ?? 0) - $currentAmount;
                $originAccount->save();
            }

            if ($destinationAccount) {
                $destinationAccount->saldo_atual = ($destinationAccount->saldo_atual ?? 0) + $currentAmount;
                $destinationAccount->save();
            }

            return;
        }

        if ($from === JournalEntryStatus::Pago && $to !== JournalEntryStatus::Pago) {
            if ($originAccount) {
                $originAccount->saldo_atual = ($originAccount->saldo_atual ?? 0) + $originalAmount;
                $originAccount->save();
            }

            if ($destinationAccount) {
                $destinationAccount->saldo_atual = ($destinationAccount->saldo_atual ?? 0) - $originalAmount;
                $destinationAccount->save();
            }
        }
    }
}
