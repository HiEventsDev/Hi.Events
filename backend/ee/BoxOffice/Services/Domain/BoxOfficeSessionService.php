<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use Carbon\Carbon;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\CreatedBoxOfficeSessionDTO;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

class BoxOfficeSessionService
{
    public const SESSION_TTL_HOURS = 24;

    private const TOKEN_PREFIX = 'bos_';

    private const CACHE_KEY_PREFIX = 'box_office_session:';

    /**
     * @var array<string, BoxOfficeSessionDTO|null>
     */
    private array $resolved = [];

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function create(
        BoxOfficeDomainObject $boxOffice,
        string $operatorName,
        ?int $eventOccurrenceId,
        ?int $stripeTerminalReaderId,
        ?int $authenticatedUserId = null,
        ?int $authenticatedAccountId = null,
    ): CreatedBoxOfficeSessionDTO {
        $token = self::TOKEN_PREFIX.Str::random(48);
        $expiresAt = Carbon::now()->addHours(self::SESSION_TTL_HOURS);

        $session = new BoxOfficeSessionDTO(
            box_office_id: $boxOffice->getId(),
            event_id: $boxOffice->getEventId(),
            operator_name: $operatorName,
            event_occurrence_id: $eventOccurrenceId,
            stripe_terminal_reader_id: $stripeTerminalReaderId,
            pin_hash: $boxOffice->getPinHash(),
            expires_at: $expiresAt->toIso8601String(),
            authenticated_user_id: $authenticatedUserId,
            authenticated_account_id: $authenticatedAccountId,
        );

        $this->cache->put($this->cacheKey($token), $session->toArray(), $expiresAt);

        return new CreatedBoxOfficeSessionDTO(token: $token, session: $session);
    }

    public function update(string $token, BoxOfficeSessionDTO $session): void
    {
        $this->cache->put($this->cacheKey($token), $session->toArray(), Carbon::parse($session->expires_at));
        unset($this->resolved[$token]);
    }

    public function forget(?string $token): void
    {
        if ($token !== null) {
            $this->cache->forget($this->cacheKey($token));
            unset($this->resolved[$token]);
        }
    }

    public function resolve(?string $token): ?BoxOfficeSessionDTO
    {
        if ($token === null || ! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        if (! array_key_exists($token, $this->resolved)) {
            $stored = $this->cache->get($this->cacheKey($token));
            $this->resolved[$token] = is_array($stored) ? BoxOfficeSessionDTO::from($stored) : null;
        }

        return $this->resolved[$token];
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_KEY_PREFIX.hash('sha256', $token);
    }
}
