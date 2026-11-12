<?php

namespace App\Notifications;

use App\Mail\StandardEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): StandardEmail
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        if (! str_starts_with($frontendUrl, 'http://') && ! str_starts_with($frontendUrl, 'https://')) {
            $frontendUrl = 'http://'.$frontendUrl;
        }

        $resetUrl = $frontendUrl.'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->email);

        return (new StandardEmail(
            title: 'Reset Your Custospark Events Password',
            mailBody: '
                <p>Hello <strong>'.e($notifiable->name).'</strong>,</p>
                <p>You are receiving this email because we received a password reset request for your Custospark Events account.</p>
                <p style="font-size:14px; color:#64748b;">This password reset link will expire in 60 minutes.</p>
                <p style="font-size:14px; color:#64748b;">If you did not request a password reset, no further action is required.</p>
            ',
            ctaUrl: $resetUrl,
            ctaLabel: 'Reset My Password',
            tip: 'Never share this email with anyone. Custospark Events will never ask for your password.',
            isHtml: true,
        ))->to($notifiable->email);
    }
}
