<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\FinancialAccountRequest;
use App\Http\Resources\Financeiro\FinancialAccountResource;
use App\Models\FinancialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FinancialAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $accounts = FinancialAccount::query()
            ->when($request->boolean('ativos'), fn ($query) => $query->where('ativo', true))
            ->when($request->filled('tipo'), fn ($query) => $query->where('tipo', $request->string('tipo')))
            ->orderBy('nome')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return FinancialAccountResource::collection($accounts);
    }

    public function store(FinancialAccountRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $account = FinancialAccount::create($request->validated());

        return FinancialAccountResource::make($account)
            ->additional(['message' => 'Conta financeira criada com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(FinancialAccount $account): FinancialAccountResource
    {
        $this->authorize('view', $account);

        return FinancialAccountResource::make($account);
    }

    public function update(FinancialAccountRequest $request, FinancialAccount $account): FinancialAccountResource
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return FinancialAccountResource::make($account)->additional([
            'message' => 'Conta financeira atualizada com sucesso.',
        ]);
    }

    public function destroy(FinancialAccount $account): Response
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->noContent();
    }
}
