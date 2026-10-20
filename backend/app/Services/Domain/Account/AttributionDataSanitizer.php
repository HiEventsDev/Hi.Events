<?php

namespace HiEvents\Services\Domain\Account;

class AttributionDataSanitizer
{
    private const MAX_UTM_RAW_PARAMS = 20;

    private const MAX_UTM_RAW_LENGTH = 255;

    private const FIELDS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer_url',
        'landing_page',
        'gclid',
        'fbclid',
    ];

    public function fromValidated(array $validated): array
    {
        $attribution = [];

        foreach (self::FIELDS as $field) {
            $attribution[$field] = $validated[$field] ?? null;
        }

        $attribution['utm_raw'] = $this->sanitizeUtmRaw($validated['utm_raw'] ?? null);

        return $attribution;
    }

    private function sanitizeUtmRaw(?array $utmRaw): ?array
    {
        if ($utmRaw === null) {
            return null;
        }

        $sanitized = collect($utmRaw)
            ->filter(fn (mixed $value, mixed $key) => is_string($key) && is_scalar($value))
            ->take(self::MAX_UTM_RAW_PARAMS)
            ->mapWithKeys(fn (mixed $value, string $key) => [
                mb_substr($key, 0, self::MAX_UTM_RAW_LENGTH) => mb_substr((string) $value, 0, self::MAX_UTM_RAW_LENGTH),
            ])
            ->all();

        return $sanitized === [] ? null : $sanitized;
    }
}
