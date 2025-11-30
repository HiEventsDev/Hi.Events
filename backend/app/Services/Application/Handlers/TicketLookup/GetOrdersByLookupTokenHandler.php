<?php

namespace HiEvents\Services\Application\Handlers\TicketLookup;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\TicketLookupTokenDomainObject;
use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketLookupTokenRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketLookup\DTO\GetOrdersByLookupTokenDTO;
use Illuminate\Support\Collection;

class GetOrdersByLookupTokenHandler
{
    public function __construct(
        private readonly TicketLookupTokenRepositoryInterface $ticketLookupTokenRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * @throws InvalidTicketLookupTokenException
     * @return Collection<OrderDomainObject>
     */
    public function handle(GetOrdersByLookupTokenDTO $dto): Collection
    {
        $tokenRecord = $this->validateAndFetchToken($dto->token);

        return $this->getOrdersForEmail($tokenRecord->getEmail());
    }

    /**
     * @throws InvalidTicketLookupTokenException
     */
    private function validateAndFetchToken(string $token): TicketLookupTokenDomainObject
    {
        $tokenRecord = $this->ticketLookupTokenRepository->findFirstWhere(['token' => $token]);

        if (!$tokenRecord) {
            throw new InvalidTicketLookupTokenException(__('Invalid or expired link. Please request a new one.'));
        }

        if ($this->isTokenExpired($tokenRecord->getExpiresAt())) {
            throw new InvalidTicketLookupTokenException(__('This link has expired. Please request a new one.'));
        }

        return $tokenRecord;
    }

    private function isTokenExpired(string $expiresAt): bool
    {
        return (new Carbon($expiresAt))->isPast();
    }

    /**
     * @return Collection<OrderDomainObject>
     */
    private function getOrdersForEmail(string $email): Collection
    {
        return $this->orderRepository
            ->loadRelation(new Relationship(
                domainObject: AttendeeDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: ProductDomainObject::class,
                        nested: [
                            new Relationship(
                                domainObject: ProductPriceDomainObject::class,
                            )
                        ],
                        name: ProductDomainObjectAbstract::SINGULAR_NAME,
                    )
                ],
            ))
            ->loadRelation(new Relationship(
                domainObject: EventDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventSettingDomainObject::class,
                    ),
                    new Relationship(
                        domainObject: OrganizerDomainObject::class,
                        name: OrganizerDomainObjectAbstract::SINGULAR_NAME,
                    ),
                    new Relationship(
                        domainObject: ImageDomainObject::class,
                    )
                ],
                name: EventDomainObjectAbstract::SINGULAR_NAME
            ))
            ->findWhere(
                [
                    [OrderDomainObjectAbstract::EMAIL, '=', $email],
                    [OrderDomainObjectAbstract::STATUS, '=', OrderStatus::COMPLETED->name],
                ],
                orderAndDirections: [
                    new OrderAndDirection(OrderDomainObjectAbstract::CREATED_AT, 'desc'),
                ],
            );
    }
}
