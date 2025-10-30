<?php

declare(strict_types=1);

namespace App\Notifications;

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
        $url = url(route('password.reset', [
            'token' => $this->token,
            'username' => $this->username,
        ], false));

        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage())
            ->subject(Lang::get('Redefinição de senha'))
            ->line(Lang::get('Recebemos uma solicitação para redefinir a senha da sua conta.'))
            ->action(Lang::get('Redefinir senha'), $url)
            ->line(Lang::get('Este link expira em :count minutos.', ['count' => $expire]))
            ->line(Lang::get('Se você não solicitou a redefinição, ignore este e-mail.'));
    }
}
