<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\Request;

abstract class BaseCashlessSalesPointAction extends BaseAction
{
    private const SESSION_HEADER = 'X-Cashless-Session';

    protected function getSessionToken(Request $request): ?string
    {
        return $request->header(self::SESSION_HEADER);
    }
}
