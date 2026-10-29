<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class OrganizerAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        if ($context->organizerIds === []) {
            return [];
        }

        return [
            $this->executor->scrub(
                query: $this->databaseManager->table('organizers')->whereIn('id', $context->organizerIds),
                entity: 'organizers',
                columnStrategies: [
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                    'phone' => AnonymizationStrategy::NULLIFY,
                    'website' => AnonymizationStrategy::NULLIFY,
                    'description' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
            $this->executor->scrub(
                query: $this->databaseManager->table('organizer_settings')->whereIn('organizer_id', $context->organizerIds),
                entity: 'organizer_settings',
                columnStrategies: [
                    'homepage_sections' => AnonymizationStrategy::NULLIFY,
                    'website_url' => AnonymizationStrategy::NULLIFY,
                    'location_details' => AnonymizationStrategy::NULLIFY,
                    'social_media_handles' => AnonymizationStrategy::NULLIFY,
                    'seo_title' => AnonymizationStrategy::NULLIFY,
                    'seo_description' => AnonymizationStrategy::NULLIFY,
                    'seo_keywords' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
            $this->executor->scrub(
                query: $this->databaseManager->table('profiles')->whereIn('organizer_id', $context->organizerIds),
                entity: 'profiles',
                columnStrategies: [
                    'name' => AnonymizationStrategy::SCRUB_TEXT,
                    'tagline' => AnonymizationStrategy::NULLIFY,
                    'bio' => AnonymizationStrategy::NULLIFY,
                    'links' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
            $this->executor->delete(
                query: $this->databaseManager->table('organizer_stripe_platforms')->whereIn('organizer_id', $context->organizerIds),
                entity: 'organizer_stripe_platforms',
            ),
            $this->executor->softDelete(
                query: $this->databaseManager->table('organizers')->whereIn('id', $context->organizerIds),
                entity: 'organizers',
            ),
        ];
    }
}
