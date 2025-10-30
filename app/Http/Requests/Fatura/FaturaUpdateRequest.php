<?php

namespace App\Http\Requests\Fatura;

use App\Support\Formatting\Concerns\NormalizesDecimalValues;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FaturaUpdateRequest extends FormRequest
{
    use NormalizesDecimalValues;

    private const ITEM_CATEGORIES = ['Aluguel', 'Condominio', 'IPTU', 'Multa', 'Juros', 'Desconto', 'Outros', 'Agua', 'Luz', 'Gas'];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('faturas.update');
    }

    public function rules(): array
    {
        return [
            'vencimento' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
            'boleto_url' => ['nullable', 'string', 'max:255'],
            'pix_qrcode' => ['nullable', 'string'],
            'nosso_numero' => ['nullable', 'string', 'max:50'],
            'status' => ['prohibited'],
            'itens' => ['nullable', 'array'],
            'itens.*.categoria' => ['required', Rule::in(self::ITEM_CATEGORIES)],
            'itens.*.descricao' => ['nullable', 'string', 'max:200'],
            'itens.*.quantidade' => ['nullable', 'integer', 'min:0'],
            'itens.*.valor_unitario' => ['required_with:itens.*.categoria', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        $data['vencimento'] = $this->normalizeDate($this->input('vencimento'));
        $data['itens'] = $this->normalizeItens($this->input('itens'));

        $this->merge($data);
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
                return [
                    'categoria' => $item['categoria'] ?? null,
                    'descricao' => $item['descricao'] ?? null,
                    'quantidade' => $this->normalizeDecimalToNullableString($item['quantidade'] ?? 1),
                    'valor_unitario' => $this->normalizeDecimalToNullableString($item['valor_unitario'] ?? 0),
                ];
            })
            ->filter(fn ($item) => $item['categoria'] !== null)
            ->values()
            ->map(function ($item) {
                return [
                    'categoria' => $item['categoria'],
                    'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'] === null ? null : (int) round((float) $item['quantidade']),
                    'valor_unitario' => $item['valor_unitario'] === null ? null : (float) $item['valor_unitario'],
                ];
            })
            ->all();
    }
}
