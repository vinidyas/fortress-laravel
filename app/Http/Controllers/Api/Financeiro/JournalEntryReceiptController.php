<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\FinancialReceiptResource;
use App\Models\FinancialReceipt;
use App\Models\JournalEntry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalEntryReceiptController extends Controller
{
    public function index(JournalEntry $journalEntry): AnonymousResourceCollection
    {
        $this->authorize('view', $journalEntry);

        $receipts = $journalEntry->receipts()->with(['issuedBy'])->latest()->get();

        return FinancialReceiptResource::collection($receipts);
    }

    public function download(JournalEntry $journalEntry, FinancialReceipt $receipt): StreamedResponse
    {
        $this->authorize('view', $journalEntry);

        if ($receipt->journal_entry_id !== $journalEntry->id) {
            abort(Response::HTTP_NOT_FOUND, 'Recibo não pertence ao lançamento informado.');
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $receipt->pdf_path || ! $disk->exists($receipt->pdf_path)) {
            abort(Response::HTTP_NOT_FOUND, 'Arquivo de recibo não disponível.');
        }

        $filename = $receipt->number ? Str::slug($receipt->number, '_').'.pdf' : 'recibo.pdf';

        return $disk->download($receipt->pdf_path, (string) $filename);
    }
}
