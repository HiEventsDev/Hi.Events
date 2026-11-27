<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\SignupForms;

use HiEvents\DomainObjects\Generated\CrmSignupFormDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteSignupFormAction extends BaseCrmAction
{
    public function __construct(
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
    ) {}

    public function __invoke(int $organizerId, int $formId): Response|JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $deleted = $this->signupFormRepository->deleteWhere([
            CrmSignupFormDomainObjectAbstract::ID => $formId,
            CrmSignupFormDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($deleted === 0) {
            return $this->errorResponse(__('Signup form not found'), Response::HTTP_NOT_FOUND);
        }

        return $this->deletedResponse();
    }
}
