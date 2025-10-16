<?php

namespace App\Http\Controllers\Api;

use App\Actions\Imovel\GenerateCodigo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imovel\ImovelStoreRequest;
use App\Http\Requests\Imovel\ImovelUpdateRequest;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use App\Models\ImovelAnexo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ImovelController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Imovel::class);

        $query = Imovel::query()->with([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'contratos:id,imovel_id,codigo_contrato,status',
        ])->withCount('anexos');

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

        $imovel->refresh()->load([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'anexos.uploader',
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

        $imovel->refresh()->load([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
            'anexos.uploader',
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
}
