<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertSignupFormDTO extends BaseDataObject
{
    /**
     * @param  int[]  $tag_ids
     */
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly string $status,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $button_label,
        public readonly ?string $success_message,
        public readonly bool $asks_for_name,
        public readonly ?string $privacy_url,
        public readonly array $tag_ids,
        public readonly bool $show_on_organizer_page,
        public readonly ?int $form_id = null,
    ) {}
}
