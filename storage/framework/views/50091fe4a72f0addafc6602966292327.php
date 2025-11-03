<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo <?php echo e($receipt->number); ?></title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2933; margin: 0; padding: 24px; }
        header { text-align: center; margin-bottom: 24px; }
        h1 { font-size: 20px; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 2px; }
        h2 { font-size: 14px; margin: 0; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px 12px; border: 1px solid #d1d5db; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .amount { font-size: 18px; font-weight: 600; text-align: right; margin-top: 16px; }
        .footer { margin-top: 32px; text-align: center; font-size: 11px; color: #6b7280; }
        .signature { margin-top: 48px; text-align: center; }
        .signature span { display: inline-block; border-top: 1px solid #000; padding: 4px 16px; }
    </style>
</head>
<body>
    <header>
        <h1>Recibo de Pagamento</h1>
        <h2>Nº <?php echo e($receipt->number); ?> · Emitido em <?php echo e($issueDateFormatted); ?></h2>
    </header>

    <section>
        <table>
            <tbody>
                <tr>
                    <th>Beneficiário</th>
                    <td><?php echo e($account?->nome ?? config('app.name')); ?></td>
                </tr>
                <tr>
                    <th>Pagador</th>
                    <td><?php echo e($person?->nome ?? 'Não informado'); ?></td>
                </tr>
                <tr>
                    <th>Lançamento</th>
                    <td>#<?php echo e($entry->id); ?> · <?php echo e($entry->description_custom ?? 'Sem descrição'); ?></td>
                </tr>
                <tr>
                    <th>Parcela</th>
                    <td>
                        <?php if($installment): ?>
                            Nº <?php echo e($installment->numero_parcela); ?> · Vencimento <?php echo e(optional($installment->due_date)->format('d/m/Y') ?? '-'); ?>

                        <?php else: ?>
                            Recibo referente ao valor total do lançamento.
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Centro de custo</th>
                    <td><?php echo e($costCenter?->nome ?? 'Não vinculado'); ?></td>
                </tr>
            </tbody>
        </table>

        <p class="amount">
            Valor recebido: R$ <?php echo e($amount_formatted); ?>

        </p>

        <p style="margin-top: 20px; line-height: 1.6;">
            Declaramos para os devidos fins que recebemos o valor acima de <?php echo e($person?->nome ?? '...'); ?>,
            correspondente ao lançamento financeiro identificado neste recibo.
        </p>
    </section>

    <section class="signature">
        <span><?php echo e($account?->nome ?? config('app.name')); ?></span>
    </section>

    <footer class="footer">
        Recibo gerado automaticamente pelo sistema em <?php echo e(now()->format('d/m/Y H:i')); ?>.<br>
        Documento válido sem assinatura.
    </footer>
</body>
</html>
<?php /**PATH /root/docker/fortress/fortress-laravel/resources/views/pdf/financial-receipt.blade.php ENDPATH**/ ?>