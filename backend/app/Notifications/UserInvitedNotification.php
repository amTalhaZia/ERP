<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to a user when they are added to a company. New accounts also receive
 * the standard password-reset e-mail (sent separately by the invite action) so
 * they can choose their own password; this message never carries credentials.
 */
class UserInvitedNotification extends ErpNotification
{
    public const TYPE = 'user.invited';

    public function __construct(
        private readonly Company $company,
        private readonly ?User $invitedBy = null,
        private readonly bool $isNewAccount = false,
    ) {
        parent::__construct();

        $this->companyId = $company->id;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function title(): string
    {
        return "You've been added to {$this->company->name}";
    }

    public function body(): string
    {
        $by = $this->invitedBy ? " by {$this->invitedBy->name}" : '';

        return $this->isNewAccount
            ? "An account was created for you{$by}. Check your inbox for a link to set your password, then sign in."
            : "You were added to {$this->company->name}{$by}. Switch to it from the company menu after signing in.";
    }

    public function url(): ?string
    {
        return $this->frontendUrl('/login');
    }

    public function meta(): array
    {
        return [
            'company_name' => $this->company->name,
            'invited_by_id' => $this->invitedBy?->id,
            'is_new_account' => $this->isNewAccount,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.(data_get($notifiable, 'name') ?: 'there').',')
            ->line($this->body());

        if (! $this->isNewAccount) {
            $mail->action('Sign in', $this->url());
        }

        return $mail;
    }
}
