<?php

declare(strict_types=1);

namespace HiEvents\Resources\LifecycleEmail;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\LifecycleEmailPreviewDTO;
use Illuminate\Http\Request;

/**
 * @mixin LifecycleEmailPreviewDTO
 */
class LifecycleEmailPreviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->subject,
            'html' => $this->html,
        ];
    }
}
