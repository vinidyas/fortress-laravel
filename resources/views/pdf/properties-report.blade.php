<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Imóveis</title>
    <style>
        @page {
            margin: 18mm 12mm 18mm 12mm;
        }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        h1 {
            font-size: 20px;
            text-align: center;
            margin: 0 0 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #111827;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .summary td {
            font-size: 11px;
            padding: 4px 6px;
            color: #4b5563;
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
            font-size: 10px;
            padding: 8px 6px;
            text-align: left;
        }
        td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 11px;
        }
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Relatório de Imóveis</h1>

    <table class="summary">
        <tr>
            <td><strong>Gerado em:</strong> {{ ($generated_at ?? now())->format('d/m/Y H:i') }}</td>
            <td><strong>Filtro:</strong> {{ !empty($filters['only_available']) ? 'Somente disponíveis' : 'Todos' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 26%;">Imóvel</th>
                <th style="width: 13%;">Tipo</th>
                <th style="width: 13%;">Cidade</th>
                <th style="width: 14%;">Valor locação</th>
                <th style="width: 12%;">Dorms/vagas</th>
                <th style="width: 11%;">Disponibilidade</th>
                <th style="width: 11%;">Área total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($properties as $imovel)
                <tr>
                    <td>
                        <div>{{ $imovel['label'] ?? '—' }}</div>
                        <div class="muted">{{ $imovel['info'] ?? '—' }}</div>
                    </td>
                    <td>{{ $imovel['tipo'] ?? '—' }}</td>
                    <td>{{ $imovel['cidade'] ?? '—' }}</td>
                    <td class="text-right">
                        {{ isset($imovel['valor_locacao']) ? 'R$ '.number_format($imovel['valor_locacao'], 2, ',', '.') : '—' }}
                    </td>
                    <td>
                        {{ $imovel['dormitorios'] ?? 0 }} dorm<br>
                        {{ $imovel['vagas'] ?? 0 }} vaga(s)
                    </td>
                    <td>{{ $imovel['disponibilidade'] ?? '—' }}</td>
                    <td class="text-right">
                        {{ $imovel['area_total'] ? number_format($imovel['area_total'], 2, ',', '.').' m²' : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhum imóvel encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
