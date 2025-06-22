<?php

namespace HiEvents\Http\Actions\Common;

use HiEvents\DomainObjects\Enums\ColorTheme;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetColorThemesAction extends BaseAction
{
    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse(
            data: ColorTheme::getAllThemes(),
            wrapInData: true,
        );
    }
}
