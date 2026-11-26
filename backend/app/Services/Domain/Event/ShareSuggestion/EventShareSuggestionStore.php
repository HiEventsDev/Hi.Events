<?php

namespace HiEvents\Services\Domain\Event\ShareSuggestion;

use HiEvents\DomainObjects\Enums\ShareCaptionTone;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\ShareCaptionDTO;
use Illuminate\Contracts\Cache\Repository;

class EventShareSuggestionStore
{
    private const int RETENTION_SECONDS = 2592000;

    public function __construct(
        private readonly Repository $cache,
    ) {}

    /**
     * @return ShareCaptionDTO[]
     */
    public function latest(int $eventId): array
    {
        $stored = (array) $this->cache->get($this->key($eventId), []);

        return array_values(array_map(
            static fn (array $caption): ShareCaptionDTO => new ShareCaptionDTO(
                tone: ShareCaptionTone::from($caption['tone']),
                text: $caption['text'],
            ),
            $stored,
        ));
    }

    /**
     * @param  ShareCaptionDTO[]  $captions
     */
    public function replace(int $eventId, array $captions): void
    {
        $this->cache->put(
            $this->key($eventId),
            array_map(
                static fn (ShareCaptionDTO $caption): array => ['tone' => $caption->tone->value, 'text' => $caption->text],
                $captions,
            ),
            self::RETENTION_SECONDS,
        );
    }

    private function key(int $eventId): string
    {
        return 'event-share-suggestions:event:'.$eventId;
    }
}
