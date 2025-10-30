<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\JournalEntryAttachmentResource;
use App\Models\JournalEntry;
use App\Models\JournalEntryAttachment;
use App\Models\JournalEntryInstallment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalEntryAttachmentController extends Controller
{
    public function index(JournalEntry $journalEntry): AnonymousResourceCollection
    {
        $this->authorize('view', $journalEntry);

        $attachments = $journalEntry->attachments()->with('uploadedBy')->latest()->get();

        return JournalEntryAttachmentResource::collection($attachments);
    }

    public function store(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('update', $journalEntry);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'installment_id' => ['nullable', 'integer', 'exists:journal_entry_installments,id'],
        ]);

        $installmentId = $validated['installment_id'] ?? null;

        if ($installmentId) {
            /** @var JournalEntryInstallment|null $installment */
            $installment = $journalEntry->installments()->find($installmentId);
            if (! $installment) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Parcela informada não pertence ao lançamento.');
            }
        }

        $file = $request->file('file');
        $disk = Storage::disk(config('filesystems.default'));
        $directory = sprintf('financeiro/attachments/%d', $journalEntry->id);
        $filename = sprintf('%s-%s', Str::uuid()->toString(), $file->getClientOriginalName());
        $path = trim($directory, '/').'/'.$filename;

        $disk->put($path, $file->getContent());

        /** @var JournalEntryAttachment $attachment */
        $attachment = $journalEntry->attachments()->create([
            'installment_id' => $validated['installment_id'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'uploaded_by' => $request->user()?->id,
        ]);

        return JournalEntryAttachmentResource::make($attachment->load('uploadedBy'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(JournalEntry $journalEntry, JournalEntryAttachment $attachment): Response
    {
        $this->authorize('update', $journalEntry);
        $this->assertAttachmentBelongsToEntry($journalEntry, $attachment);

        $disk = Storage::disk(config('filesystems.default'));
        if ($attachment->file_path && $disk->exists($attachment->file_path)) {
            $disk->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->noContent();
    }

    public function download(JournalEntry $journalEntry, JournalEntryAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $journalEntry);
        $this->assertAttachmentBelongsToEntry($journalEntry, $attachment);

        $disk = Storage::disk(config('filesystems.default'));
        if (! $attachment->file_path || ! $disk->exists($attachment->file_path)) {
            abort(Response::HTTP_NOT_FOUND, 'Arquivo não encontrado.');
        }

        return $disk->download($attachment->file_path, $attachment->file_name ?? 'anexo');
    }

    private function assertAttachmentBelongsToEntry(JournalEntry $entry, JournalEntryAttachment $attachment): void
    {
        if ($attachment->journal_entry_id !== $entry->id) {
            abort(Response::HTTP_NOT_FOUND, 'Anexo não pertence ao lançamento informado.');
        }
    }
}
