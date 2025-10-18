<?php

namespace App\Mail;

use App\Models\Fatura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FaturaInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Fatura $fatura,
        public ?string $customMessage = null
    ) {
    }

    public function build(): self
    {
        $fatura = $this->fatura;
        $contrato = $fatura->contrato;

        $billingUrl = route('faturas.billing', $fatura);
        $receiptUrl = route('faturas.receipt', $fatura);

        return $this->view('emails.faturas.invoice', [
            'fatura' => $fatura,
            'contrato' => $contrato,
            'customMessage' => $this->customMessage,
            'billingUrl' => $billingUrl,
            'receiptUrl' => $receiptUrl,
        ]);
    }
}
