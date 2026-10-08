<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficePublicDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReaderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalReaderService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

class GetBoxOfficePublicHandler
{
    private const READERS_CACHE_SECONDS = 30;

    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly StripeTerminalReaderService $readerService,
        private readonly BoxOfficeOperatorAuthorizer $operatorAuthorizer,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(string $shortId, ?UserDomainObject $user = null, ?int $accountId = null): BoxOfficePublicDTO
    {
        $boxOffice = $this->boxOfficeRepository
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, nested: [
                new Relationship(domainObject: EventSettingDomainObject::class, name: 'event_settings'),
                new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrences'),
            ], name: 'event'))
            ->findFirstWhere(['short_id' => $shortId]);

        if ($boxOffice === null) {
            throw new ResourceNotFoundException(__('Box office not found'));
        }

        $readers = $this->availableReaders($boxOffice->getEvent()->getOrganizerId());

        return new BoxOfficePublicDTO(
            box_office: $boxOffice,
            readers: $readers,
            card_payments_enabled: $readers->isNotEmpty(),
            can_skip_pin: $this->operatorAuthorizer->canOperateWithoutPin($boxOffice->getEvent(), $user, $accountId),
        );
    }

    /**
     * @return Collection<TerminalReaderDTO>
     */
    private function availableReaders(int $organizerId): Collection
    {
        $readers = $this->cache->remember(
            'box_office_readers:'.$organizerId,
            self::READERS_CACHE_SECONDS,
            fn () => $this->readerService
                ->listForOrganizer($this->organizerRepository
                    ->loadRelation(OrganizerStripePlatformDomainObject::class)
                    ->findById($organizerId))
                ->readers
                ->filter(fn (TerminalReaderDTO $reader) => $reader->is_available)
                ->map(fn (TerminalReaderDTO $reader) => $reader->toArray())
                ->values()
                ->all(),
        );

        return collect($readers)->map(fn (array $reader) => TerminalReaderDTO::from($reader));
    }
}
