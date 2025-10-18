<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <title>Fatura #{{ $fatura->id }} - {{ $company['name'] }}</title>
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
      .summary { padding: 26px 34px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
      .payment-block { padding: 26px 34px; display: flex; flex-direction: column; align-items: stretch; gap: 16px; }
      .summary .thanks, .instructions { font-size: 12.2px; color: #475569; }
      .summary .total, .highlight { font-size: 19.5px; font-weight: 700; color: #0f172a; }
      .highlight { background: #0f172a; color: #f8fafc; border-radius: 14px; padding: 18px 22px; width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 14px; }
      .highlight span { display: inline-block; }
      .instructions { max-width: none; }
      .instructions strong { display: block; margin-bottom: 6px; color: #0f172a; }
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

    <div class="invoice-wrapper">
      <header>
        @if ($logoBase64)
          <img src="{{ $logoBase64 }}" alt="Logo {{ $company['name'] }}" class="logo">
        @endif
        <div class="branding">
          <h1>{{ strtoupper($company['name']) }}</h1>
          <p>{{ $company['phone'] }}</p>
          <p>{{ $company['email'] }}</p>
        </div>
      </header>

      <div class="invoice-info">
        <div>
          <h2 class="invoice-title">Fatura #{{ $fatura->id }}</h2>
          <p class="invoice-meta">Emitida em {{ optional($fatura->created_at)->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
        </div>
        <div class="invoice-meta" style="text-align:right;">
          <p><strong>Competência:</strong> {{ optional($fatura->competencia)->format('m/Y') }}</p>
          <p><strong>Vencimento:</strong> {{ optional($fatura->vencimento)->format('d/m/Y') }}</p>
        </div>
      </div>

      <section class="client-supplier">
        <div class="panel">
          <h3>Destinatário</h3>
          <p><strong>{{ $fatura->contrato?->locatario?->nome_razao_social ?? '---' }}</strong></p>
          <p>{{ $fatura->contrato?->locatario?->documento ?? '' }}</p>
          <p>{{ $fatura->contrato?->locatario?->endereco ?? 'Endereço não informado' }}</p>
        </div>
        <div class="panel">
          <h3>Responsável</h3>
          <p><strong>{{ $fatura->contrato?->locador?->nome_razao_social ?? $company['name'] }}</strong></p>
          <p>{{ $fatura->contrato?->locador?->telefone ?: 'Telefone não informado' }}</p>
          @php
            $locadorEmails = preg_split('/[;\n,]+/', (string) ($fatura->contrato?->locador?->email));
            $locadorEmails = collect($locadorEmails)
              ->map(fn ($email) => trim($email))
              ->filter();
          @endphp
          @if ($locadorEmails->isNotEmpty())
            @foreach ($locadorEmails as $email)
              <p>{{ $email }}</p>
            @endforeach
          @else
            <p>Email não informado</p>
          @endif
        </div>
        @php
          $formaPagamentoPreferida = $fatura->contrato?->forma_pagamento_preferida?->label();
          $formaPagamento = $fatura->metodo_pagamento ?? $formaPagamentoPreferida;
        @endphp
        <div class="panel">
          <h3>Condições</h3>
          <p><strong>Forma de cobrança:</strong> {{ $formaPagamento ?? 'Definir com o gestor' }}</p>
          <p><strong>Status:</strong> {{ $fatura->status }}</p>
        </div>
      </section>

      <section style="padding: 0 40px 32px;">
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
              <td colspan="4" style="text-align:right;">Total</td>
              <td style="text-align:right;">{{ 'R$ ' . number_format((float) $fatura->valor_total, 2, ',', '.') }}</td>
            </tr>
          </tfoot>
        </table>
      </section>

      <section class="payment-block">
        <div class="highlight">
          <span class="label">Valor para pagamento:</span>
          <span class="amount">{{ 'R$ ' . number_format((float) $fatura->valor_total, 2, ',', '.') }}</span>
        </div>
        <div class="instructions">
          <strong>Instruções ao cliente:</strong> Em caso de dúvidas, contate o suporte da Fortress Empreendimentos.
        </div>
      </section>

      <footer class="footer">
        <div>
          {{ $company['name'] }}<br>
                  </div>
        <div style="text-align:right;">
          Este documento foi gerado automaticamente. Não é necessário assiná-lo.
        </div>
      </footer>
    </div>
  </body>
</html>
