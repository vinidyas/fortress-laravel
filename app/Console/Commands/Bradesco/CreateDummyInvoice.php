<?php

namespace App\Console\Commands\Bradesco;

use App\Models\Condominio;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Services\Banking\Bradesco\GenerateBradescoBoletoService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CreateDummyInvoice extends Command
{
    protected $signature = 'bradesco:create-dummy-invoice {--no-issue : Não emite o boleto (padrão: emite) }';

    protected $description = 'Cria uma fatura de teste com contrato/locatário e, opcionalmente, emite um boleto no sandbox';

    public function handle(GenerateBradescoBoletoService $service): int
    {
        RequestException::dontTruncate();

        $issue = ! $this->option('no-issue');

        try {
            [$contrato, $fatura] = DB::transaction(function () {
                $marker = 'BRADESCO_SANDBOX_DUMMY';

                // Remove dados anteriores marcados
                Fatura::query()->where('observacoes', $marker)->each(function (Fatura $fatura) {
                    $fatura->itens()->delete();
                    $fatura->boletos()->delete();
                    $fatura->delete();
                });

                $locador = Pessoa::query()->firstOrCreate(
                    ['cpf_cnpj' => '77256195000120'],
                    [
                        'nome_razao_social' => 'INOVA FOODS LOCADOR TESTE',
                        'email' => 'locador@inova.test',
                        'telefone' => '1133334444',
                        'cep' => '04547000',
                        'estado' => 'SP',
                    'cidade' => 'SAO PAULO',
                    'bairro' => 'ITAIM BIBI',
                    'rua' => 'AV BRIGADEIRO FARIA LIMA',
                    'numero' => '1000',
                        'complemento' => 'CONJ 1001',
                        'tipo_pessoa' => 'Juridica',
                        'papeis' => ['Locador'],
                    ]
                );

                $locatario = Pessoa::query()->firstOrCreate(
                    ['cpf_cnpj' => '12345678909'],
                    [
                        'nome_razao_social' => 'JOSE LOCATARIO TESTE',
                        'email' => 'locatario@exemplo.test',
                        'telefone' => '11988887777',
                        'cep' => '01310930',
                        'estado' => 'SP',
                    'cidade' => 'SAO PAULO',
                    'bairro' => 'BELA VISTA',
                    'rua' => 'AV PAULISTA',
                    'numero' => '1578',
                        'complemento' => 'AP 42',
                        'tipo_pessoa' => 'Fisica',
                        'papeis' => ['Locatario'],
                    ]
                );

                $condominio = Condominio::factory()->create([
                    'nome' => 'Condomínio Demo Bradesco',
                    'cep' => '01310930',
                    'estado' => 'SP',
                    'cidade' => 'SAO PAULO',
                    'bairro' => 'BELA VISTA',
                    'rua' => 'AV PAULISTA',
                    'numero' => '1578',
                ]);

                $imovel = Imovel::factory()
                    ->for($locador, 'proprietario')
                    ->for($locador, 'agenciador')
                    ->for($locador, 'responsavel')
                    ->for($condominio)
                    ->state([
                        'codigo' => 'BRSAN'.random_int(100, 999),
                        'cep' => '01310930',
                        'estado' => 'SP',
                        'cidade' => 'SAO PAULO',
                        'bairro' => 'BELA VISTA',
                        'rua' => 'AV PAULISTA',
                        'numero' => '1578',
                    ])
                    ->create();

                $contrato = Contrato::factory()
                    ->for($imovel)
                    ->for($locador, 'locador')
                    ->for($locatario, 'locatario')
                    ->state([
                        'codigo_contrato' => 'BRADESCO-SANDBOX-'.Str::upper(Str::random(5)),
                        'status' => \App\Enums\ContratoStatus::Ativo,
                        'dia_vencimento' => 28,
                        'valor_aluguel' => 5519.97,
                    ])
                    ->create();

                $competencia = Carbon::now()->startOfMonth();
                $vencimento = Carbon::now()->setDay(28);

                $fatura = Fatura::query()->create([
                    'contrato_id' => $contrato->id,
                    'competencia' => $competencia->toDateString(),
                    'vencimento' => $vencimento->toDateString(),
                    'status' => 'Aberta',
                    'valor_total' => 0,
                    'observacoes' => 'BRADESCO_SANDBOX_DUMMY',
                ]);

                $items = [
                    ['categoria' => 'Aluguel', 'descricao' => 'Aluguel mensal', 'valor' => 2749.89],
                    ['categoria' => 'Condominio', 'descricao' => 'Repasse condominio', 'valor' => 861.57],
                    ['categoria' => 'IPTU', 'descricao' => 'Repasse IPTU', 'valor' => 1908.51],
                ];

                foreach ($items as $item) {
                    FaturaLancamento::query()->create([
                        'fatura_id' => $fatura->id,
                        'categoria' => $item['categoria'],
                        'descricao' => $item['descricao'],
                        'quantidade' => 1,
                        'valor_unitario' => $item['valor'],
                        'valor_total' => $item['valor'],
                    ]);
                }

                $fatura->recalcTotals()->save();

                return [$contrato, $fatura];
            });

            $this->info("Fatura dummy criada: #{$fatura->id} (Contrato {$contrato->codigo_contrato})");
            $this->line('Total da fatura: R$ '.number_format((float) $fatura->valor_total, 2, ',', '.'));

            if ($issue) {
                $boleto = $service->handle($fatura);

                $this->info('Boleto emitido no sandbox:');
                $this->line(' • Nosso número: '.$boleto->nosso_numero);
                $this->line(' • Linha digitável: '.$boleto->linha_digitavel);
                $this->line(' • PDF URL: '.$boleto->pdf_url);
            } else {
                $this->warn('Boleto NÃO foi emitido porque a opção --no-issue foi utilizada.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::channel('bradesco')->error('[Bradesco] Falha ao criar fatura dummy', [
                'exception' => $exception->getMessage(),
                'response' => method_exists($exception, 'response') && $exception->response()
                    ? $exception->response()->body()
                    : null,
            ]);

            $this->error('Não foi possível criar a fatura de teste: '.$exception->getMessage());

            if (method_exists($exception, 'response') && $exception->response()) {
                $this->line('Detalhes: '.$exception->response()->body());
            }

            return self::FAILURE;
        }
    }
}
