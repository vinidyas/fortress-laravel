<?php

namespace App\Services;

use App\Mail\FaturaInvoiceMail;
use App\Models\Fatura;
use App\Models\FaturaEmailLog;
use App\Models\FaturaAnexo;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FaturaEmailService
{
    private const FIXED_CC = ['atendimento@fortressempreendimentos.com.br'];

    /**
     * @return array{to: array<int, string>, cc: array<int, string>}
     */
    public function buildDefaults(Fatura $fatura): array
    {
        $fatura->loadMissing([
            'contrato.locatario',
            'contrato.locador',
        ]);

        $to = $this->extractEmails(optional($fatura->contrato?->locatario)->email);

        return [
            'to' => $to,
            'cc' => $this->ensureFixedCc([], $to),
        ];
    }

    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  iterable<int, FaturaAnexo>  $attachments
     */
    public function send(
        Fatura $fatura,
        array $to,
        array $cc = [],
        array $bcc = [],
        ?string $message = null,
        ?User $user = null,
        iterable $attachments = []
    ): FaturaEmailLog
    {
        $normalized = $this->normalizeRecipientLists($to, $cc, $bcc);

        if (empty($normalized['to'])) {
            throw ValidationException::withMessages([
                'recipients' => 'Informe ao menos um destinatário para envio.',
            ]);
        }

        $fatura->loadMissing([
            'contrato.locatario',
            'contrato.locador',
            'contrato.imovel',
            'itens',
        ]);

        $subject = $this->makeSubject($fatura);
        $mailable = (new FaturaInvoiceMail($fatura, $message))
            ->subject($subject);

        $attachments = collect($attachments)
            ->filter(fn ($attachment) => $attachment instanceof FaturaAnexo);

        $attachments->each(function (FaturaAnexo $attachment) use ($mailable) {
            $name = $this->formatAttachmentName($attachment);
            $options = [];

            if ($attachment->mime_type) {
                $options['mime'] = $attachment->mime_type;
            }

            $mailable->attachFromStorageDisk('public', $attachment->path, $name, $options);
        });

        $attachmentsForLog = $attachments
            ->map(fn (FaturaAnexo $attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'display_name' => $attachment->display_name ?? $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ])
            ->values()
            ->all();

        $mailer = Mail::to($normalized['to']);

        if (! empty($normalized['cc'])) {
            $mailer->cc($normalized['cc']);
        }

        if (! empty($normalized['bcc'])) {
            $mailer->bcc($normalized['bcc']);
        }

        $status = 'sent';
        $error = null;

        try {
            $mailer->send($mailable);
        } catch (Throwable $exception) {
            $status = 'failed';
            $error = Str::limit($exception->getMessage(), 1000);
            $this->registerLog($fatura, $subject, $normalized, $message, $status, $error, $user, $attachmentsForLog);

            throw $exception;
        }

        return $this->registerLog($fatura, $subject, $normalized, $message, $status, $error, $user, $attachmentsForLog);
    }

    /**
     * @return array{to: array<int, string>, cc: array<int, string>, bcc: array<int, string>}
     */
    public function normalizeRecipientLists(array $to, array $cc = [], array $bcc = []): array
    {
        $primary = $this->sanitizeEmails($to);
        $copy = $this->ensureFixedCc(
            array_values(array_diff($this->sanitizeEmails($cc), $primary)),
            $primary
        );
        $blindCopy = array_values(array_diff($this->sanitizeEmails($bcc), array_merge($primary, $copy)));

        return [
            'to' => $primary,
            'cc' => $copy,
            'bcc' => $blindCopy,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sanitizeEmails(array $emails): array
    {
        return collect($emails)
            ->flatMap(function ($item) {
                if (is_array($item)) {
                    return $item;
                }

                return preg_split('/[,\s;]+/', (string) $item) ?: [];
            })
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function extractEmails(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return $this->sanitizeEmails([$raw]);
    }

    /**
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $to
     * @return array<int, string>
     */
    private function ensureFixedCc(array $cc, array $to = []): array
    {
        $fixed = $this->sanitizeEmails(self::FIXED_CC);
        $merged = array_unique(array_merge($cc, $fixed));

        if ($to !== []) {
            $merged = array_values(array_diff($merged, $to));
        } else {
            $merged = array_values($merged);
        }

        return $merged;
    }

    protected function registerLog(
        Fatura $fatura,
        string $subject,
        array $normalizedRecipients,
        ?string $message,
        string $status,
        ?string $error,
        ?User $user,
        array $attachments
    ): FaturaEmailLog {
        return $fatura->emailLogs()->create([
            'user_id' => $user?->getKey(),
            'subject' => $subject,
            'recipients' => $normalizedRecipients['to'],
            'cc' => $normalizedRecipients['cc'] ?: null,
            'bcc' => $normalizedRecipients['bcc'] ?: null,
            'attachments' => $attachments ?: null,
            'message' => $message,
            'status' => $status,
            'error_message' => $error,
        ]);
    }

    protected function makeSubject(Fatura $fatura): string
    {
        $codigoContrato = optional($fatura->contrato)->codigo_contrato;
        $competencia = optional($fatura->competencia)->format('m/Y');
        $identificador = $codigoContrato
            ? sprintf('Contrato %s', $codigoContrato)
            : sprintf('Fatura #%d', $fatura->id);

        if ($competencia) {
            return sprintf('%s - Fatura %s', $identificador, $competencia);
        }

        return sprintf('%s - Fatura %d', $identificador, $fatura->id);
    }

    protected function formatAttachmentName(FaturaAnexo $attachment): string
    {
        $rawName = $attachment->display_name ?? $attachment->original_name ?? basename($attachment->path);
        $preparedName = str_replace(['/', '\\'], '-', $rawName);
        $pathInfo = pathinfo($preparedName);

        $baseName = $pathInfo['filename'] ?? $preparedName;
        $extension = $pathInfo['extension'] ?? null;

        if (! $extension) {
            $pathPrepared = str_replace(['/', '\\'], '-', $attachment->path);
            $extension = pathinfo($pathPrepared, PATHINFO_EXTENSION) ?: null;
        }

        $normalizedBase = trim(preg_replace('/\s+/', ' ', Str::ascii($baseName)));
        $normalizedBase = preg_replace('/[^\w\s.-]+/', '', $normalizedBase) ?: '';
        $normalizedBase = preg_replace('/\s+/', ' ', $normalizedBase);
        $normalizedBase = $normalizedBase !== '' ? $normalizedBase : 'anexo_'.$attachment->getKey();

        $normalizedExtension = $extension
            ? preg_replace('/[^A-Za-z0-9]+/', '', Str::ascii($extension))
            : null;

        $filename = $normalizedBase;

        if ($normalizedExtension) {
            $filename .= '.'.$normalizedExtension;
        }

        return $filename;
    }
}
