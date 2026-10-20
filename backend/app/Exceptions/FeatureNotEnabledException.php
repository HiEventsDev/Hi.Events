<?php

namespace HiEvents\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FeatureNotEnabledException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct(__('This feature is not enabled for this account'));
    }
}
