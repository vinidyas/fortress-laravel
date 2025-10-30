<?php

namespace App\Console\Commands\Bradesco;

use App\Services\Banking\Bradesco\BradescoApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestBradescoAuth extends Command
{
    protected $signature = 'bradesco:test-auth {--force : Força a renovação do token}';

    protected $description = 'Testa a autenticação com o Bradesco (sandbox) via mTLS e exibe o token recebido';

    public function handle(BradescoApiClient $client): int
    {
        $force = (bool) $this->option('force');

        try {
            $config = $client->refreshAccessToken($force);

            $this->info('Token obtido com sucesso.');
            $this->line('Expira em: '.$config->token_expires_at?->toDateTimeString());
            $this->line('Trecho do token: '.substr((string) $config->access_token, 0, 32).'...');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::channel('bradesco')->error('[Bradesco] Teste de autenticação falhou', [
                'exception' => $exception->getMessage(),
            ]);

            $this->error('Falha ao autenticar com o Bradesco: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
