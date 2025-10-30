@php
  $rowsCollection = collect($rows ?? []);
  $totalCount = $rowsCollection->count();
  $inflow = (float) ($totals['inflow'] ?? 0);
  $outflow = (float) ($totals['outflow'] ?? 0);
  $net = (float) ($totals['net'] ?? 0);

  $periodFrom = !empty($filters['date_from'])
    ? \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y')
    : 'Início';
  $periodTo = !empty($filters['date_to'])
    ? \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y')
    : 'Hoje';

  $typeLabel = $filters['type_label'] ?? 'Todos';
  $statusLabel = $filters['status_label'] ?? 'Todos';
  $basisLabel = $filters['date_basis_label'] ?? 'Data de movimento';
  $personLabel = $filters['person_label'] ?? 'Todos';
  $propertyLabel = $filters['property_label'] ?? 'Todos';
  $costCenterLabel = $filters['cost_center_label'] ?? 'Todos';
  $accountLabel = $filters['account_label'] ?? ($account['nome'] ?? 'Todos os bancos');
  $descriptionFilter = $filters['description'] ?? null;
  $orderByLabel = $filters['order_by_label'] ?? 'Data de movimento';
  $orderDirection = !empty($filters['order_desc']) ? 'Decrescente' : 'Crescente';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>Relatório Geral Analítico</title>
    <style>
      @page { margin: 18mm 10mm 16mm 10mm; }
      body {
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 12px;
        color: #1f2937;
        margin: 0;
        padding: 0;
        line-height: 1.4;
      }
      .wrapper { padding: 10px 6px; }
      .header-table { width: 100%; border-collapse: collapse; }
      .header-logo { width: 170px; }
      .logo { width: 160px; height: auto; display: block; }
      .company-info { text-align: right; padding-left: 12px; }
      .company-info h1 { font-size: 18px; margin: 0; font-weight: 700; color: #111827; }
      .company-info p { margin: 4px 0 0; color: #6b7280; }
      .section-divider { height: 1px; background-color: #d1d5db; margin: 12px 0; }
      .report-title { font-size: 20px; font-weight: 700; text-align: center; margin: 0 0 14px; letter-spacing: 0.08em; color: #111827; }
      .muted { color: #6b7280; font-size: 11px; }
      .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px;
      }
      .summary-card {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 12px 14px;
        background-color: #f9fafb;
      }
      .summary-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        margin-bottom: 4px;
      }
      .summary-value {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
      }
      .filters-card {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 12px 14px;
        background-color: #fff;
        margin-bottom: 14px;
      }
      .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 6px 12px;
      }
      .filters-item {
        display: inline-flex;
        align-items: baseline;
        gap: 6px;
        font-size: 11px;
        color: #1f2937;
        border-radius: 9999px;
        padding: 4px 10px;
        background-color: #f3f4f6;
        border: 1px solid #e5e7eb;
      }
      .filters-item strong {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
      }
      thead th {
        background-color: #111827;
        color: #f9fafb;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 10.5px;
        padding: 7px 8px;
        text-align: left;
        white-space: nowrap;
      }
      tbody td {
        padding: 7px 8px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 11.5px;
      }
      .col-date { width: 8%; }
      .col-type { width: 8%; }
      .col-person { width: 16%; }
      .col-description { width: 22%; }
      .col-details { width: 20%; }
      .col-account { width: 15%; }
      .col-status { width: 9%; }
      .col-value { width: 10%; text-align: right; font-variant-numeric: tabular-nums; }
      .text-muted { color: #6b7280; font-size: 10px; }
      .badge {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 9999px;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }
      .badge-open { background-color: #fef3c7; color: #92400e; }
      .badge-settled { background-color: #dcfce7; color: #166534; }
      .badge-cancelled { background-color: #fee2e2; color: #b91c1c; }
      .badge-overdue { background-color: #fde68a; color: #92400e; }
      .footer {
        margin-top: 20px;
        font-size: 10px;
        color: #9ca3af;
        text-align: right;
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
              <h1>{{ $company['name'] ?? 'Fortress Empreendimentos' }}</h1>
              <p>Relatório Geral Analítico</p>
              <p class="muted">
                Gerado em {{ optional($filters['generated_at'] ?? null)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
              </p>
            </td>
          </tr>
        </table>
      </header>

      <div class="section-divider"></div>

      <h1 class="report-title">Relatório Geral Analítico</h1>
      <p class="muted" style="text-align:center; margin-top:-6px; margin-bottom:12px;">
        Período de {{ $periodFrom }} a {{ $periodTo }} • Conta: {{ $accountLabel }}
      </p>

      <div class="filters-card">
        <div class="filters-grid">
          <div class="filters-item"><strong>Tipo</strong> {{ $typeLabel }}</div>
          <div class="filters-item"><strong>Status</strong> {{ $statusLabel }}</div>
          <div class="filters-item"><strong>Análise por</strong> {{ $basisLabel }}</div>
          <div class="filters-item"><strong>Fornecedor/Cliente</strong> {{ $personLabel ?? 'Todos' }}</div>
          <div class="filters-item"><strong>Imóvel</strong> {{ $propertyLabel ?? 'Todos' }}</div>
          <div class="filters-item"><strong>Centro de custo</strong> {{ $costCenterLabel ?? 'Todos' }}</div>
          <div class="filters-item"><strong>Ordenação</strong> {{ $orderByLabel }} • {{ $orderDirection }}</div>
          <div class="filters-item"><strong>Descrição contém</strong> {{ $descriptionFilter ?: 'Qualquer' }}</div>
          <div class="filters-item"><strong>Total de lançamentos</strong> {{ $totalCount }}</div>
        </div>
      </div>

      <table style="margin-bottom: 18px;">
        <thead>
          <tr>
            <th class="col-date">Data</th>
            <th class="col-type">Tipo</th>
            <th class="col-person">Fornecedor/Cliente</th>
            <th class="col-description">Descrição / Observação</th>
            <th class="col-details">Imóvel / Centro de custo</th>
            <th class="col-account">Conta / Documento</th>
            <th class="col-status">Status</th>
            <th class="col-value">Valor</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            @php
              $signed = (float) ($row['signed_amount'] ?? 0);
              $status = strtolower((string) ($row['status'] ?? 'open'));
              $statusClass = match ($status) {
                'pago' => 'badge badge-settled',
                'quitado', 'settled' => 'badge badge-settled',
                'cancelado', 'cancelled' => 'badge badge-cancelled',
                'overdue', 'atrasado' => 'badge badge-overdue',
                default => 'badge badge-open',
              };
              $formattedValue = ($signed < 0 ? '-' : '') . ' R$ ' . number_format(abs($signed), 2, ',', '.');
            @endphp
            <tr>
              <td class="col-date">
                {{ $row['movement_date'] ? \Illuminate\Support\Carbon::parse($row['movement_date'])->format('d/m/Y') : '—' }}
              </td>
              <td class="col-type">
                {{ mb_strtoupper($row['type_label'] ?? '-', 'UTF-8') }}
              </td>
              <td class="col-person">
                {{ $row['person'] ? mb_strtoupper($row['person'], 'UTF-8') : '—' }}
              </td>
              <td class="col-description">
                <strong>{{ mb_strtoupper($row['description'] ?? 'Sem descrição', 'UTF-8') }}</strong>
                @if (!empty($row['notes']))
                  <div class="text-muted">{{ $row['notes'] }}</div>
                @endif
              </td>
              <td class="col-details">
                @if (!empty($row['property']))
                  <div>{{ mb_strtoupper($row['property'], 'UTF-8') }}</div>
                @endif
                @if (!empty($row['cost_center']))
                  <div class="text-muted">{{ $row['cost_center'] }}</div>
                @endif
                @if (empty($row['property']) && empty($row['cost_center']))
                  —
                @endif
              </td>
              <td class="col-account">
                @if (!empty($row['account']))
                  <div>{{ mb_strtoupper($row['account'], 'UTF-8') }}</div>
                @endif
                @if (!empty($row['document']))
                  <div class="text-muted">Doc: {{ $row['document'] }}</div>
                @endif
                @if (empty($row['account']) && empty($row['document']))
                  —
                @endif
              </td>
              <td class="col-status">
                <span class="{{ $statusClass }}">
                  {{ $row['status_label'] ?? '—' }}
                </span>
              </td>
              <td class="col-value" style="color: {{ $signed < 0 ? '#b91c1c' : '#166534' }}; font-weight: 600;">
                {{ $formattedValue }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" style="text-align:center; padding:16px 0; color:#6b7280;">
                Nenhum lançamento encontrado para os filtros informados.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>

      <div class="summary-grid" style="margin-top: 16px;">
        <div class="summary-card">
          <div class="summary-label">Total de entradas</div>
          <div class="summary-value">R$ {{ number_format($inflow, 2, ',', '.') }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Total de saídas</div>
          <div class="summary-value">R$ {{ number_format($outflow, 2, ',', '.') }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Resultado líquido</div>
          <div class="summary-value" style="color: {{ $net >= 0 ? '#166534' : '#b91c1c' }};">
            R$ {{ number_format($net, 2, ',', '.') }}
          </div>
        </div>
      </div>

      <div class="footer">
        Relatório gerado por {{ $filters['generated_by'] ?? 'Sistema' }} em {{ now()->format('d/m/Y H:i') }}.
      </div>
    </div>
  </body>
</html>
