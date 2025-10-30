<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fatura\FaturaAttachmentStoreRequest;
use App\Http\Resources\FaturaAttachmentResource;
use App\Models\Fatura;
use App\Models\FaturaAnexo;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FaturaAttachmentController extends Controller
{
    public function store(FaturaAttachmentStoreRequest $request, Fatura $fatura)
    {
        $this->authorize('update', $fatura);

        $labels = $request->labels();
        $userId = $request->user()?->id;

        $createdIds = collect();

        $files = (array) $request->file('attachments', []);

        foreach ($files as $index => $uploadedFile) {
            if (! $uploadedFile instanceof UploadedFile) {
                continue;
            }

            if (! $uploadedFile->isValid()) {
                throw ValidationException::withMessages([
                    "attachments.$index" => $uploadedFile->getErrorMessage() ?: 'Falha ao enviar o arquivo. Tente novamente.',
                ]);
            }

            $path = $uploadedFile->store("faturas/{$fatura->id}", 'public');
            $displayName = $labels[$index] ?? null;
            $displayName = $displayName ? mb_substr($displayName, 0, 255) : null;

            $createdIds->push($fatura->anexos()->create([
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'display_name' => $displayName,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'uploaded_by' => $userId,
            ])->getKey());
        }

        if ($createdIds->isEmpty()) {
            return FaturaAttachmentResource::collection(collect())->additional([
                'meta' => [
                    'message' => 'Nenhum arquivo válido enviado.',
                ],
            ])->response()->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $attachments = FaturaAnexo::query()
            ->whereIn('id', $createdIds)
            ->with('uploader')
            ->get();

        return FaturaAttachmentResource::collection($attachments)
            ->additional([
                'meta' => [
                    'message' => 'Anexo(s) adicionados à fatura com sucesso.',
                ],
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Fatura $fatura, int $attachment)
    {
        $this->authorize('update', $fatura);

        $attachmentRecord = $fatura->anexos()->whereKey($attachment)->first();

        if (! $attachmentRecord) {
            abort(Response::HTTP_NOT_FOUND);
        }

        Storage::disk('public')->delete($attachmentRecord->path);
        $attachmentRecord->delete();

        return response()->noContent();
    }

    public function rename(Fatura $fatura, int $attachment)
    {
        $this->authorize('update', $fatura);

        $fatura->loadMissing(['contrato.imovel.condominio']);

        $attachmentRecord = $fatura->anexos()->whereKey($attachment)->first();

        if (! $attachmentRecord) {
            abort(Response::HTTP_NOT_FOUND);
        }

        [$originalBase, $originalExtension] = $this->extractNameParts(
            $attachmentRecord->original_name,
            $attachmentRecord->path
        );

        $extension = $originalExtension;

        $displayBase = $this->buildDisplayName($fatura);
        $sanitizedBase = $this->sanitizeForPath($displayBase);

        [$displayFull, $newPath] = $this->resolveUniqueNames(
            $fatura->id,
            $displayBase,
            $sanitizedBase,
            $extension,
            $attachmentRecord->path,
            true
        );

        if ($attachmentRecord->path !== $newPath) {
            Storage::disk('public')->move($attachmentRecord->path, $newPath);
        }

        $attachmentRecord->update([
            'path' => $newPath,
            'display_name' => $displayFull,
        ]);

        return (new FaturaAttachmentResource($attachmentRecord->fresh('uploader')))
            ->additional([
                'meta' => [
                    'message' => 'Nome padrão aplicado ao anexo.',
                ],
            ]);
    }

    public function resetName(Fatura $fatura, int $attachment)
    {
        $this->authorize('update', $fatura);

        $attachmentRecord = $fatura->anexos()->whereKey($attachment)->first();

        if (! $attachmentRecord) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $original = $attachmentRecord->getOriginal('original_name') ?? $attachmentRecord->original_name;

        if (! $original) {
            return (new FaturaAttachmentResource($attachmentRecord->fresh('uploader')))
                ->additional([
                    'meta' => [
                        'message' => 'O anexo já está com o nome original.',
                    ],
                ]);
        }

        [$base, $extension] = $this->extractNameParts($original, $attachmentRecord->path);

        $sanitizedBase = $this->sanitizeForPath($base);

        [$displayFull, $newPath] = $this->resolveUniqueNames(
            $fatura->id,
            $base,
            $sanitizedBase,
            $extension,
            $attachmentRecord->path,
            false
        );

        if ($attachmentRecord->path !== $newPath) {
            Storage::disk('public')->move($attachmentRecord->path, $newPath);
        }

        $attachmentRecord->update([
            'path' => $newPath,
            'display_name' => $displayFull,
        ]);

        return (new FaturaAttachmentResource($attachmentRecord->fresh('uploader')))
            ->additional([
                'meta' => [
                    'message' => 'Nome original restaurado.',
                ],
            ]);
    }

    protected function buildDisplayName(Fatura $fatura): string
    {
        $competencia = optional($fatura->competencia)->format('m/y') ?: '';
        $contratoCodigo = $fatura->contrato?->codigo_contrato ?: (string) $fatura->contrato_id;
        $imovelLabel = $this->formatImovelLabel($fatura);

        $parts = array_filter([
            'Boleto',
            $contratoCodigo,
            $imovelLabel,
            $competencia,
        ], fn ($value) => filled($value));

        return implode(' - ', $parts);
    }

    protected function formatImovelLabel(Fatura $fatura): ?string
    {
        $imovel = $fatura->contrato?->imovel;

        if (! $imovel) {
            return null;
        }

        $base = trim((string) ($imovel->condominio?->nome ?? ''));
        $fallback = $base !== '' ? $base : 'Sem condomínio';
        $complemento = trim((string) ($imovel->complemento ?? ''));

        return $complemento !== '' ? sprintf('%s — %s', $fallback, $complemento) : $fallback;
    }

    protected function sanitizeForPath(string $value): string
    {
        $ascii = Str::ascii($value);
        $normalized = preg_replace('/[^\w\s.-]+/', '', $ascii) ?: '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        if ($normalized === '') {
            return 'anexo';
        }

        return str_replace(' ', '_', $normalized);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveUniqueNames(
        int $faturaId,
        string $displayBase,
        string $sanitizedBase,
        ?string $extension,
        string $currentPath,
        bool $allowDisplaySuffix = true
    ): array {
        $disk = Storage::disk('public');

        $counter = 0;
        $displayName = $displayBase;
        $sanitizedName = $sanitizedBase;

        do {
            $candidateDisplay = $allowDisplaySuffix && $counter > 0
                ? sprintf('%s - %d', $displayName, $counter + 1)
                : $displayName;
            $candidateSanitized = $counter > 0 ? sprintf('%s-%d', $sanitizedName, $counter + 1) : $sanitizedName;

            $candidateFilename = $extension
                ? sprintf('%s.%s', $candidateSanitized, $extension)
                : $candidateSanitized;

            $candidatePath = sprintf('faturas/%d/%s', $faturaId, $candidateFilename);

            if ($candidatePath === $currentPath || ! $disk->exists($candidatePath)) {
                $finalDisplayBase = $allowDisplaySuffix ? $candidateDisplay : $displayName;

                $finalDisplay = $extension
                    ? sprintf('%s.%s', $finalDisplayBase, $extension)
                    : $finalDisplayBase;

                return [$finalDisplay, $candidatePath];
            }

            $counter++;
        } while ($counter < 100);

        $uniqueSuffix = uniqid();
        $fallbackFilename = $extension
            ? sprintf('%s_%s.%s', $sanitizedBase, $uniqueSuffix, $extension)
            : sprintf('%s_%s', $sanitizedBase, $uniqueSuffix);

        return [
            $extension ? sprintf('%s.%s', $displayBase, $extension) : $displayBase,
            sprintf('faturas/%d/%s', $faturaId, $fallbackFilename),
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function extractNameParts(?string $name, string $path): array
    {
        $trimmed = $name ? trim($name) : '';

        $extension = pathinfo($trimmed, PATHINFO_EXTENSION);

        if ($extension === '') {
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: null;
        }

        $base = pathinfo($trimmed, PATHINFO_FILENAME);

        if ($base === '' || $base === null) {
            $base = $extension
                ? basename($trimmed, '.'.$extension)
                : $trimmed;
        }

        if ($base === '' || $base === null) {
            $base = pathinfo($path, PATHINFO_FILENAME) ?: 'anexo';
        }

        return [$base, $extension];
    }
}
