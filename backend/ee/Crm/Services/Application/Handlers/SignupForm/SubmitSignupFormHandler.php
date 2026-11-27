<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm;

use HiEvents\DomainObjects\Generated\CrmPendingSubscriptionDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmPendingSubscriptionStatus;
use HiEvents\Enterprise\Crm\Exceptions\SignupVerificationFailedException;
use HiEvents\Enterprise\Crm\Http\Request\Rules\NoLinksRule;
use HiEvents\Enterprise\Crm\Mail\SubscriptionConfirmationMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmPendingSubscriptionRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO\SubmitSignupFormDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupFormPublicLookup;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupSubmissionLimiter;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SubscriptionTokenService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\Url;
use HiEvents\Services\Infrastructure\Turnstile\TurnstileVerifier;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;

class SubmitSignupFormHandler
{
    public const CONFIRMATION_DAYS = 7;

    public function __construct(
        private readonly SignupFormPublicLookup $lookup,
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
        private readonly CrmPendingSubscriptionRepositoryInterface $pendingRepository,
        private readonly ContactEmailNormalizer $emailNormalizer,
        private readonly SignupSubmissionLimiter $limiter,
        private readonly SubscriptionTokenService $tokenService,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SignupVerificationFailedException
     */
    public function handle(SubmitSignupFormDTO $dto): void
    {
        $form = $this->lookup->findActive($dto->public_id) ?? throw new ResourceNotFoundException(__('This signup form isn\'t available.'));

        if (! $this->turnstileVerifier->verify($dto->turnstile_token, $dto->ip_address, passWhenUnreachable: false)) {
            throw new SignupVerificationFailedException(__('We couldn\'t verify you\'re not a robot. Please try again.'));
        }

        $email = $this->emailNormalizer->normalize($dto->email);

        if ($email === null || ($dto->honeypot ?? '') !== '' || ! $this->limiter->attempt($form->getOrganizerId(), $form->getId(), $email)) {
            return;
        }

        $token = $this->tokenService->generate();
        $organizer = $form->getOrganizer();

        $this->databaseManager->transaction(function () use ($dto, $form, $email, $token): void {
            $resubmittedForms = $this->pendingRepository->supersedePending($form->getOrganizerId(), $email);
            $this->pendingRepository->create([
                CrmPendingSubscriptionDomainObjectAbstract::ORGANIZER_ID => $form->getOrganizerId(),
                CrmPendingSubscriptionDomainObjectAbstract::FORM_ID => $form->getId(),
                CrmPendingSubscriptionDomainObjectAbstract::EMAIL => $email,
                CrmPendingSubscriptionDomainObjectAbstract::FIRST_NAME => $form->getAsksForName() ? $dto->first_name : null,
                CrmPendingSubscriptionDomainObjectAbstract::LAST_NAME => $form->getAsksForName() ? $dto->last_name : null,
                CrmPendingSubscriptionDomainObjectAbstract::LOCALE => $dto->locale,
                CrmPendingSubscriptionDomainObjectAbstract::TOKEN_HASH => $token->hash,
                CrmPendingSubscriptionDomainObjectAbstract::IP_ADDRESS => $dto->ip_address,
                CrmPendingSubscriptionDomainObjectAbstract::USER_AGENT => $dto->user_agent === null ? null : mb_substr($dto->user_agent, 0, 255),
                CrmPendingSubscriptionDomainObjectAbstract::STATUS => CrmPendingSubscriptionStatus::PENDING->value,
                CrmPendingSubscriptionDomainObjectAbstract::EXPIRES_AT => now()->addDays(self::CONFIRMATION_DAYS)->toDateTimeString(),
                CrmPendingSubscriptionDomainObjectAbstract::CREATED_AT => now()->toDateTimeString(),
            ]);
            if (! in_array($form->getId(), $resubmittedForms, true)) {
                $this->signupFormRepository->recordSubmission($form->getId());
            }
        });

        $organizerName = $organizer?->getName();

        $this->mailer->to($email)->locale($dto->locale)->send(new SubscriptionConfirmationMail(
            organizerName: $organizerName !== null && NoLinksRule::isSafe($organizerName) ? trim($organizerName) : null,
            confirmUrl: sprintf(Url::getFrontEndUrlFromConfig(Url::CRM_CONFIRM_SUBSCRIPTION), $token->token),
            expiresInDays: self::CONFIRMATION_DAYS,
        ));
    }
}
