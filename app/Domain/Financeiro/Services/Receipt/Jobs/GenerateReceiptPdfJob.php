<?php

namespace App\Domain\Financeiro\Services\Receipt\Jobs;

use App\Models\FinancialReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateReceiptPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $receiptId)
    {
        $this->onQueue('receipts');
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        /** @var FinancialReceipt|null $receipt */
        $receipt = FinancialReceipt::query()
            ->with([
                'journalEntry.bankAccount',
                'journalEntry.person',
                'journalEntry.costCenter',
                'installment',
                'issuedBy',
            ])
            ->find($this->receiptId);

        if (! $receipt) {
            throw new RuntimeException("Recibo {$this->receiptId} não encontrado.");
        }

        $entry = $receipt->journalEntry;
        if (! $entry) {
            throw new RuntimeException('Recibo sem lançamento vinculado.');
        }

        $amount = $receipt->installment ? (float) $receipt->installment->valor_total : (float) $entry->amount;

        $data = [
            'receipt' => $receipt,
            'entry' => $entry,
            'installment' => $receipt->installment,
            'person' => $entry->person,
            'costCenter' => $entry->costCenter,
            'account' => $entry->bankAccount,
            'amount' => $amount,
            'amount_formatted' => number_format($amount, 2, ',', '.'),
            'issueDateFormatted' => $receipt->issue_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
        ];

        $pdf = Pdf::loadView('pdf.financial-receipt', $data)->setPaper('a4');

        $disk = Storage::disk(config('filesystems.default'));
        $directory = sprintf('financeiro/receipts/%d', $entry->id);
        $filename = sprintf('recibo-%d-%s.pdf', $receipt->id, Str::slug($receipt->number ?? 'recibo'));
        $path = trim($directory, '/').'/'.$filename;

        $disk->put($path, $pdf->output());

        $receipt->update([
            'pdf_path' => $path,
            'metadata' => array_merge($receipt->metadata ?? [], [
                'status' => 'generated',
                'generated_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
