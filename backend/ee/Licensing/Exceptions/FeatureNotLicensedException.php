<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FeatureNotLicensedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct(__('This feature requires an active Hi.Events Enterprise licence'));
    }
}
