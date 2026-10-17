<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Mail\Account\EmailConfirmationCodeEmail;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use Illuminate\Contracts\Mail\Mailer;

class SendSsoSignupCodeHandler
{
    public function __construct(
        private readonly SsoTicketService $ticketService,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws SsoException
     */
    public function handle(string $signupTicket, string $binding, ?string $locale): void
    {
        $payload = $this->ticketService->find(SsoTicketType::SIGNUP, $signupTicket, $binding);
        $claims = SsoIdentityClaims::from($payload['claims']);

        if ($claims->emailTrusted || $claims->email === null) {
            throw new SsoException(SsoErrorCode::TICKET_INVALID, __('Your sign-in session has expired. Please sign in again.'));
        }

        $code = (string) random_int(100000, 999999);

        $this->ticketService->update(SsoTicketType::SIGNUP, $signupTicket, [
            ...$payload,
            'email_code_hash' => hash('sha256', $code),
        ]);

        $this->mailer
            ->to($claims->email)
            ->locale($locale ?? config('app.locale'))
            ->send(new EmailConfirmationCodeEmail($code));
    }
}
