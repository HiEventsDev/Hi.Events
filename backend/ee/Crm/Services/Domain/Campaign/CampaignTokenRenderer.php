<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

class CampaignTokenRenderer
{
    /**
     * @param  array<string, string|null>  $values
     */
    public function renderSubject(string $subject, array $values): string
    {
        return trim((string) preg_replace('/\s*\R\s*/u', ' ', $this->replace($subject, $values, false)));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function renderBody(string $body, array $values): string
    {
        return $this->replace($body, $values, true);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function renderText(string $text, array $values): string
    {
        return trim($this->replace($text, $values, false));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function replace(string $template, array $values, bool $escape): string
    {
        return (string) preg_replace_callback(
            '/'.CampaignTokenCatalog::tagPattern().'/',
            static function (array $match) use ($values, $escape): string {
                if (! array_key_exists($match[1], $values)) {
                    return $match[0];
                }

                $value = trim((string) preg_replace('/\s+/u', ' ', (string) $values[$match[1]]));

                if ($value === '') {
                    return $match[2] ?? '';
                }

                return $escape ? e($value) : $value;
            },
            $template,
        );
    }
}
