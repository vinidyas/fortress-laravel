<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\ContratoFormaPagamento;
use App\Http\Requests\Fatura\FaturaBaixaRequest;
use App\Http\Requests\Fatura\FaturaStoreRequest;
use App\Http\Requests\Fatura\FaturaUpdateRequest;
use App\Http\Requests\Fatura\FaturaEmailRequest;
use App\Http\Resources\FaturaResource;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Services\FaturaGenerator;
use App\Services\FaturaEmailService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FaturaController extends Controller
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly FaturaGenerator $faturaGenerator
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Fatura::class);

        $query = Fatura::query()->with([
            'contrato.imovel.condominio',
            'anexos',
            'emailLogs' => fn ($q) => $q->latest()->limit(1),
        ]);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
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
            ->paginate($perPage)
            ->appends($request->query());

        return FaturaResource::collection($faturas);
    }

    public function show(Fatura $fatura)
    {
        $this->authorize('view', $fatura);

        $this->loadFaturaRelations($fatura);

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
                $fatura->vencimento = Carbon::parse(
                    $this->resolveVencimento($contratoId, $competencia)
                );
            }

            $fatura->save();

            $this->syncItens($fatura, $request->input('itens', []));

            $fatura->recalcTotals()->save();
            $this->loadFaturaRelations($fatura);

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

            if ($request->exists('itens')) {
                $this->syncItens($fatura, $request->input('itens', []));
                $fatura->recalcTotals();
            }

            $fatura->save();
            $this->loadFaturaRelations($fatura);

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

            $this->loadFaturaRelations($fatura);

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

        $this->loadFaturaRelations($fatura);

        return new FaturaResource($fatura);
    }

    public function updateContractPaymentMethod(Request $request, Fatura $fatura)
    {
        $this->authorize('update', $fatura);

        $contrato = $fatura->contrato;

        if (! $contrato) {
            return response()->json(['message' => 'Contrato não encontrado para esta fatura.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'forma_pagamento_preferida' => ['nullable', 'string', Rule::in(ContratoFormaPagamento::values())],
        ]);

        $contrato->forma_pagamento_preferida = $validated['forma_pagamento_preferida'] ?? null;
        $contrato->save();

        $this->loadFaturaRelations($fatura);

        return new FaturaResource($fatura);
    }

    public function sendEmail(
        FaturaEmailRequest $request,
        Fatura $fatura,
        FaturaEmailService $emailService
    ) {
        $this->authorize('email', $fatura);

        $this->loadFaturaRelations($fatura);

        $payload = $request->sanitized();
        $defaults = $emailService->buildDefaults($fatura);

        $to = $payload['recipients'] ?: $defaults['to'];
        $cc = $payload['cc'] ?: $defaults['cc'];
        $bcc = $payload['bcc'] ?? [];
        $message = $payload['message'] ?? null;
        $attachmentIds = array_values(array_unique($payload['attachments'] ?? []));

        $selectedAttachments = ! empty($attachmentIds)
            ? $fatura->anexos()->whereIn('id', $attachmentIds)->get()
            : collect();

        if ($selectedAttachments->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages([
                'attachments' => 'Alguns anexos selecionados não pertencem a esta fatura.',
            ]);
        }

        $emailService->send($fatura, $to, $cc, $bcc, $message, $request->user(), $selectedAttachments);

        $this->loadFaturaRelations($fatura);

        return (new FaturaResource($fatura))->additional([
            'meta' => [
                'message' => 'Fatura enviada por e-mail com sucesso.',
            ],
        ]);
    }

    public function generateCurrentMonth(Request $request)
    {
        $this->authorize('create', Fatura::class);

        $competencia = Carbon::now()->startOfMonth();
        $contratoId = $request->filled('contrato_id') ? $request->integer('contrato_id') : null;

        $result = $this->faturaGenerator->generateForCompetencia($competencia, $contratoId);

        if ($result['processed_contracts'] === 0) {
            return response()->json([
                'message' => 'Nenhum contrato elegivel encontrado para a competencia atual.',
                'created' => 0,
                'skipped' => 0,
                'processed_contracts' => 0,
            ]);
        }

        $message = sprintf(
            'Faturas geradas: %d | Ignoradas (ja existentes): %d',
            $result['created'],
            $result['skipped']
        );

        if ($contratoId && $result['processed_contracts'] > 0) {
            $message = $result['created'] > 0
                ? 'Fatura gerada com sucesso para o contrato selecionado.'
                : 'A fatura deste contrato já foi gerada no mês vigente.';
        }

        return response()->json([
            'message' => $message,
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'processed_contracts' => $result['processed_contracts'],
        ]);
    }

    public function eligibleContracts(Request $request)
    {
        $this->authorize('create', Fatura::class);

        $competencia = Carbon::now()->startOfMonth();
        $competenciaDate = $competencia->toDateString();
        $competenciaEndDate = $competencia->clone()->endOfMonth()->toDateString();

        $limit = min(max($request->integer('limit', 20), 1), 50);
        $search = trim((string) $request->query('search', ''));

        $query = Contrato::query()
            ->with([
                'imovel' => fn ($imovelQuery) => $imovelQuery->with('condominio'),
            ])
            ->withCount([
                'faturas as invoices_in_month_count' => fn ($q) => $q->whereDate('competencia', $competenciaDate),
            ])
            ->where('status', 'Ativo')
            ->where('data_inicio', '<=', $competenciaEndDate)
            ->where(function ($query) use ($competenciaDate) {
                $query->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $competenciaDate);
            });

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('codigo_contrato', 'like', "%{$search}%")
                    ->orWhereHas('imovel', function ($imovelQuery) use ($search) {
                        $imovelQuery->where('codigo', 'like', "%{$search}%")
                            ->orWhere('cidade', 'like', "%{$search}%");
                    });

                if (is_numeric($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $contracts = $query
            ->orderBy('codigo_contrato')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function (Contrato $contrato) {
                $imovel = $contrato->imovel;

                return [
                    'id' => $contrato->id,
                    'codigo_contrato' => $contrato->codigo_contrato,
                    'imovel' => $imovel ? [
                        'codigo' => $imovel->codigo,
                        'cidade' => $imovel->cidade,
                        'bairro' => $imovel->bairro,
                        'condominio_nome' => $imovel->condominio?->nome,
                        'complemento' => $imovel->complemento,
                    ] : null,
                    'imovel_label' => $imovel
                        ? ($imovel->condominio?->nome ?: 'Sem condomínio')
                        : null,
                    'imovel_sub_label' => $imovel ? ($imovel->complemento ?: null) : null,
                    'has_invoice_in_month' => ($contrato->invoices_in_month_count ?? 0) > 0,
                ];
            })
            ->values();

        return response()->json([
            'data' => $contracts,
        ]);
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

    private function loadFaturaRelations(Fatura $fatura): void
    {
        $fatura->load([
            'contrato.locatario',
            'contrato.locador',
            'contrato.imovel.condominio',
            'itens',
            'anexos' => fn ($query) => $query->latest()->with('uploader'),
            'emailLogs' => fn ($query) => $query->latest()->with(['user:id,nome,email'])->limit(10),
        ]);
    }
}
