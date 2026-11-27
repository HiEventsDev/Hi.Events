<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\SubmitSignupFormHandler;

class SubscriptionConfirmationDTO extends BaseDataObject
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const EXPIRED = 'expired';

    public const INVALID = 'invalid';

    public function __construct(
        public readonly string $status,
        public readonly ?int $organizer_id = null,
        public readonly ?string $organizer_name = null,
        public readonly ?string $masked_email = null,
        public readonly ?string $success_message = null,
        public readonly ?string $form_public_id = null,
        public readonly int $link_lifetime_days = SubmitSignupFormHandler::CONFIRMATION_DAYS,
    ) {}
}
