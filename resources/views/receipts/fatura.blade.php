<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <title>Recibo da Fatura #{{ $fatura->id }} - {{ $company['name'] }}</title>
    <style>
      :root { color-scheme: light; }
      * { box-sizing: border-box; font-family: 'Helvetica Neue', Arial, sans-serif; }
      @page { size: A4; margin: 14mm; }
      body { margin: 0; padding: 28px; background: #f5f6fa; color: #1f2937; font-size: 13.2px; line-height: 1.55; }
      .invoice-wrapper, .receipt-wrapper { width: 100%; max-width: 21cm; margin: 0 auto; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 48px rgba(15, 23, 42, 0.12); overflow: hidden; }
      header { display: flex; align-items: center; justify-content: space-between; padding: 28px 34px; background: linear-gradient(135deg, #0f172a, #1e293b); color: #e2e8f0; }
      header .branding h1 { margin: 0; font-size: 25px; letter-spacing: 0.12em; text-transform: uppercase; }
      header .branding p { margin: 4px 0; font-size: 12px; color: #cbd5f5; }
      .logo { max-width: 155px; }
      .invoice-info, .title-section { padding: 26px 34px 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; }
      .invoice-title, .title-section h2 { margin: 0; font-size: 23px; color: #0f172a; }
      .invoice-meta, .title-section p { margin: 6px 0 0; font-size: 12.2px; color: #475569; }
      .meta, .client-supplier { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; padding: 0 34px 26px; }
      .meta-card, .panel { border: 1px solid #e2e8f0; border-radius: 14px; background: #f8fafc; padding: 18px 20px; }
      .meta-card h3, .panel h3 { margin: 0 0 9px; font-size: 11.4px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
      .meta-card p, .panel p { margin: 3px 0; font-size: 13px; color: #0f172a; }
      table { width: 100%; border-collapse: collapse; }
      thead { background: #1e293b; color: #e2e8f0; }
      th, td { padding: 11px 14px; font-size: 12.3px; border-bottom: 1px solid #e2e8f0; }
      tbody tr:nth-child(odd) { background: #f8fafc; }
      tfoot td { background: #0f172a; color: #f8fafc; font-weight: 700; font-size: 14.5px; border-bottom: none; }
      .summary, .payment-block { padding: 26px 34px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
      .summary .thanks, .instructions { font-size: 12.2px; color: #475569; }
      .summary .total, .highlight { font-size: 19.5px; font-weight: 700; color: #0f172a; }
      .highlight { background: #0f172a; color: #f8fafc; border-radius: 14px; padding: 18px 22px; }
      .instructions { max-width: 62%; }
      .footer { padding: 22px 34px 28px; font-size: 12px; color: #64748b; display: flex; justify-content: space-between; align-items: flex-start; background: #f8fafc; border-top: 1px solid #e2e8f0; }
      .footer small { display: block; margin-top: 4px; color: #94a3b8; }
      .signature { margin-top: 30px; text-align: center; }
      .signature-line { width: 220px; margin: 28px auto 8px; border-bottom: 1px solid #94a3b8; }
      .print-btn { position: fixed; top: 18px; right: 18px; background: #0f172a; color: #f8fafc; border: none; padding: 10px 18px; border-radius: 999px; font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; box-shadow: 0 12px 26px rgba(15, 23, 42, 0.22); }
      @media print {
        body { padding: 0; background: #ffffff; }
        .invoice-wrapper, .receipt-wrapper { box-shadow: none; border-radius: 0; }
        .print-btn { display: none; }
      }
    </style>
  </head>
  <body>
    <button class="print-btn" onclick="window.print()">Imprimir / Salvar PDF</button>

    <div class="receipt-wrapper">
      <header>
        @if ($logoBase64)
          <img src="{{ $logoBase64 }}" alt="Logo {{ $company['name'] }}" class="logo">
        @endif
        <div class="company">
          <h1>{{ $company['name'] }}</h1>
          <p>{{ $company['phone'] }}</p>
          <p>{{ $company['email'] }}</p>
        </div>
      </header>

      <section class="title-section">
        <h2>Recibo da Fatura nº {{ $fatura->id }}</h2>
        <p>Emitido em {{ optional($fatura->created_at)->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
      </section>

      <section class="meta">
        <div class="meta-card">
          <h3>Cliente</h3>
          <p>{{ $fatura->contrato?->locatario?->nome_razao_social ?? '---' }}</p>
          <p style="font-weight:400; color:#64748b;">Contrato {{ $fatura->contrato?->codigo_contrato ?? '---' }}</p>
        </div>
        <div class="meta-card">
          <h3>Imóvel</h3>
          <p>{{ $fatura->contrato?->imovel?->codigo ?? '---' }}</p>
          <p style="font-weight:400; color:#64748b;">{{ $fatura->contrato?->imovel?->cidade ?? '' }}</p>
        </div>
        <div class="meta-card">
          <h3>Competência</h3>
          <p>{{ optional($fatura->competencia)->format('m/Y') }}</p>
        </div>
        <div class="meta-card">
          <h3>Vencimento</h3>
          <p>{{ optional($fatura->vencimento)->format('d/m/Y') }}</p>
        </div>
      </section>

      <section style="padding: 0 40px 24px;">
        <table>
          <thead>
            <tr>
              <th style="text-align:left;">Categoria</th>
              <th style="text-align:left;">Descrição</th>
              <th style="text-align:right;">Qtd</th>
              <th style="text-align:right;">Valor Unitário</th>
              <th style="text-align:right;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($fatura->itens as $item)
              <tr>
                <td>{{ $item->categoria }}</td>
                <td>{{ $item->descricao ?? '---' }}</td>
                <td style="text-align:right;">{{ number_format((float) $item->quantidade, 0, ',', '.') }}</td>
                <td style="text-align:right;">{{ 'R$ ' . number_format((float) $item->valor_unitario, 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ 'R$ ' . number_format((float) $item->valor_total, 2, ',', '.') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" style="text-align:center; color:#94a3b8;">Nenhum lançamento registrado.</td>
              </tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="text-align:right;">Total da Fatura</td>
              <td style="text-align:right;">{{ 'R$ ' . number_format((float) $fatura->valor_total, 2, ',', '.') }}</td>
            </tr>
          </tfoot>
        </table>
      </section>

      <div class="summary">
        <div class="thanks">
          <strong>Recebido de:</strong>
          {{ $fatura->contrato?->locatario?->nome_razao_social ?? '---' }}<br>
          <strong>Descrição:</strong> Pagamento referente à fatura nº {{ $fatura->id }} do contrato {{ $fatura->contrato?->codigo_contrato ?? '---' }}.
        </div>
        <div class="total">{{ 'R$ ' . number_format((float) ($fatura->valor_pago ?? $fatura->valor_total), 2, ',', '.') }}</div>
      </div>

      <section style="padding: 0 40px 24px; display:grid; gap:18px;">
        @php
          $formaPagamentoPreferida = $fatura->contrato?->forma_pagamento_preferida?->label();
          $formaPagamento = $fatura->metodo_pagamento ?? $formaPagamentoPreferida;
        @endphp
        <div style="font-size:13px; color:#475569;">
          <strong>Forma de pagamento:</strong> {{ $formaPagamento ?? 'Não informado' }}<br>
          <strong>Status:</strong> {{ $fatura->status }}<br>
          <strong>Pago em:</strong> {{ optional($fatura->pago_em)->format('d/m/Y') ?? '—' }}
        </div>
        @if ($fatura->observacoes)
          <div style="font-size:13px; color:#475569;">
            <strong>Observações:</strong><br>
            {{ $fatura->observacoes }}
          </div>
        @endif
        <div style="font-size:13px; color:#475569;">
          <strong>Instruções ao cliente:</strong> Em caso de dúvidas, contate o suporte da Fortress Empreendimentos.
        </div>
      </section>

      <div class="footer">
        <div class="signature">
          <div class="signature-line"></div>
          <p>Assinatura / Responsável</p>
        </div>
        <p style="margin-top:24px;">Este recibo foi gerado automaticamente por {{ $company['name'] }}. Para dúvidas ou ajustes, entre em contato conosco.</p>
      </div>
    </div>
  </body>
</html>
