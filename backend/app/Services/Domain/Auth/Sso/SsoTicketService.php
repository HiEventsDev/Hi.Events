<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Exceptions\SsoException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

class SsoTicketService
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    public static function newSecret(): string
    {
        return Str::random(64);
    }

    public static function hashBinding(string $binding): string
    {
        return hash('sha256', $binding);
    }

    public function issue(SsoTicketType $type, array $payload): string
    {
        $ticket = self::newSecret();

        $this->update($type, $ticket, $payload);

        return $ticket;
    }

    /**
     * @throws SsoException
     */
    public function find(SsoTicketType $type, string $ticket, ?string $binding = null): array
    {
        $payload = $this->cache->get($this->key($type, $ticket));

        if (! is_array($payload)) {
            throw $this->invalid($type);
        }

        $bindingHash = $payload['binding_hash'] ?? null;

        if ($bindingHash !== null && ($binding === null || ! hash_equals($bindingHash, self::hashBinding($binding)))) {
            throw $this->invalid($type);
        }

        return $payload;
    }

    public function update(SsoTicketType $type, string $ticket, array $payload): void
    {
        $this->cache->put($this->key($type, $ticket), $payload, $type->ttlSeconds());
    }

    /**
     * @throws SsoException
     */
    public function consume(SsoTicketType $type, string $ticket, ?string $binding = null): array
    {
        $payload = $this->find($type, $ticket, $binding);

        if (! $this->cache->add($this->key($type, $ticket).':consumed', true, $type->ttlSeconds())) {
            throw $this->invalid($type);
        }

        $this->forget($type, $ticket);

        return $payload;
    }

    public function forget(SsoTicketType $type, string $ticket): void
    {
        $this->cache->forget($this->key($type, $ticket));
    }

    private function key(SsoTicketType $type, string $ticket): string
    {
        return 'sso_ticket:'.$type->value.':'.hash('sha256', $ticket);
    }

    private function invalid(SsoTicketType $type): SsoException
    {
        return $type === SsoTicketType::STATE
            ? new SsoException(SsoErrorCode::STATE_INVALID, __('This sign-in link has expired. Please try again.'))
            : new SsoException(SsoErrorCode::TICKET_INVALID, __('Your sign-in session has expired. Please sign in again.'));
    }
}
