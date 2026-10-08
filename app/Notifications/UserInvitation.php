<?php

namespace App\Notifications;

use App\Support\InvitationTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = InvitationTemplate::settings();
        $render = fn (string $key) => InvitationTemplate::render((string) ($settings[$key] ?? ''), $notifiable);
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $mail = (new MailMessage)
            ->subject($render('subject'))
            ->greeting($render('greeting'))
            ->line($render('message'))
            ->action($render('button_text'), $url)
            ->line('This link expires according to your password reset configuration. If it expires, use Forgot Password to request another.');

        if ($render('footer') !== '') {
            $mail->line($render('footer'));
        }

        return $mail;
    }
}
