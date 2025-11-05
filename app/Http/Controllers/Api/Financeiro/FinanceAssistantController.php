<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Services\Financeiro\FinanceAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceAssistantController extends Controller
{
    public function __construct(private readonly FinanceAssistantService $assistant)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->assistant->respond($validated['message']);

        return response()->json(['data' => $result]);
    }
}

