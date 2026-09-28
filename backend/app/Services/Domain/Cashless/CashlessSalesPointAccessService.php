<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CashlessSalesPointDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Str;

class CashlessSalesPointAccessService
{
    private const SESSION_TTL_SECONDS = 43200;

    public function __construct(
        private readonly CashlessSalesPointRepositoryInterface $salesPointRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly HashManager $hashManager,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     */
    public function authenticate(string $shortId, ?string $pin): string
    {
        $salesPoint = $this->findOpenSalesPoint($shortId);

        if ($salesPoint->getAccessPin() !== null
            && ! $this->hashManager->check((string) $pin, $salesPoint->getAccessPin())) {
            throw new CashlessSalesPointAccessException(__('That PIN is not correct.'));
        }

        $token = Str::random(40);

        $this->cache->put($this->cacheKey($shortId, $token), $salesPoint->getId(), self::SESSION_TTL_SECONDS);

        return $token;
    }

    /**
     * @throws CashlessSalesPointAccessException
     */
    public function resolveAuthorised(string $shortId, ?string $token): CashlessSalesPointDomainObject
    {
        $salesPoint = $this->findOpenSalesPoint($shortId);

        if ($salesPoint->getAccessPin() === null) {
            return $salesPoint;
        }

        if ($token === null || $this->cache->get($this->cacheKey($shortId, $token)) !== $salesPoint->getId()) {
            throw new CashlessSalesPointAccessException(__('This till session has expired. Please enter the PIN again.'));
        }

        return $salesPoint;
    }

    public function resolveUnauthenticated(string $shortId): ?CashlessSalesPointDomainObject
    {
        try {
            return $this->findOpenSalesPoint($shortId);
        } catch (CashlessSalesPointAccessException) {
            return null;
        }
    }

    /**
     * @throws CashlessSalesPointAccessException
     */
    private function findOpenSalesPoint(string $shortId): CashlessSalesPointDomainObject
    {
        $salesPoint = $this->salesPointRepository
            ->loadRelation(new Relationship(ProductDomainObject::class, nested: [
                new Relationship(ProductPriceDomainObject::class),
                new Relationship(TaxAndFeesDomainObject::class),
            ]))
            ->findFirstWhere([
                CashlessSalesPointDomainObjectAbstract::SHORT_ID => $shortId,
            ]);

        if ($salesPoint === null) {
            throw new CashlessSalesPointAccessException(__('This sales point could not be found.'));
        }

        /** @var EventDomainObject $event */
        $event = $this->eventRepository->findById($salesPoint->getEventId());

        if ($salesPoint->isExpired($event->getTimezone()) || ! $salesPoint->isActivated($event->getTimezone())) {
            throw new CashlessSalesPointAccessException(__('This sales point is not currently open.'));
        }

        return $salesPoint->setEvent($event);
    }

    private function cacheKey(string $shortId, string $token): string
    {
        return sprintf('cashless_sales_point_session_%s_%s', $shortId, $token);
    }
}
