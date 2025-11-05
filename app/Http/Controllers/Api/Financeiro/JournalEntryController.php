<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Domain\Financeiro\DataTransferObjects\AllocationData;
use App\Domain\Financeiro\DataTransferObjects\InstallmentData;
use App\Domain\Financeiro\DataTransferObjects\JournalEntryData;
use App\Domain\Financeiro\Services\CloneJournalEntryService;
use App\Domain\Financeiro\Services\CreateJournalEntryService;
use App\Domain\Financeiro\Services\Installment\PayInstallmentService;
use App\Domain\Financeiro\Services\Receipt\GenerateReceiptService;
use App\Domain\Financeiro\Services\SyncInstallmentClonesService;
use App\Domain\Financeiro\Services\UpdateJournalEntryService;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Events\Financeiro\AccountBalancesShouldRefresh;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\JournalEntryInstallmentPayRequest;
use App\Http\Requests\Financeiro\JournalEntryStoreRequest;
use App\Http\Requests\Financeiro\JournalEntryUpdateRequest;
use App\Http\Resources\Financeiro\FinancialReceiptResource;
use App\Http\Resources\Financeiro\JournalEntryResource;
use App\Models\JournalEntry;
use App\Models\JournalEntryInstallment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly CreateJournalEntryService $createJournalEntry,
        private readonly CloneJournalEntryService $cloneJournalEntry,
        private readonly PayInstallmentService $payInstallment,
        private readonly UpdateJournalEntryService $updateJournalEntry,
        private readonly GenerateReceiptService $generateReceipt,
        private readonly SyncInstallmentClonesService $syncInstallmentClones,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JournalEntry::class);

        $query = $this->makeFilteredQuery($request);
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $entries = $query
            ->orderByDesc('movement_date')
            ->paginate($perPage)
            ->appends($request->query());

        return JournalEntryResource::collection($entries);
    }

    public function store(JournalEntryStoreRequest $request): JsonResponse
    {
        $data = $this->mapToDto($request->validated(), $request);

        $entry = $this->createJournalEntry->handle($data);

        $this->syncInstallmentClones->handle($entry);

        $entry = $entry->refresh()->load([
            'bankAccount',
            'costCenter.parent',
            'person',
            'property',
            'installments',
            'allocations',
            'attachments.uploadedBy',
            'receipts.issuedBy',
        ]);

        return JournalEntryResource::make($entry)
            ->additional(['message' => 'Lançamento criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('view', $journalEntry);

        return JournalEntryResource::make(
            $journalEntry->load([
                'bankAccount',
                'costCenter.parent',
                'person',
                'property',
                'installments',
                'allocations',
                'attachments.uploadedBy',
                'receipts.issuedBy',
            ])
        );
    }

    public function update(JournalEntryUpdateRequest $request, JournalEntry $journalEntry): JournalEntryResource
    {
        $journalEntry->load(['installments', 'allocations']);

        $data = $this->mapToDto($request->validated(), $request, $journalEntry);

        $entry = $this->updateJournalEntry->handle($journalEntry, $data);

        $this->syncInstallmentClones->handle($entry);

        return JournalEntryResource::make($entry->refresh()->load([
            'bankAccount',
            'costCenter.parent',
            'person',
            'property',
            'installments',
            'allocations',
            'attachments.uploadedBy',
            'receipts.issuedBy',
        ]))->additional([
            'message' => 'Lançamento atualizado com sucesso.',
        ]);
    }

    public function destroy(JournalEntry $journalEntry): Response
    {
        $this->authorize('delete', $journalEntry);

        $accountIds = array_filter([
            $journalEntry->bank_account_id,
            $journalEntry->counter_bank_account_id,
        ]);

        $journalEntry->delete();

        event(new AccountBalancesShouldRefresh($accountIds));

        return response()->noContent();
    }

    public function clone(JournalEntry $journalEntry, Request $request): JsonResponse
    {
        $this->authorize('create', JournalEntry::class);

        $overrides = $request->validate([
            'movement_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'bank_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'counter_bank_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'reference_code' => ['nullable', 'string', 'max:40'],
        ]);

        $clone = $this->cloneJournalEntry->handle($journalEntry, $overrides);

        return JournalEntryResource::make($clone->load([
            'bankAccount',
            'costCenter.parent',
            'person',
            'property',
            'installments',
            'allocations',
            'attachments.uploadedBy',
            'receipts.issuedBy',
        ]))
            ->additional(['message' => 'Lançamento clonado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function payInstallment(JournalEntryInstallmentPayRequest $request, JournalEntry $journalEntry, JournalEntryInstallment $installment): JournalEntryResource
    {
        $this->authorize('update', $journalEntry);

        $data = $request->validated();
        $this->payInstallment->handle(
            $installment,
            paymentDate: $data['payment_date'],
            penalty: $data['penalty'] ?? null,
            interest: $data['interest'] ?? null,
            discount: $data['discount'] ?? null,
        );

        return JournalEntryResource::make($journalEntry->refresh()->load(['installments']));
    }

    public function cancel(Request $request, JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('update', $journalEntry);

        if ($journalEntry->status === JournalEntryStatus::Pago->value) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Não é possível cancelar um lançamento pago.');
        }

        $journalEntry->load(['installments', 'allocations']);

        $payload = [
            'status' => JournalEntryStatus::Cancelado->value,
            'installments' => $journalEntry->installments->map(fn ($installment) => [
                'numero_parcela' => $installment->numero_parcela,
                'movement_date' => $installment->movement_date?->toDateString(),
                'due_date' => $installment->due_date?->toDateString(),
                'payment_date' => $installment->payment_date?->toDateString(),
                'valor_principal' => (float) $installment->valor_principal,
                'valor_juros' => (float) $installment->valor_juros,
                'valor_multa' => (float) $installment->valor_multa,
                'valor_desconto' => (float) $installment->valor_desconto,
                'valor_total' => (float) $installment->valor_total,
                'status' => JournalEntryStatus::Cancelado->value,
                'meta' => $installment->meta,
            ])->toArray(),
        ];

        $data = $this->mapToDto($payload, $request, $journalEntry);

        $entry = $this->updateJournalEntry->handle($journalEntry, $data);

        $this->syncInstallmentClones->handle($entry);

        return JournalEntryResource::make($entry->refresh()->load([
            'installments',
            'allocations',
        ]))->additional([
            'message' => 'Lançamento cancelado.',
        ]);
    }

    public function generateReceipt(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('view', $journalEntry);

        if ($journalEntry->status !== JournalEntryStatus::Pago->value) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Só é possível gerar recibo para lançamentos pagos.');
        }

        $data = $request->validate([
            'installment_id' => ['nullable', 'integer', 'exists:journal_entry_installments,id'],
        ]);

        $installment = null;

        if (! empty($data['installment_id'])) {
            $installment = $journalEntry->installments()->find($data['installment_id']);

            if (! $installment) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Parcela informada não pertence ao lançamento.');
            }
        }

        $receipt = $this->generateReceipt->handle($journalEntry, $installment);

        return FinancialReceiptResource::make($receipt->load('issuedBy'))->additional([
            'message' => 'Recibo gerado com sucesso.',
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', JournalEntry::class);

        $query = $this->makeFilteredQuery($request)->orderByDesc('movement_date');
        $filename = 'journal-entries-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Data', 'Conta', 'Tipo', 'Valor', 'Status', 'Descrição']);

            $query->with(['bankAccount'])->lazy(500)->each(function (JournalEntry $entry) use ($handle) {
                $statusEnum = $entry->status ? JournalEntryStatus::tryFrom((string) $entry->status) : null;
                $typeEnum = $entry->type ? JournalEntryType::tryFrom((string) $entry->type) : null;
                $statusLabel = $statusEnum ? $statusEnum->label($typeEnum) : $entry->status;

                fputcsv($handle, [
                    $entry->id,
                    $entry->movement_date?->toDateString(),
                    $entry->bankAccount?->nome,
                    $entry->type,
                    number_format((float) $entry->amount, 2, '.', ''),
                    $statusLabel,
                    $entry->description_custom,
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function makeFilteredQuery(Request $request): Builder
    {
        return JournalEntry::query()
            ->operational()
            ->with([
                'bankAccount',
                'costCenter.parent',
                'person',
                'property',
                'installments' => fn ($query) => $query->select('id', 'journal_entry_id', 'meta')->orderBy('id'),
            ])
            ->when($request->filled('filter.tipo'), fn ($q) => $q->where('type', $request->string('filter.tipo')))
            ->when($request->filled('filter.status'), function ($q) use ($request) {
                $statuses = JournalEntryStatus::filterValues((string) $request->string('filter.status'));

                return count($statuses) === 1
                    ? $q->where('status', $statuses[0])
                    : $q->whereIn('status', $statuses);
            })
            ->when($request->filled('filter.account_id'), fn ($q) => $q->where('bank_account_id', $request->integer('filter.account_id')))
            ->when($request->filled('filter.cost_center_id'), fn ($q) => $q->where('cost_center_id', $request->integer('filter.cost_center_id')))
            ->when($request->filled('filter.person_id'), fn ($q) => $q->where('person_id', $request->integer('filter.person_id')))
            ->when($request->filled('filter.property_id'), fn ($q) => $q->where('property_id', $request->integer('filter.property_id')))
            ->when($request->filled('filter.data_de'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('filter.data_de')->toDateString()))
            ->when($request->filled('filter.data_ate'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('filter.data_ate')->toDateString()))
            ->when($request->filled('filter.search'), function ($q) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->string('filter.search'))).'%';
                $q->where(function ($inner) use ($term) {
                    $inner
                        ->whereRaw('LOWER(description_custom) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(notes) LIKE ?', [$term])
                        ->orWhere('reference_code', 'like', $term);
                });
            });
    }

    private function mapToDto(array $data, Request $request, ?JournalEntry $existing = null): JournalEntryData
    {
        if ($existing) {
            $existing->loadMissing(['installments', 'allocations']);

            $base = [
                'type' => $existing->type,
                'bank_account_id' => $existing->bank_account_id,
                'counter_bank_account_id' => $existing->counter_bank_account_id,
                'cost_center_id' => $existing->cost_center_id,
                'property_id' => $existing->property_id,
                'person_id' => $existing->person_id,
                'description_id' => $existing->description_id,
                'description_custom' => $existing->description_custom,
                'notes' => $existing->notes,
                'reference_code' => $existing->reference_code,
                'improvement_type' => $existing->improvement_type,
                'movement_date' => $existing->movement_date?->toDateString(),
                'due_date' => $existing->due_date?->toDateString(),
                'payment_date' => $existing->payment_date?->toDateString(),
                'currency' => $existing->currency,
                'status' => $existing->status,
                'amount' => (float) $existing->amount,
                'installments' => $existing->installments->map(fn ($installment) => [
                    'numero_parcela' => $installment->numero_parcela,
                    'movement_date' => $installment->movement_date?->toDateString(),
                    'due_date' => $installment->due_date?->toDateString(),
                    'payment_date' => $installment->payment_date?->toDateString(),
                    'valor_principal' => (float) $installment->valor_principal,
                    'valor_juros' => (float) $installment->valor_juros,
                    'valor_multa' => (float) $installment->valor_multa,
                    'valor_desconto' => (float) $installment->valor_desconto,
                    'valor_total' => (float) $installment->valor_total,
                    'status' => $installment->status,
                    'meta' => $installment->meta,
                ])->toArray(),
                'allocations' => $existing->allocations->map(fn ($allocation) => [
                    'cost_center_id' => $allocation->cost_center_id,
                    'property_id' => $allocation->property_id,
                    'percentage' => $allocation->percentage,
                    'amount' => $allocation->amount,
                ])->toArray(),
            ];

            $data = array_merge($base, $data);
        }

        $installments = collect($data['installments'] ?? [])->map(function (array $item, int $index) {
            return new InstallmentData(
                numeroParcela: $item['numero_parcela'] ?? $index + 1,
                movementDate: $item['movement_date'],
                dueDate: $item['due_date'],
                paymentDate: $item['payment_date'] ?? null,
                valorPrincipal: (float) $item['valor_principal'],
                valorJuros: (float) ($item['valor_juros'] ?? 0),
                valorMulta: (float) ($item['valor_multa'] ?? 0),
                valorDesconto: (float) ($item['valor_desconto'] ?? 0),
                valorTotal: (float) $item['valor_total'],
                status: JournalEntryStatus::from($item['status'] ?? 'planejado'),
                meta: $item['meta'] ?? null,
            );
        });

        $allocations = collect($data['allocations'] ?? [])->map(fn (array $item) => new AllocationData(
            costCenterId: $item['cost_center_id'],
            propertyId: $item['property_id'] ?? null,
            percentage: isset($item['percentage']) ? (float) $item['percentage'] : null,
            amount: isset($item['amount']) ? (float) $item['amount'] : null,
        ));

        $origin = $existing?->origin ?? 'manual';

        return new JournalEntryData(
            type: JournalEntryType::from($data['type']),
            bankAccountId: $data['bank_account_id'],
            counterBankAccountId: $data['counter_bank_account_id'] ?? null,
            costCenterId: $data['cost_center_id'] ?? null,
            propertyId: $data['property_id'] ?? null,
            personId: $data['person_id'] ?? null,
            descriptionId: $data['description_id'] ?? null,
            descriptionCustom: $data['description_custom'] ?? null,
            notes: $data['notes'] ?? null,
            referenceCode: $data['reference_code'] ?? null,
            improvementType: $data['improvement_type'] ?? null,
            origin: $origin,
            cloneOfId: null,
            movementDate: $data['movement_date'],
            dueDate: $data['due_date'] ?? null,
            paymentDate: $data['payment_date'] ?? null,
            currency: $data['currency'] ?? 'BRL',
            status: JournalEntryStatus::from($data['status'] ?? 'planejado'),
            amount: (float) $data['amount'],
            installments: Collection::make($installments),
            allocations: Collection::make($allocations),
            createdBy: $request->user()?->id,
            updatedBy: $request->user()?->id,
        );
    }
}
