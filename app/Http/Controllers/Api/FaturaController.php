<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fatura\FaturaBaixaRequest;
use App\Http\Requests\Fatura\FaturaStoreRequest;
use App\Http\Requests\Fatura\FaturaUpdateRequest;
use App\Http\Resources\FaturaResource;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FaturaController extends Controller
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Fatura::class);

        $query = Fatura::query()->with(['contrato.imovel']);

        $faturas = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['competencia', 'vencimento', 'status', 'valor_total', 'created_at'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($builder, $value) {
                    $value = is_array($value) ? implode(' ', $value) : $value;
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->where(function ($query) use ($value) {
                        $query->where('nosso_numero', 'like', "%{$value}%")
                            ->orWhere('boleto_url', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('contrato_id'),
                AllowedFilter::callback('competencia', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }
                    $builder->where('competencia', $this->normalizeCompetencia($value));
                }),
                AllowedFilter::callback('competencia_de', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }
                    $builder->where('competencia', '>=', $this->normalizeCompetencia($value));
                }),
                AllowedFilter::callback('competencia_ate', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }
                    $builder->where('competencia', '<=', $this->normalizeCompetencia($value));
                }),
                AllowedFilter::callback('vencimento_de', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }
                    $builder->where('vencimento', '>=', Carbon::parse($value)->toDateString());
                }),
                AllowedFilter::callback('vencimento_ate', function ($builder, $value) {
                    if (! $value) {
                        return;
                    }
                    $builder->where('vencimento', '<=', Carbon::parse($value)->toDateString());
                }),
            ])
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return FaturaResource::collection($faturas);
    }

    public function show(Fatura $fatura)
    {
        $this->authorize('view', $fatura);

        $fatura->load(['contrato.imovel', 'itens']);

        return new FaturaResource($fatura);
    }

    public function store(FaturaStoreRequest $request)
    {
        $this->authorize('create', Fatura::class);

        return $this->db->transaction(function () use ($request) {
            $competencia = $request->input('competencia');
            $contratoId = $request->integer('contrato_id');

            $this->ensureUnique($contratoId, $competencia);

            $fatura = new Fatura($request->validated());
            $fatura->competencia = $competencia;

            if (! $fatura->vencimento) {
                $fatura->vencimento = $this->resolveVencimento($contratoId, $competencia);
            }

            $fatura->save();

            $this->syncItens($fatura, $request->input('itens', []));

            $fatura->recalcTotals()->save();
            $fatura->load(['contrato.imovel', 'itens']);

            return (new FaturaResource($fatura))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        });
    }

    public function update(FaturaUpdateRequest $request, Fatura $fatura)
    {
        $this->authorize('update', $fatura);

        return $this->db->transaction(function () use ($request, $fatura) {
            $fatura->fill($request->validated());

            if ($request->filled('itens')) {
                $this->syncItens($fatura, $request->input('itens', []));
                $fatura->recalcTotals();
            }

            $fatura->save();
            $fatura->load(['contrato.imovel', 'itens']);

            return new FaturaResource($fatura);
        });
    }

    public function destroy(Fatura $fatura)
    {
        $this->authorize('delete', $fatura);

        if ($fatura->status === 'Paga') {
            return response()->json(['message' => 'Nao e possivel remover fatura paga.'], Response::HTTP_CONFLICT);
        }

        $fatura->delete();

        return response()->noContent();
    }

    public function settle(FaturaBaixaRequest $request, Fatura $fatura)
    {
        $this->authorize('settle', $fatura);

        return $this->db->transaction(function () use ($request, $fatura) {
            $fatura->fill($request->validated());
            $fatura->status = 'Paga';
            $fatura->save();

            $fatura->load(['contrato.imovel', 'itens']);

            return new FaturaResource($fatura);
        });
    }

    public function cancel(Fatura $fatura)
    {
        $this->authorize('cancel', $fatura);

        if ($fatura->status === 'Paga') {
            return response()->json(['message' => 'Nao e possivel cancelar fatura paga.'], Response::HTTP_CONFLICT);
        }

        $fatura->status = 'Cancelada';
        $fatura->save();

        $fatura->load(['contrato.imovel', 'itens']);

        return new FaturaResource($fatura);
    }

    private function syncItens(Fatura $fatura, array $itens): void
    {
        $fatura->itens()->delete();

        $payload = collect($itens)
            ->filter(fn ($item) => isset($item['categoria']))
            ->map(function ($item) {
                $quantidade = (float) ($item['quantidade'] ?? 1);
                $valorUnitario = (float) ($item['valor_unitario'] ?? 0);

                return [
                    'categoria' => $item['categoria'],
                    'descricao' => $item['descricao'] ?? null,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $quantidade * $valorUnitario,
                ];
            })
            ->values();

        if ($payload->isNotEmpty()) {
            $fatura->itens()->createMany($payload->all());
        }

        $fatura->unsetRelation('itens');
        $fatura->load('itens');
    }

    private function resolveVencimento(int $contratoId, string $competencia): string
    {
        $contrato = Contrato::query()->findOrFail($contratoId);

        $competenciaDate = Carbon::parse($competencia)->startOfMonth();
        $dia = max(1, min(28, $contrato->dia_vencimento ?? 1));

        $vencimento = $competenciaDate->clone()->setDay($dia);

        if ($vencimento->month !== $competenciaDate->month) {
            $vencimento = $competenciaDate->clone()->endOfMonth();
        }

        return $vencimento->toDateString();
    }

    private function ensureUnique(int $contratoId, string $competencia): void
    {
        $normalized = Carbon::parse($competencia)->toDateString();

        $exists = Fatura::query()
            ->where('contrato_id', $contratoId)
            ->whereDate('competencia', $normalized)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'competencia' => 'Ja existe uma fatura para este contrato na competencia informada.',
            ]);
        }
    }

    private function normalizeCompetencia(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return Carbon::now()->startOfMonth()->toDateString();
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        return Carbon::parse($value)->startOfMonth()->toDateString();
    }
}


