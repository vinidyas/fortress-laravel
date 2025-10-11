<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate {--competencia=} {--contrato=}';

    protected $description = 'Gera faturas para contratos ativos na competencia informada';

    public function handle(): int
    {
        $competencia = $this->resolveCompetencia($this->option('competencia'));
        $contratoId = $this->option('contrato');

        $contractsQuery = Contrato::query()
            ->with('imovel')
            ->where('status', 'Ativo')
            ->where('data_inicio', '<=', $competencia->clone()->endOfMonth()->toDateString())
            ->where(function ($query) use ($competencia) {
                $query->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $competencia->toDateString());
            });

        if ($contratoId) {
            $contractsQuery->whereKey($contratoId);
        }

        $contracts = $contractsQuery->get();

        if ($contracts->isEmpty()) {
            $this->info('Nenhum contrato elegivel encontrado.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($contracts as $contrato) {
            DB::transaction(function () use ($contrato, $competencia, &$created, &$skipped) {
                $competenciaDate = $competencia->toDateString();

                $fatura = Fatura::query()
                    ->where('contrato_id', $contrato->id)
                    ->whereDate('competencia', $competenciaDate)
                    ->first();

                if ($fatura) {
                    $skipped++;

                    return;
                }

                $fatura = Fatura::query()->create([
                    'contrato_id' => $contrato->id,
                    'competencia' => $competenciaDate,
                    'vencimento' => $this->resolveVencimento($contrato, $competencia),
                    'status' => 'Aberta',
                ]);

                $fatura->itens()->delete();

                $this->createLancamento($fatura, 'Aluguel', 'Aluguel mensal', 1, (float) $contrato->valor_aluguel);

                $imovel = $contrato->imovel;
                if ($imovel) {
                    if (! $imovel->condominio_isento && (float) $imovel->valor_condominio > 0) {
                        $this->createLancamento($fatura, 'Condominio', 'Repasse condominio', 1, (float) $imovel->valor_condominio);
                    }
                    if (! $imovel->iptu_isento && (float) $imovel->valor_iptu > 0) {
                        $this->createLancamento($fatura, 'IPTU', 'Repasse IPTU', 1, (float) $imovel->valor_iptu);
                    }
                }

                $fatura->recalcTotals()->save();
                $created++;
            });
        }

        $this->info(sprintf(
            'Faturas geradas: %d | Ignoradas (ja existentes): %d',
            $created,
            $skipped
        ));

        return self::SUCCESS;
    }

    private function resolveCompetencia(?string $value): Carbon
    {
        if (! $value) {
            return Carbon::now()->startOfMonth();
        }

        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        return Carbon::parse($value)->startOfMonth();
    }

    private function resolveVencimento(Contrato $contrato, Carbon $competencia): string
    {
        $dia = max(1, min(28, $contrato->dia_vencimento ?? 1));
        $vencimento = $competencia->clone()->setDay($dia);

        if ($vencimento->month !== $competencia->month) {
            $vencimento = $competencia->clone()->endOfMonth();
        }

        return $vencimento->toDateString();
    }

    private function createLancamento(Fatura $fatura, string $categoria, string $descricao, float $quantidade, float $valorUnitario): void
    {
        FaturaLancamento::query()->create([
            'fatura_id' => $fatura->id,
            'categoria' => $categoria,
            'descricao' => $descricao,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $quantidade * $valorUnitario,
        ]);
        $fatura->unsetRelation('itens');
        $fatura->load('itens');
    }
}
