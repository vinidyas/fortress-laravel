<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

class FaturaGenerator
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Gera faturas para os contratos elegíveis na competência informada.
     *
     * @return array{created:int, skipped:int, processed_contracts:int}
     */
    public function generateForCompetencia(Carbon $competencia, ?int $contratoId = null): array
    {
        $competencia = $competencia->clone()->startOfMonth();
        $competenciaDate = $competencia->toDateString();
        $competenciaEndDate = $competencia->clone()->endOfMonth()->toDateString();

        $contractsQuery = Contrato::query()
            ->with('imovel')
            ->where('status', 'Ativo')
            ->where('data_inicio', '<=', $competenciaEndDate)
            ->where(function ($query) use ($competenciaDate) {
                $query->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $competenciaDate);
            });

        if ($contratoId) {
            $contractsQuery->whereKey($contratoId);
        }

        $contracts = $contractsQuery->get();

        $created = 0;
        $skipped = 0;

        foreach ($contracts as $contrato) {
            $this->db->transaction(function () use ($contrato, $competencia, $competenciaDate, &$created, &$skipped) {
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
                        $this->createLancamento(
                            $fatura,
                            'Condominio',
                            'Repasse condominio',
                            1,
                            (float) $imovel->valor_condominio
                        );
                    }

                    if (! $imovel->iptu_isento && (float) $imovel->valor_iptu > 0) {
                        $this->createLancamento(
                            $fatura,
                            'IPTU',
                            'Repasse IPTU',
                            1,
                            (float) $imovel->valor_iptu
                        );
                    }
                }

                $fatura->recalcTotals()->save();
                $created++;
            });
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'processed_contracts' => $contracts->count(),
        ];
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

    private function createLancamento(
        Fatura $fatura,
        string $categoria,
        string $descricao,
        float $quantidade,
        float $valorUnitario
    ): void {
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

