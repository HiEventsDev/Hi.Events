<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Configurations;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\DeleteConfigurationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DeleteConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly DeleteConfigurationHandler $handler,
    ) {
    }

    public function __invoke(int $configurationId): Response
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        try {
            $this->handler->handle($configurationId);
        } catch (CannotDeleteEntityException $e) {
            throw ValidationException::withMessages([
                'configuration' => [$e->getMessage()],
            ]);
        }

        return $this->deletedResponse();
    }
}
