<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly string $username,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baseUrl = config('app.url');

        if ($notifiable instanceof User && $notifiable->hasTenantAccess()) {
            $baseUrl = config('app.portal_url', $baseUrl);
        }

        $path = route('password.reset', [
            'token' => $this->token,
            'username' => $this->username,
        ], false);

        $url = rtrim((string) $baseUrl, '/').$path;

        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage())
            ->subject(Lang::get('Redefinição de senha'))
            ->line(Lang::get('Recebemos uma solicitação para redefinir a senha da sua conta.'))
            ->action(Lang::get('Redefinir senha'), $url)
            ->line(Lang::get('Este link expira em :count minutos.', ['count' => $expire]))
            ->line(Lang::get('Se você não solicitou a redefinição, ignore este e-mail.'));
    }
}
