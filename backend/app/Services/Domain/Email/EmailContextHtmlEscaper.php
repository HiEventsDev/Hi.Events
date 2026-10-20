<?php

namespace HiEvents\Services\Domain\Email;

class EmailContextHtmlEscaper
{
    private const PURIFIED_HTML_PATHS = [
        'event.description',
        'settings.post_checkout_message',
        'settings.offline_payment_instructions',
        'event_location.online_connection_details',
    ];

    public function escape(array $context): array
    {
        return $this->escapeValues($context, '');
    }

    private function escapeValues(array $values, string $path): array
    {
        foreach ($values as $key => $value) {
            $valuePath = $path === '' ? (string) $key : $path.'.'.$key;

            if (is_array($value)) {
                $values[$key] = $this->escapeValues($value, $valuePath);

                continue;
            }

            if (is_string($value) && ! in_array($valuePath, self::PURIFIED_HTML_PATHS, true)) {
                $values[$key] = e($value);
            }
        }

        return $values;
    }
}
