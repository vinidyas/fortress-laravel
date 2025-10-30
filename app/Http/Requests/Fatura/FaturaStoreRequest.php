<?php

namespace App\Http\Requests\Fatura;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FaturaStoreRequest extends FormRequest
{
    use NormalizesDecimalValues;

    private const ITEM_CATEGORIES = ['Aluguel', 'Condominio', 'IPTU', 'Multa', 'Juros', 'Desconto', 'Outros'];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('faturas.create');
    }

    public function rules(): array
    {
        return [
            'contrato_id' => ['required', 'integer', 'exists:contratos,id'],
            'competencia' => ['required', 'date'],
            'vencimento' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['Aberta', 'Paga', 'Cancelada'])],
            'observacoes' => ['nullable', 'string'],
            'itens' => ['nullable', 'array'],
            'itens.*.categoria' => ['required', Rule::in(self::ITEM_CATEGORIES)],
            'itens.*.descricao' => ['nullable', 'string', 'max:200'],
            'itens.*.quantidade' => ['nullable', 'numeric', 'min:0'],
            'itens.*.valor_unitario' => ['required_with:itens.*.categoria', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        $data['competencia'] = $this->normalizeCompetencia($this->input('competencia'));
        $data['vencimento'] = $this->normalizeDate($this->input('vencimento'));

        if (! isset($data['status']) || $data['status'] === '') {
            $data['status'] = 'Aberta';
        }

        $data['itens'] = $this->normalizeItens($this->input('itens'));

        $this->merge($data);
    }

    private function normalizeCompetencia(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        return Carbon::parse($value)->startOfMonth()->toDateString();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeItens(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item) {
                $quantidade = $this->normalizeDecimalToNullableString($item['quantidade'] ?? 1);
                $valorUnitario = $this->normalizeDecimalToNullableString($item['valor_unitario'] ?? 0);

                return [
                    'categoria' => $item['categoria'] ?? null,
                    'descricao' => $item['descricao'] ?? null,
                    'quantidade' => $quantidade === null ? null : (float) $quantidade,
                    'valor_unitario' => $valorUnitario === null ? null : (float) $valorUnitario,
                ];
            })
            ->filter(fn ($item) => $item['categoria'] !== null)
            ->values()
            ->all();
    }

}
