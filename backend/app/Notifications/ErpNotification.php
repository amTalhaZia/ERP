<?php

namespace App\Notifications;

use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for every in-app notification. Subclasses provide a stable
 * `type()` slug (e.g. `user.invited`, `leave.approved`) plus title/body/url;
 * the database payload shape is fixed so the notification centre can render
 * any module's notification the same way.
 *
 * Channels honour the recipient's `preferences.notifications[type]`
 * ({in_app: bool, email: bool}); missing keys fall back to the defaults below.
 */
abstract class ErpNotification extends Notification
{
    use Queueable;

    /** Whether the e-mail channel is on when the recipient has no preference. */
    protected bool $emailByDefault = true;

    /** Whether the in-app (database) channel is on when the recipient has no preference. */
    protected bool $inAppByDefault = true;

    protected ?int $companyId = null;

    public function __construct()
    {
        $this->companyId = app(TenantContext::class)->id();
    }

    abstract public function type(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    public function url(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [];
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function forCompany(?int $companyId): static
    {
        $this->companyId = $companyId;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Type slugs contain dots, so index the map directly rather than via a dotted path.
        $all = (array) data_get($notifiable, 'preferences.notifications', []);
        $preferences = (array) ($all[$this->type()] ?? []);

        $channels = [];

        if ((bool) ($preferences['in_app'] ?? $this->inAppByDefault)) {
            $channels[] = 'database';
        }

        if ((bool) ($preferences['email'] ?? $this->emailByDefault) && $this->canEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array{type: string, title: string, body: string, url: ?string, company_id: ?int, meta: array<string, mixed>}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'company_id' => $this->companyId(),
            'meta' => $this->meta(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.(data_get($notifiable, 'name') ?: 'there').',')
            ->line($this->body());

        if ($this->url() !== null) {
            $mail->action('Open', $this->url());
        }

        return $mail;
    }

    protected function canEmail(object $notifiable): bool
    {
        return is_string(data_get($notifiable, 'email')) && data_get($notifiable, 'email') !== '';
    }

    protected function frontendUrl(string $path = ''): string
    {
        return rtrim((string) config('erp.frontend_url'), '/').'/'.ltrim($path, '/');
    }
}
