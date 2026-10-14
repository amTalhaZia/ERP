<?php

namespace App\Notifications;

/**
 * Ready-made notification for modules that do not need a dedicated class:
 *
 *   $user->notify(new GenericNotification('leave.approved', 'Leave approved', 'Your leave…', '/leave/12'));
 *
 * `$url` may be absolute or a frontend path. E-mail is off by default; pass
 * `email: true` to opt in (the recipient's preferences still win).
 *
 * @param  array<string, mixed>  $meta
 */
class GenericNotification extends ErpNotification
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $url = null,
        private readonly array $meta = [],
        bool $email = false,
        ?int $companyId = null,
    ) {
        parent::__construct();

        $this->emailByDefault = $email;

        if ($companyId !== null) {
            $this->companyId = $companyId;
        }
    }

    public function type(): string
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function url(): ?string
    {
        if ($this->url === null || $this->url === '') {
            return null;
        }

        return str_starts_with($this->url, 'http') ? $this->url : $this->frontendUrl($this->url);
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
