<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\SignupForms;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\DomainObjects\Generated\CrmSignupFormDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmPendingSubscriptionRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Enterprise\Crm\Resources\SignupFormResource;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use Illuminate\Http\JsonResponse;

class GetSignupFormsAction extends BaseCrmAction
{
    public function __construct(
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
        private readonly CrmPendingSubscriptionRepositoryInterface $pendingRepository,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $waiting = $this->pendingRepository->countWaitingByForm($organizerId, now());
        $forms = $this->signupFormRepository->findWhere(
            where: [CrmSignupFormDomainObjectAbstract::ORGANIZER_ID => $organizerId],
            orderAndDirections: [new OrderAndDirection(CrmSignupFormDomainObjectAbstract::ID, OrderAndDirection::DIRECTION_DESC)],
        )->each(static fn (CrmSignupFormDomainObject $form) => $form->setWaitingCount($waiting[$form->getId()] ?? 0));

        return $this->resourceResponse(SignupFormResource::class, $forms);
    }
}
