<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Financeiro;

use App\Domain\Financeiro\Services\AccountBalanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountBalanceController extends Controller
{
    public function __construct(private readonly AccountBalanceService $balanceService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->hasPermission('financeiro.balance.view') && ! $user?->hasPermission('financeiro.view')) {
            abort(403, 'Você não tem permissão para visualizar os saldos financeiros.');
        }

        $filters = [
            'category' => $request->input('filter.category'),
            'cost_center_id' => $request->input('filter.cost_center_id'),
            'account_id' => $request->input('filter.account_id'),
            'include_inactive' => $request->boolean('filter.include_inactive'),
        ];

        $payload = $this->balanceService->getSummary($user, $filters);

        return response()->json([
            'data' => $payload,
        ]);
    }
}

