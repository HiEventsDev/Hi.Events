<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\SendTestCampaignHandler;
use HiEvents\Validators\Rules\MobilePhoneNumberRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SendTestCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly SendTestCampaignHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        $email = $this->getAuthenticatedUser()->getEmail();
        $phone = $request->filled('phone') ? $request->validate(['phone' => MobilePhoneNumberRule::rules(required: true)])['phone'] : null;

        try {
            $sentTo = $this->handler->handle($organizerId, $campaignId, $email, $this->signedInRecipientName(), $phone);
        } catch (CampaignNotSendableException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse(['message' => __('Test sent to :address', ['address' => $sentTo])]);
    }
}
