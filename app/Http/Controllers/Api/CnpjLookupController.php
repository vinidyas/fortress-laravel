<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Cnpj\CnpjLookupService;
use App\Domain\Cnpj\Exceptions\CnpjLookupException;
use App\Domain\Cnpj\Exceptions\CnpjNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CnpjLookupController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly CnpjLookupService $service,
    ) {
    }

    public function __invoke(Request $request, string $cnpj): JsonResponse
    {
        $normalized = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($normalized) !== 14) {
            return response()->json([
                'message' => 'Informe um CNPJ com 14 dígitos.',
                'errors' => [
                    'cnpj' => ['Informe um CNPJ com 14 dígitos.'],
                ],
            ], 422);
        }

        try {
            $data = $this->service->lookup($normalized);
        } catch (CnpjNotFoundException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'cnpj' => [$exception->getMessage()],
                ],
            ], 404);
        } catch (CnpjLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Não foi possível consultar os dados do CNPJ no momento.',
            ], 503);
        }

        return response()->json([
            'data' => $data->toArray(),
        ]);
    }
}
