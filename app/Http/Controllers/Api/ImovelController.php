<?php

namespace App\Http\Controllers\Api;

use App\Actions\Imovel\GenerateCodigo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imovel\ImovelStoreRequest;
use App\Http\Requests\Imovel\ImovelUpdateRequest;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use App\Models\ImovelAnexo;
use App\Models\ImovelFoto;
use App\Services\ImovelFotoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use ZipStream\ZipStream;

class ImovelController extends Controller
{
    private const MAX_PHOTOS = 15;

    public function __construct(private readonly ImovelFotoService $fotoService)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Imovel::class);

        $query = Imovel::query()->with([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'contratos:id,imovel_id,codigo_contrato,status',
        ])->withCount(['anexos', 'fotos']);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $imoveis = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['codigo', 'cidade', 'valor_locacao', 'created_at'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($builder, $value) {
                    $value = is_array($value) ? implode(' ', $value) : $value;
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $decimalSearch = str_replace(['.', ' '], '', $value);
                    $decimalSearch = str_replace(',', '.', $decimalSearch);
                    $isNumericSearch = $decimalSearch !== '' && is_numeric($decimalSearch);

                    $builder->where(function ($query) use ($value, $decimalSearch, $isNumericSearch) {
                        $query->where('codigo', 'like', "%{$value}%")
                            ->orWhere('cidade', 'like', "%{$value}%")
                            ->orWhere('bairro', 'like', "%{$value}%")
                            ->orWhere('rua', 'like', "%{$value}%")
                            ->orWhere('logradouro', 'like', "%{$value}%")
                            ->orWhere('complemento', 'like', "%{$value}%")
                            ->orWhereHas('condominio', fn ($q) => $q->where('nome', 'like', "%{$value}%"));

                        if ($isNumericSearch) {
                            $query->orWhere('valor_locacao', 'like', "%{$decimalSearch}%");
                        }
                    });
                }),
                AllowedFilter::exact('tipo_imovel'),
                AllowedFilter::exact('disponibilidade'),
                AllowedFilter::exact('cidade'),
                AllowedFilter::callback('finalidade', function ($builder, $value) {
                    $values = collect(is_array($value) ? $value : [$value])
                        ->filter()
                        ->map(fn ($item) => ucfirst(mb_strtolower((string) $item)))
                        ->unique()
                        ->values();

                    if ($values->isEmpty()) {
                        return;
                    }

                    $builder->where(function ($q) use ($values) {
                        foreach ($values as $item) {
                            $q->orWhereJsonContains('finalidade', $item);
                        }
                    });
                }),
            ])
            ->paginate($perPage)
            ->appends($request->query());

        return ImovelResource::collection($imoveis);
    }

    public function generateCodigo(GenerateCodigo $generateCodigo)
    {
        $this->authorize('create', Imovel::class);

        return response()->json([
            'codigo' => $generateCodigo->generate(),
        ]);
    }

    public function show(Imovel $imovel)
    {
        $this->authorize('view', $imovel);

        return new ImovelResource($imovel->load([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'anexos.uploader',
            'fotos',
        ]));
    }

    public function store(ImovelStoreRequest $request, GenerateCodigo $generateCodigo)
    {
        $this->authorize('create', Imovel::class);

        $data = $request->validated();
        if (empty($data['codigo'])) {
            $data['codigo'] = $generateCodigo->generate();
        }

        $imovel = Imovel::query()->create($data);

        $this->syncAttachments($imovel, $request);
        $this->syncPhotos($imovel, $request);

        $imovel->refresh()->load([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'anexos.uploader',
            'fotos',
        ]);

        return (new ImovelResource($imovel))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ImovelUpdateRequest $request, Imovel $imovel)
    {
        $this->authorize('update', $imovel);

        $data = $request->validated();
        if (empty($data['codigo'])) {
            $data['codigo'] = $imovel->codigo;
        }

        $imovel->update($data);
        $this->syncAttachments($imovel, $request);
        $this->syncPhotos($imovel, $request);

        $imovel->refresh()->load([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'anexos.uploader',
            'fotos',
        ]);

        return new ImovelResource($imovel);
    }

    public function destroy(Imovel $imovel)
    {
        $this->authorize('delete', $imovel);

        try {
            $imovel->delete();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                return response()->json([
                    'message' => 'Não é possível excluir o imóvel porque existem contratos vinculados. Finalize ou mova os contratos antes de tentar novamente.',
                ], Response::HTTP_CONFLICT);
            }

            throw $exception;
        }

        return response()->noContent();
    }

    private function syncAttachments(Imovel $imovel, Request $request): void
    {
        $removeIds = collect($request->input('anexos_remover', []))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($removeIds->isNotEmpty()) {
            $imovel->anexos()
                ->whereIn('id', $removeIds)
                ->get()
                ->each(function (ImovelAnexo $anexo) {
                    Storage::disk('public')->delete($anexo->path);
                    $anexo->delete();
                });
        }

        $existingLabels = $request->input('anexos_legendas_existentes', []);
        if (is_array($existingLabels) && $existingLabels !== []) {
            $attachments = $imovel->anexos()->whereIn('id', array_keys($existingLabels))->get()->keyBy('id');
            foreach ($existingLabels as $id => $label) {
                $attachment = $attachments->get((int) $id);
                if (! $attachment) {
                    continue;
                }
                $label = is_string($label) ? trim($label) : '';
                $attachment->display_name = $label !== '' ? mb_substr($label, 0, 255) : $attachment->original_name;
                $attachment->save();
            }
        }

        if (! $request->hasFile('anexos')) {
            return;
        }

        $labels = $request->input('anexos_legendas', []);
        $userId = $request->user()?->id;

        foreach ((array) $request->file('anexos', []) as $index => $uploadedFile) {
            if (! $uploadedFile instanceof UploadedFile) {
                continue;
            }

            $path = $uploadedFile->store("imoveis/{$imovel->id}", 'public');
            $label = is_array($labels) && array_key_exists($index, $labels) ? $labels[$index] : null;
            $displayName = is_string($label) ? trim($label) : '';
            $displayName = $displayName !== '' ? mb_substr($displayName, 0, 255) : $uploadedFile->getClientOriginalName();

            $imovel->anexos()->create([
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'display_name' => $displayName,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'uploaded_by' => $userId,
            ]);
        }
    }

    private function syncPhotos(Imovel $imovel, Request $request): void
    {
        $removeIds = collect($request->input('fotos_remover', []))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($removeIds->isNotEmpty()) {
            $imovel->fotos()
                ->whereIn('id', $removeIds)
                ->get()
                ->each(function (ImovelFoto $foto) {
                    $this->fotoService->delete($foto);
                });
        }

        $existingPhotos = $imovel->fotos()->get()->keyBy('id');

        $existingLegendas = $request->input('fotos_legendas_existentes', []);
        if (is_array($existingLegendas) && $existingLegendas !== []) {
            foreach ($existingLegendas as $id => $legenda) {
                $photo = $existingPhotos->get((int) $id);
                if (! $photo) {
                    continue;
                }
                $trimmed = is_string($legenda) ? trim($legenda) : '';
                $photo->legenda = $trimmed !== '' ? mb_substr($trimmed, 0, 255) : null;
                $photo->save();
            }
        }

        $newFiles = collect((array) $request->file('fotos', []))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        $newIds = is_array($request->input('fotos_ids')) ? array_values($request->input('fotos_ids')) : [];
        $newLegendas = is_array($request->input('fotos_legendas')) ? array_values($request->input('fotos_legendas')) : [];

        $newPhotos = [];
        foreach ($newFiles as $index => $file) {
            $tempId = $newIds[$index] ?? null;
            if (! is_string($tempId) || $tempId === '') {
                $tempId = Str::uuid()->toString();
            }

            $newPhotos[$tempId] = [
                'file' => $file,
                'legenda' => $newLegendas[$index] ?? null,
                'ordem' => null,
            ];
        }

        $currentCount = $existingPhotos->count();
        $newCount = count($newPhotos);
        if (($currentCount + $newCount) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'fotos' => ["Limite máximo de ".self::MAX_PHOTOS." fotos por imóvel atingido."],
            ]);
        }

        $orderEntries = collect($request->input('fotos_ordem', []))
            ->filter(fn ($value) => is_string($value) && str_contains($value, ':'))
            ->map(function ($value) {
                [$type, $identifier] = explode(':', (string) $value, 2);

                return [
                    'type' => $type === 'existing' ? 'existing' : ($type === 'new' ? 'new' : null),
                    'id' => $identifier,
                ];
            })
            ->filter(fn ($entry) => $entry['type'] !== null && is_string($entry['id']) && $entry['id'] !== '')
            ->values();

        $existingOrderAssignments = [];
        $newOrderAssignments = [];
        $order = 1;

        foreach ($orderEntries as $entry) {
            if ($entry['type'] === 'existing') {
                $photoId = (int) $entry['id'];
                if (! $existingPhotos->has($photoId) || isset($existingOrderAssignments[$photoId])) {
                    continue;
                }
                $existingOrderAssignments[$photoId] = $order++;
            } elseif ($entry['type'] === 'new') {
                $tempId = (string) $entry['id'];
                if (! array_key_exists($tempId, $newPhotos) || isset($newOrderAssignments[$tempId])) {
                    continue;
                }
                $newOrderAssignments[$tempId] = $order++;
            }
        }

        foreach ($existingPhotos as $id => $photo) {
            if (isset($existingOrderAssignments[$id])) {
                continue;
            }
            $existingOrderAssignments[$id] = $order++;
        }

        foreach ($newPhotos as $tempId => $info) {
            if (isset($newOrderAssignments[$tempId])) {
                continue;
            }
            $newOrderAssignments[$tempId] = $order++;
        }

        foreach ($existingOrderAssignments as $id => $ordem) {
            $photo = $existingPhotos->get($id);
            if (! $photo) {
                continue;
            }
            if ($photo->ordem !== $ordem) {
                $photo->ordem = $ordem;
                $photo->save();
            }
        }

        foreach ($newPhotos as $tempId => $info) {
            $ordem = $newOrderAssignments[$tempId] ?? null;
            if ($ordem === null) {
                continue;
            }

            $legenda = is_string($info['legenda']) ? trim($info['legenda']) : null;
            $this->fotoService->store(
                $imovel,
                $info['file'],
                $ordem,
                $legenda !== '' ? $legenda : null
            );
        }
    }

    public function downloadPhotos(Imovel $imovel)
    {
        $this->authorize('view', $imovel);

        $photos = $imovel->fotos()->orderBy('ordem')->get();

        if ($photos->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma foto disponível para download.',
            ], Response::HTTP_NOT_FOUND);
        }

        $filename = $this->buildPhotosZipFilename($imovel);
        $storage = Storage::disk('public');

        return response()->streamDownload(function () use ($photos, $storage) {
            $zip = new ZipStream(sendHttpHeaders: false);

            foreach ($photos as $photo) {
                if (! $storage->exists($photo->path)) {
                    continue;
                }

                $stream = $storage->readStream($photo->path);
                if (! $stream) {
                    continue;
                }

                $zip->addFileFromStream(
                    $this->buildPhotoFilename($photo),
                    $stream
                );

                fclose($stream);
            }

            $zip->finish();
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function buildPhotosZipFilename(Imovel $imovel): string
    {
        $codigo = $imovel->codigo ? Str::slug($imovel->codigo) : null;
        $timestamp = now()->format('Ymd_His');

        $basename = $codigo !== null && $codigo !== '' ? "imovel-{$codigo}" : "imovel-{$imovel->id}";

        return "{$basename}-fotos-{$timestamp}.zip";
    }

    private function buildPhotoFilename(ImovelFoto $foto): string
    {
        $basename = $foto->legenda ?: $foto->original_name ?: "foto-{$foto->id}";
        $basename = Str::slug(pathinfo($basename, PATHINFO_FILENAME));
        $extension = pathinfo($foto->path, PATHINFO_EXTENSION) ?: 'jpg';

        if ($basename === '') {
            $basename = "foto-{$foto->id}";
        }

        return "{$basename}.{$extension}";
    }
}
