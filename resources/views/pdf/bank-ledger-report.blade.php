@php
  $type = strtolower((string) ($filters['type'] ?? 'despesa'));
  $isRevenueReport = $type === 'receita';
  $reportTitle = $isRevenueReport ? 'Relatório de Receitas' : 'Relatório de Despesas';
  $detailedReportTitle = $isRevenueReport ? 'Relatório de Receitas Detalhado' : 'Relatório de Despesas Detalhado';
  $sectionTitle = $isRevenueReport ? 'Receitas' : 'Despesas';
  $rowsCollection = collect($rows ?? []);
  $maxSupplierLength = $rowsCollection->max(fn ($row) => \Illuminate\Support\Str::length($row['person']['nome'] ?? '')) ?? 0;
  $maxPropertyLength = $rowsCollection->max(fn ($row) => \Illuminate\Support\Str::length($row['property']['nome'] ?? '')) ?? 0;
  $dateColumnWidth = 6.5;
  $dueColumnWidth = 6.5;
  $statusColumnWidth = 6;
  $valueColumnWidth = 8;

  $availableWidth = 100 - ($dateColumnWidth + $dueColumnWidth + $statusColumnWidth + $valueColumnWidth);
  $descriptionMinWidth = 24;
  $supplierMinWidth = 14;
  $supplierMaxWidth = 24;
  $propertyMinWidth = 14;
  $propertyMaxWidth = 34;

  $propertyTarget = $propertyMinWidth + ($maxPropertyLength * 0.22);
  $propertyColumnWidth = round(min($propertyMaxWidth, max($propertyMinWidth, $propertyTarget)), 1);

  $maxPropertyAllowed = $availableWidth - $supplierMinWidth - $descriptionMinWidth;
  if ($maxPropertyAllowed < $propertyMinWidth) {
      $maxPropertyAllowed = $propertyMinWidth;
  }
  $propertyColumnWidth = min($propertyColumnWidth, $maxPropertyAllowed);

  $remainingAfterProperty = $availableWidth - $propertyColumnWidth;
  $supplierTarget = $supplierMinWidth + ($maxSupplierLength * 0.18);
  $supplierColumnWidth = round(min($supplierMaxWidth, max($supplierMinWidth, $supplierTarget)), 1);
  $maxSupplierAllowed = $remainingAfterProperty - $descriptionMinWidth;
  if ($maxSupplierAllowed < $supplierMinWidth) {
      $supplierColumnWidth = $supplierMinWidth;
      $propertyColumnWidth = max(
          $propertyMinWidth,
          min($propertyColumnWidth, $availableWidth - $supplierColumnWidth - $descriptionMinWidth)
      );
  } else {
      $supplierColumnWidth = min($supplierColumnWidth, $maxSupplierAllowed);
  }

  $descriptionColumnWidth = max(
      $descriptionMinWidth,
      $availableWidth - $supplierColumnWidth - $propertyColumnWidth,
  );
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <title>{{ $detailedReportTitle }}</title>
    <style>
      @page {
        margin: 18mm 8mm 16mm 8mm;
      }
      :root {
        color-scheme: light;
        --column-date-width: {{ $dateColumnWidth }}%;
        --column-due-width: {{ $dueColumnWidth }}%;
        --column-status-width: {{ $statusColumnWidth }}%;
        --column-value-width: {{ $valueColumnWidth }}%;
        --column-description-width: {{ $descriptionColumnWidth }}%;
        --column-supplier-width: {{ $supplierColumnWidth }}%;
        --column-property-width: {{ $propertyColumnWidth }}%;
      }
      body {
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 12px;
        color: #1f2937;
        margin: 0;
        padding: 0;
        line-height: 1.4;
      }
      .wrapper {
        padding: 10px 6px;
      }
      header {
        margin-bottom: 8px;
      }
      .header-table {
        width: 100%;
        border-collapse: collapse;
      }
      .header-logo {
        width: 170px;
        vertical-align: middle;
      }
      .logo {
        width: 160px;
        height: auto;
        display: block;
      }
      .company-info {
        text-align: right;
        vertical-align: middle;
        padding-left: 12px;
      }
      .company-info h1 {
        font-size: 18px;
        margin: 0;
        font-weight: 700;
        color: #111827;
      }
      .company-info p {
        margin: 4px 0 0;
        color: #6b7280;
      }
      h2 {
        font-size: 16px;
        margin: 8px 0 12px;
        color: #111827;
      }
      .section-divider {
        height: 1px;
        background-color: #d1d5db;
        margin-bottom: 12px;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
      }
      th {
        background-color: #111827;
        color: #f9fafb;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 11px;
        padding: 8px 10px;
        text-align: left;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      td {
        padding: 8px 10px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .text-right {
        text-align: right;
      }
      .muted {
        color: #6b7280;
        font-size: 11px;
      }
      section {
        margin-bottom: 18px;
      }
      .report-title {
        font-size: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        text-align: center;
        margin: 0 0 16px;
        color: #111827;
      }
      .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
      }
      .summary-card {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 10px 12px;
        background-color: #f9fafb;
      }
      .summary-card-inline {
        padding: 9px 12px;
      }
      .summary-inline {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
      }
      .summary-inline--wrap {
        flex-wrap: wrap;
        gap: 6px;
      }
      .summary-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
      }
      .summary-value {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
      }
      .summary-grow {
        flex: 1 1 auto;
      }
      .summary-divider {
        color: #9ca3af;
        font-size: 12px;
      }
      .footer {
        margin-top: 32px;
        font-size: 10px;
        color: #9ca3af;
        text-align: right;
      }
      .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }
      .badge-open { background-color: #fef3c7; color: #92400e; }
      .badge-settled { background-color: #dcfce7; color: #166534; }
      .badge-cancelled { background-color: #ffe4e6; color: #b91c1c; }
      .badge-overdue { background-color: #fde68a; color: #92400e; }
      .column-date,
      .column-date-cell {
        width: var(--column-date-width);
        text-align: left;
        padding-left: 4px !important;
        padding-right: 4px !important;
      }
      .column-due,
      .column-due-cell {
        width: var(--column-due-width);
        text-align: left;
        padding-left: 4px !important;
        padding-right: 4px !important;
      }
      .column-supplier { width: var(--column-supplier-width); }
      .column-description { width: var(--column-description-width); }
      .column-property { width: var(--column-property-width); }
      .column-status { width: var(--column-status-width); }
      .column-value {
        width: var(--column-value-width);
        padding-left: 6px !important;
        padding-right: 6px !important;
        font-variant-numeric: tabular-nums;
      }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <header>
        <table class="header-table">
          <tr>
            <td class="header-logo">
              @if ($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo {{ $company['name'] ?? config('app.name') }}" class="logo">
              @endif
            </td>
            <td class="company-info">
              <h1>FORTRESS EMPREENDIMENTOS</h1>
              <p>{{ $detailedReportTitle }}</p>
              <p class="muted">
                Gerado em {{ optional($filters['generated_at'] ?? null)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
              </p>
            </td>
          </tr>
        </table>
      </header>

      <div class="section-divider"></div>

      <h1 class="report-title">{{ $reportTitle }}</h1>
      @php
        $periodFrom = !empty($filters['date_from'])
          ? \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y')
          : 'Início';
        $periodTo = !empty($filters['date_to'])
          ? \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y')
          : 'Hoje';
      @endphp
      @if ($isRevenueReport)
        @php
          $periodLabel = "Período de {$periodFrom} a {$periodTo}";
        @endphp
        <p class="muted" style="margin-top: -8px; margin-bottom: 12px; text-align: center;">
          {{ $periodLabel }}
        </p>
      @else
        <p class="muted" style="margin-top: -8px; margin-bottom: 12px; text-align: center;">
          Período de {{ $periodFrom }} a {{ $periodTo }}
        </p>
      @endif

      @php
        $totalExpenses = !$isRevenueReport
          ? array_reduce(
              $rows ?? [],
              fn ($carry, $row) => $carry + (($row['absolute_amount'] ?? null) ?: abs($row['signed_amount'] ?? 0)),
              0
            )
          : 0;

        $totalRevenue = $isRevenueReport
          ? array_reduce(
              $rows ?? [],
              fn ($carry, $row) => $carry + ($row['amount_in'] ?? (($row['signed_amount'] ?? 0) > 0 ? $row['signed_amount'] : 0)),
              0
            )
          : 0;
      @endphp

      <section>
        <h2>{{ $sectionTitle }}</h2>
        <table>
          <thead>
            <tr>
              <th class="column-date">Data</th>
              <th class="column-supplier">Fornecedor</th>
              <th class="column-description">Descrição</th>
              <th class="column-property">Imóvel</th>
              <th class="column-due">Venc.</th>
              <th class="column-status">Status</th>
              <th class="column-value text-right">Valor</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $index => $row)
              <tr>
                <td class="column-date-cell">
                  {{ $row['movement_date'] ? \Illuminate\Support\Carbon::parse($row['movement_date'])->format('d/m/Y') : '-' }}
                </td>
                <td class="column-supplier">
                  {{ !empty($row['person']['nome']) ? mb_strtoupper($row['person']['nome'], 'UTF-8') : '—' }}
                </td>
                <td class="column-description">
                  <strong>{{ mb_strtoupper($row['description'] ?? 'Sem descrição', 'UTF-8') }}</strong>
                </td>
                <td class="column-property">
                  @if (!empty($row['property']['nome']))
                    {{ mb_strtoupper($row['property']['nome'], 'UTF-8') }}
                  @else
                    —
                  @endif
                </td>
                <td class="column-due-cell">
                  {{ $row['due_date'] ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('d/m/Y') : '—' }}
                </td>
                <td class="column-status">
                  {{ $row['status_label'] ?? '—' }}
                </td>
              @php
                $value = $isRevenueReport
                  ? ($row['signed_amount'] ?? 0)
                  : (($row['absolute_amount'] ?? null) ?: abs($row['signed_amount'] ?? 0));
              @endphp
              <td class="column-value text-right" style="font-weight:600;">
                  R$ {{ number_format($value, 2, ',', '.') }}
              </td>
            </tr>
              @if ($loop->last)
                <tr>
                  <td colspan="6" class="text-right" style="padding-right: 12px; font-weight:600;">
                    {{ $isRevenueReport ? 'TOTAL DE RECEITAS' : 'TOTAL DAS DESPESAS' }}
                  </td>
                  <td class="column-value text-right" style="font-weight:700;">
                    R$
                    {{ number_format($isRevenueReport ? $totalRevenue : $totalExpenses, 2, ',', '.') }}
                  </td>
                </tr>
              @endif
            @empty
              <tr>
              <td colspan="7" class="text-right muted">Nenhuma movimentação encontrada no período.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </section>

      <div class="footer">
        Documento emitido em {{ optional($filters['generated_at'] ?? null)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }} por {{ $filters['generated_by'] ?? 'Usuário não identificado' }} · Uso interno
      </div>
    </div>
  </body>
</html>
