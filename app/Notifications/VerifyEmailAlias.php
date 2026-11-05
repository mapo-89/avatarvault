<?php

namespace App\Notifications;

use App\Models\EmailAlias;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailAlias extends Notification
{
    public function __construct(public readonly EmailAlias $alias) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function verificationUrl(): string
    {
        return URL::temporarySignedRoute('email-aliases.verify',
            now()->addMinutes(config('avatarvault.email_aliases.verification_minutes')),
            ['alias' => $this->alias->id, 'hash' => sha1($this->alias->email)]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('avatarvault.alias_mail_subject'))
            ->line(__('avatarvault.alias_mail_description', ['email' => $this->alias->email]))
            ->action(__('avatarvault.alias_verify'), $this->verificationUrl())
            ->line(__('avatarvault.alias_mail_expiry', ['minutes' => config('avatarvault.email_aliases.verification_minutes')]))
            ->line(__('avatarvault.alias_mail_ignore'));
    }
}
