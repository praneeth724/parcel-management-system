<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Password reset email pointing at this application's own reset route.
 *
 * Laravel's built-in notification links to `password.reset`, which is only
 * registered by Breeze/Fortify; this app defines its own auth routes.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        $expiryMinutes = Config::get('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->greeting("Hello {$notifiable->name},")
            ->line('We received a request to reset the password for your account.')
            ->action('Reset Password', url($url))
            ->line("This link expires in {$expiryMinutes} minutes.")
            ->line('If you did not request a password reset, no further action is required and your password stays unchanged.')
            ->salutation('— The '.config('app.name').' Team');
    }
}
