<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Jobs\Bradesco\SyncPendingBradescoBoletos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

class BradescoBoletoSyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->hasPermission('faturas.boleto.generate'),
            403,
            'Você não tem permissão para sincronizar boletos.'
        );

        $lote = (int) $request->integer('lote', 50);

        Queue::push(new SyncPendingBradescoBoletos(max($lote, 1)));

        return response()->json([
            'meta' => [
                'message' => 'Sincronização dos boletos foi enfileirada.',
            ],
        ]);
    }
}
