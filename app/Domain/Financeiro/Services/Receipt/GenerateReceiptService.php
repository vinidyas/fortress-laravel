<?php

namespace App\Domain\Financeiro\Services\Receipt;

use App\Domain\Financeiro\Services\Receipt\Jobs\GenerateReceiptPdfJob;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Models\FinancialReceipt;
use App\Models\JournalEntry;
use App\Models\JournalEntryInstallment;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GenerateReceiptService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function handle(JournalEntry $entry, ?JournalEntryInstallment $installment = null): FinancialReceipt
    {
        if ($entry->status !== JournalEntryStatus::Pago->value) {
            throw new InvalidArgumentException('Somente lançamentos pagos permitem gerar recibo.');
        }

        if ($installment && $installment->journal_entry_id !== $entry->id) {
            throw new InvalidArgumentException('Parcela informada não pertence ao lançamento.');
        }

        if ($installment && $installment->status !== JournalEntryStatus::Pago->value) {
            throw new InvalidArgumentException('Parcela ainda não está quitada.');
        }

        return $this->database->transaction(function () use ($entry, $installment) {
            /** @var FinancialReceipt $receipt */
            $receipt = FinancialReceipt::query()->create([
                'journal_entry_id' => $entry->id,
                'installment_id' => $installment?->id,
                'number' => $this->makeNumber(),
                'issue_date' => now()->toDateString(),
                'issued_by' => Auth::user()?->getKey(),
                'pdf_path' => '',
                'metadata' => [
                    'status' => 'processing',
                ],
            ]);

            GenerateReceiptPdfJob::dispatch($receipt->id);

            return $receipt->fresh();
        });
    }

    private function makeNumber(): string
    {
        return sprintf('RC-%s-%s', now()->format('Ymd'), Str::padLeft((string) (FinancialReceipt::query()->count() + 1), 4, '0'));
    }
}
