<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\OauthClientDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OauthClient extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            OauthClientDomainObjectAbstract::REDIRECT_URIS => 'array',
        ];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(OauthConnection::class);
    }
}
