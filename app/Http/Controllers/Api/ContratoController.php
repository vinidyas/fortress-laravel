<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contrato\ContratoStoreRequest;
use App\Http\Requests\Contrato\ContratoUpdateRequest;
use App\Http\Resources\ContratoResource;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Contrato::class);

        $query = Contrato::query()->with(['imovel', 'locador', 'locatario', 'fiador']);

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
            ])
            ->paginate($perPage)
            ->appends($request->query());

        return ContratoResource::collection($contratos);
    }

    public function show(Contrato $contrato)
    {
        $this->authorize('view', $contrato);

        return new ContratoResource($contrato->load(['imovel', 'locador', 'locatario', 'fiador']));
    }

    public function store(ContratoStoreRequest $request)
    {
        $this->authorize('create', Contrato::class);

        $this->ensureUniqueActiveContrato(
            $request->input('imovel_id'),
            $request->input('status'),
            $request->input('data_fim')
        );

        $contrato = Contrato::query()->create($request->validated())->load(['imovel', 'locador', 'locatario', 'fiador']);

        return (new ContratoResource($contrato))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ContratoUpdateRequest $request, Contrato $contrato)
    {
        $this->authorize('update', $contrato);

        $this->ensureUniqueActiveContrato(
            $request->input('imovel_id'),
            $request->input('status'),
            $request->input('data_fim'),
            $contrato->id,
            $contrato
        );

        $contrato->update($request->validated());

        return new ContratoResource($contrato->load(['imovel', 'locador', 'locatario', 'fiador']));
    }

    public function destroy(Contrato $contrato)
    {
        $this->authorize('delete', $contrato);

        $contrato->delete();

        return response()->noContent();
    }

    private function ensureUniqueActiveContrato(?int $imovelId, ?string $status, ?string $dataFim, ?int $ignoreId = null, ?Contrato $current = null): void
    {
        $imovelId ??= $current?->imovel_id;
        $status ??= $current?->status;
        $dataFim = $dataFim === '' ? null : $dataFim;
        $dataFim ??= optional($current?->data_fim)?->toDateString();

        if ($status !== 'Ativo' || ! $imovelId) {
            return;
        }

        $query = Contrato::query()
            ->where('imovel_id', $imovelId)
            ->where('status', 'Ativo')
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
