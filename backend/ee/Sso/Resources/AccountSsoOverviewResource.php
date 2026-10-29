<?php

namespace HiEvents\Enterprise\Sso\Resources;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\DTO\AccountSsoOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountSsoOverview
 */
class AccountSsoOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'connection' => $this->connection === null ? null : new AccountSsoConnectionResource($this->connection),
            'domains' => AccountSsoDomainResource::collection($this->domains),
            'redirect_uri' => $this->redirectUri,
            'stats' => [
                'domain_count' => $this->domains->count(),
                'verified_domain_count' => $this->domains->filter(fn (AccountSsoDomainDomainObject $domain) => $domain->isUsable())->count(),
                'sso_sign_ins_last_30_days' => $this->ssoSignInsLast30Days,
            ],
        ];
    }
}
