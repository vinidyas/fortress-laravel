<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaBoleto extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAILED = 'failed';

    protected $table = 'fatura_boletos';

    protected $fillable = [
        'fatura_id',
        'bank_code',
        'external_id',
        'nosso_numero',
        'document_number',
        'linha_digitavel',
        'codigo_barras',
        'valor',
        'vencimento',
        'status',
        'registrado_em',
        'liquidado_em',
        'valor_pago',
        'pdf_url',
        'payload',
        'response_payload',
        'webhook_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'vencimento' => 'date',
        'registrado_em' => 'datetime',
        'liquidado_em' => 'datetime',
        'payload' => 'array',
        'response_payload' => 'array',
        'webhook_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function markAsPaid(float $valorPago, ?\DateTimeInterface $liquidadoEm = null): void
    {
        $this->status = self::STATUS_PAID;
        $this->valor_pago = $valorPago;
        $this->liquidado_em = $liquidadoEm ?: now();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Aguardando emissão',
            self::STATUS_REGISTERED => 'Registrado',
            self::STATUS_PAID => 'Pago',
            self::STATUS_CANCELED => 'Cancelado',
            self::STATUS_FAILED => 'Falha na emissão',
            default => ucfirst($this->status ?? 'Desconhecido'),
        };
    }
}
