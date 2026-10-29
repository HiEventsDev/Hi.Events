<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FeatureNotLicensedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct(__('This feature needs an active Hi.Events licence that includes it'));
    }
}
