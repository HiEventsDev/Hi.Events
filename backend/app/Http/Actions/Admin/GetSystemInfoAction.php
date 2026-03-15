<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetSystemInfoAction extends BaseAction
{
    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $version = trim(@file_get_contents(base_path('VERSION')) ?: 'unknown');

        return $this->jsonResponse([
            'version' => $version,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'os' => PHP_OS,
        ]);
    }
}
