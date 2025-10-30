<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Contrato\GenerateCodigo as GenerateContratoCodigo;
use App\Enums\ContratoGarantiaTipo;
use App\Enums\ContratoReajusteIndice;
use App\Enums\ContratoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contrato\ContratoStoreRequest;
use App\Http\Requests\Contrato\ContratoUpdateRequest;
use App\Http\Resources\ContratoResource;
use App\Models\Contrato;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContratoController extends Controller
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Contrato::class);

        $query = Contrato::query()->with(['imovel.condominio', 'locador', 'locatario']);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $contratos = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['codigo_contrato', 'data_inicio', 'data_fim', 'created_at'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($builder, $value) {
                    $value = is_array($value) ? implode(' ', $value) : $value;
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->where('codigo_contrato', 'like', "%{$value}%");
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('imovel_id'),
                AllowedFilter::exact('locador_id'),
                AllowedFilter::exact('locatario_id'),
                AllowedFilter::exact('conta_cobranca_id'),
                AllowedFilter::callback('reajuste_indice', function ($builder, $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->where('reajuste_indice', $value);
                }),
                AllowedFilter::exact('dia_vencimento'),
                AllowedFilter::callback('cidade', function ($builder, $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->whereHas('imovel', fn ($query) => $query->where('cidade', 'like', "%{$value}%"));
                }),
                AllowedFilter::callback('vigencia_em', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }

                    $date = Carbon::parse($value);
                    $builder->where('data_inicio', '<=', $date)
                        ->where(function ($query) use ($date) {
                            $query->whereNull('data_fim')
                                ->orWhere('data_fim', '>=', $date);
                        });
                }),
                AllowedFilter::callback('vigencia_de', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }

                    $builder->where('data_inicio', '>=', Carbon::parse($value));
                }),
                AllowedFilter::callback('vigencia_ate', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }

                    $builder->where(function ($query) use ($value) {
                        $date = Carbon::parse($value);
                        $query->whereNull('data_fim')
                            ->orWhere('data_fim', '<=', $date);
                    });
                }),
            ])
            ->paginate($perPage)
            ->appends($request->query());

        return ContratoResource::collection($contratos);
    }

    public function generateCodigo(GenerateContratoCodigo $generateCodigo)
    {
        $this->authorize('create', Contrato::class);

        return response()->json([
            'codigo' => $generateCodigo->generate(),
        ]);
    }

    public function show(Contrato $contrato)
    {
        $this->authorize('view', $contrato);

        return new ContratoResource($contrato->load(['imovel.condominio', 'locador', 'locatario', 'fiadores', 'contaCobranca', 'anexos']));
    }

    public function store(ContratoStoreRequest $request)
    {
        $this->authorize('create', Contrato::class);

        return $this->db->transaction(function () use ($request) {
            $this->ensureUniqueActiveContrato(
                $request->input('imovel_id'),
                $request->input('status'),
                $request->input('data_fim')
            );

            [$payload, $fiadores] = $this->extractContratoData($request);

            $contrato = Contrato::query()->create($payload);

            if ($fiadores->isNotEmpty()) {
                $contrato->fiadores()->sync($fiadores);
            }

            $this->storeAnexos($contrato, $request);

            return (new ContratoResource($contrato->load(['imovel.condominio', 'locador', 'locatario', 'fiadores', 'contaCobranca', 'anexos'])))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        });
    }

    public function update(ContratoUpdateRequest $request, Contrato $contrato)
    {
        $this->authorize('update', $contrato);

        return $this->db->transaction(function () use ($request, $contrato) {
            $this->ensureUniqueActiveContrato(
                $request->input('imovel_id'),
                $request->input('status'),
                $request->input('data_fim'),
                $contrato->id,
                $contrato
            );

            [$payload, $fiadores] = $this->extractContratoData($request);

            $contrato->update($payload);
            $contrato->fiadores()->sync($fiadores);

            if ($request->filled('anexos_remover')) {
                $anexosRemover = $contrato->anexos()->whereIn('id', $request->input('anexos_remover', []))->get();
                $anexosRemover->each(function ($anexo) {
                    Storage::disk('public')->delete($anexo->path);
                    $anexo->delete();
                });
            }

            $this->storeAnexos($contrato, $request);

            return new ContratoResource($contrato->load(['imovel.condominio', 'locador', 'locatario', 'fiadores', 'contaCobranca', 'anexos']));
        });
    }

    public function destroy(Contrato $contrato)
    {
        $this->authorize('delete', $contrato);

        // Impede exclusão quando houver faturas vinculadas
        if ($contrato->faturas()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir o contrato: existem faturas vinculadas.'
            ], Response::HTTP_CONFLICT);
        }

        $contrato->anexos->each(function ($anexo) {
            Storage::disk('public')->delete($anexo->path);
            $anexo->delete();
        });

        $contrato->delete();

        return response()->noContent();
    }

    private function extractContratoData(Request $request): array
    {
        $validated = $request->validated();

        $fiadores = collect($validated['fiadores'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        unset($validated['fiadores'], $validated['anexos'], $validated['anexos_remover']);

        if (($validated['garantia_tipo'] ?? null) !== ContratoGarantiaTipo::Caucao->value) {
            $validated['caucao_valor'] = null;
        }

        if (($validated['reajuste_indice'] ?? null) === ContratoReajusteIndice::SemReajuste->value) {
            $validated['reajuste_periodicidade_meses'] = null;
            $validated['data_proximo_reajuste'] = null;
            $validated['reajuste_teto_percentual'] = null;
        } elseif (empty($validated['data_proximo_reajuste']) && ! empty($validated['reajuste_periodicidade_meses'])) {
            $validated['data_proximo_reajuste'] = Carbon::parse($validated['data_inicio'])
                ->addMonths((int) $validated['reajuste_periodicidade_meses'])
                ->toDateString();
        }

        if (($validated['reajuste_indice'] ?? null) !== ContratoReajusteIndice::Outro->value) {
            $validated['reajuste_indice_outro'] = null;
        }

        if ($validated['reajuste_teto_percentual'] === '') {
            $validated['reajuste_teto_percentual'] = null;
        }

        foreach (['forma_pagamento_preferida', 'tipo_contrato'] as $enumField) {
            if (array_key_exists($enumField, $validated) && $validated[$enumField] === '') {
                $validated[$enumField] = null;
            }
        }

        if (! array_key_exists('repasse_automatico', $validated)) {
            $validated['repasse_automatico'] = false;
        }

        return [$validated, $fiadores];
    }

    private function storeAnexos(Contrato $contrato, Request $request): void
    {
        if (! $request->hasFile('anexos')) {
            return;
        }

        $userId = optional($request->user())->getKey();

        foreach ((array) $request->file('anexos') as $uploadedFile) {
            if (! $uploadedFile) {
                continue;
            }

            $path = $uploadedFile->store("contratos/{$contrato->id}", 'public');

            $contrato->anexos()->create([
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'uploaded_by' => $userId,
            ]);
        }
    }

    private function ensureUniqueActiveContrato($imovelId, $status, ?string $dataFim, ?int $ignoreId = null, ?Contrato $current = null): void
    {
        if ($imovelId === '') {
            $imovelId = null;
        }

        if ($imovelId !== null) {
            $imovelId = (int) $imovelId;
        }

        $imovelId ??= $current?->imovel_id;
        $statusValue = $status instanceof ContratoStatus ? $status->value : $status;
        $statusValue ??= $current?->status?->value;
        $dataFim = $dataFim === '' ? null : $dataFim;
        $dataFim ??= optional($current?->data_fim)?->toDateString();

        if ($statusValue !== ContratoStatus::Ativo->value || ! $imovelId) {
            return;
        }

        $query = Contrato::query()
            ->where('imovel_id', $imovelId)
            ->where('status', ContratoStatus::Ativo->value)
            ->where(function ($builder) use ($dataFim) {
                $today = Carbon::today();
                $builder->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $today);

                if ($dataFim) {
                    $builder->orWhere('data_fim', '>=', $dataFim);
                }
            });

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'imovel_id' => 'Ja existe um contrato ativo para este imovel.',
            ]);
        }
    }
}
