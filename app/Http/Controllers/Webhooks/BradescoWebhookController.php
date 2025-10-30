<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Bradesco\ProcessBradescoWebhookPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BradescoWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->ensureAuthorized($request);

        ProcessBradescoWebhookPayload::dispatch($request->all());

        return response()->json(['received' => true]);
    }

    protected function ensureAuthorized(Request $request): void
    {
        $configuredSecret = config('services.bradesco_boleto.webhook_secret');
        $providedSecret = $request->header('X-Webhook-Token') ?: $request->bearerToken();

        if (! $configuredSecret || ! $providedSecret || ! hash_equals($configuredSecret, (string) $providedSecret)) {
            Log::warning('[Bradesco] Webhook rejeitado por token inválido', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            abort(Response::HTTP_UNAUTHORIZED, 'Webhook signature mismatch.');
        }
    }
}
