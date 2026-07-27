<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Resources\Location\LocationResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizerDomainObject
 */
class OrganizerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'phone' => $this->getPhone(),
            'website' => $this->getWebsite(),
            'description' => $this->getDescription(),
            'timezone' => $this->getTimezone(),
            'currency' => $this->getCurrency(),
            'slug' => $this->getSlug(),
            'status' => $this->getStatus(),
            'location_id' => $this->getLocationId(),
            'location' => $this->when(
                condition: $this->getLocationRecord() !== null,
                value: fn () => new LocationResource($this->getLocationRecord()),
            ),
            'images' => $this->when(
                (bool) $this->getImages(),
                fn () => ImageResource::collection($this->getImages())
            ),
            'settings' => $this->when(
                condition: ! is_null($this->getOrganizerSettings()),
                value: fn () => new OrganizerSettingsResource($this->getOrganizerSettings())
            ),
            $this->mergeWhen(
                config('app.saas_mode_enabled') && $this->getOrganizerStripePlatforms() !== null,
                fn () => [
                    'stripe_connect_setup_complete' => $this->isStripeSetupComplete(),
                    'stripe_account_id' => $this->getActiveStripeAccountId(),
                ],
            ),
            $this->mergeWhen(
                $this->getOrganizerConfiguration() !== null,
                fn () => [
                    'configuration' => new OrganizerConfigurationResource($this->getOrganizerConfiguration()),
                ],
            ),
        ];
    }
}
