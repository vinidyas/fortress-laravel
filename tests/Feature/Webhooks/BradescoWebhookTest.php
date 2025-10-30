<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\Bradesco\ProcessBradescoWebhookPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BradescoWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function testWebhookRejeitaSemToken(): void
    {
        $this->postJson('/api/webhooks/bradesco', [])
            ->assertUnauthorized();
    }

    public function testWebhookDespachaProcessamentoQuandoTokenValido(): void
    {
        Queue::fake();

        config()->set('services.bradesco_boleto.webhook_secret', 'segredo');

        $payload = [
            'externalId' => '123',
            'nossoNumero' => '123',
            'evento' => 'mock',
        ];

        $this->withHeaders([
            'X-Webhook-Token' => 'segredo',
        ])
            ->postJson('/api/webhooks/bradesco', $payload)
            ->assertOk()
            ->assertJson(['received' => true]);

        Queue::assertPushed(ProcessBradescoWebhookPayload::class, function (ProcessBradescoWebhookPayload $job) use ($payload) {
            return $job->payload() === $payload;
        });
    }
}
